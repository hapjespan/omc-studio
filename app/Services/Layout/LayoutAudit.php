<?php

namespace App\Services\Layout;

use App\Models\Area;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditTrail;
use Illuminate\Database\Eloquent\Model;

class LayoutAudit
{
    private const ACTION = 'layout_changed';

    private const MERGE_SECONDS = 60;

    public function __construct(private readonly AuditTrail $trail, private readonly AuditContext $context) {}

    /** @return array<string, array<string, mixed>> */
    public function current(Canvas $canvas): array
    {
        $areaKeys = Area::query()->pluck('key', 'id');
        $states = [];

        foreach (Layout::query()->where('canvas_id', $canvas->getKey())->get() as $row) {
            $owner = $row->element_id !== null ? 'element:'.$row->element_id : 'area:'.$areaKeys[$row->area_id];
            $states[$owner] = $this->state($row->getAttributes(), $row->style);
        }

        return $states;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, array<string, mixed>>
     */
    public function incoming(array $payload): array
    {
        $states = [];

        foreach ($payload['areas'] as $key => $box) {
            $states['area:'.$key] = $this->state($box, $box['style'] ?? []);
        }

        foreach ($payload['elements'] as $id => $box) {
            if ($box['mode'] === 'manual') {
                $states['element:'.$id] = $this->state($box, $box['style'] ?? []);
            }
        }

        return $states;
    }

    /**
     * @param  array<string, array<string, mixed>>  $before
     * @param  array<string, array<string, mixed>>  $after
     */
    public function record(Canvas $canvas, array $before, array $after): void
    {
        foreach (array_keys($before + $after) as $owner) {
            $changes = $this->changes($before[$owner] ?? null, $after[$owner] ?? null);

            if ($changes !== []) {
                $this->write($canvas, $owner, $changes);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $box
     * @param  array<string, mixed>  $style
     * @return array<string, mixed>
     */
    private function state(array $box, array $style): array
    {
        ksort($style);

        return [
            'place' => ['x' => $box['x'], 'y' => $box['y'], 'z' => $box['z'] ?? 0],
            'size' => ['width' => $box['width'], 'height' => $box['height']],
            'style' => $style,
        ];
    }

    /**
     * @param  ?array<string, mixed>  $old
     * @param  ?array<string, mixed>  $new
     * @return array<string, array{mixed, mixed}>
     */
    private function changes(?array $old, ?array $new): array
    {
        $changes = [];

        foreach (['place', 'size', 'style'] as $field) {
            if (($old[$field] ?? null) !== ($new[$field] ?? null)) {
                $changes[$field] = [$old[$field] ?? null, $new[$field] ?? null];
            }
        }

        return $changes;
    }

    /** @param array<string, array{mixed, mixed}> $changes */
    private function write(Canvas $canvas, string $owner, array $changes): void
    {
        [$kind, $key] = explode(':', $owner, 2);
        $subject = $kind === 'element' ? Element::query()->findOrFail($key) : Area::query()->where('key', $key)->firstOrFail();
        $previous = $this->mergeable($subject, $canvas);

        if ($previous === null) {
            $this->trail->record($subject, self::ACTION, $changes, $canvas);

            return;
        }

        $merged = [];

        foreach ($changes + $previous->changes as $field => $pair) {
            $old = ($previous->changes[$field] ?? $pair)[0];
            $new = ($changes[$field] ?? [null, $previous->changes[$field][1] ?? null])[1];

            if ($old !== $new) {
                $merged[$field] = [$old, $new];
            }
        }

        $merged === [] ? $previous->delete() : $previous->update(['occurred_at' => now(), 'changes' => $merged]);
    }

    private function mergeable(Model $subject, Canvas $canvas): ?AuditEntry
    {
        $latest = AuditEntry::query()
            ->where('subject_type', $this->trail->kind($subject))
            ->where('subject_id', (string) $subject->getKey())
            ->where('canvas_id', $canvas->getKey())
            ->orderByDesc('id')
            ->first();

        if ($latest === null || $latest->action !== self::ACTION || $latest->actor_id !== $this->context->actorId()) {
            return null;
        }

        return $latest->occurred_at->greaterThanOrEqualTo(now()->subSeconds(self::MERGE_SECONDS)) ? $latest : null;
    }
}
