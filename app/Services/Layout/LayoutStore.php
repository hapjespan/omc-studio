<?php

namespace App\Services\Layout;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\ValidationException;

class LayoutStore
{
    use WritableGuard;

    private const FORMAT_VERSION = 1;

    public function __construct(private readonly SerialisedWrite $writes, private readonly LayoutAudit $audit, private readonly LayoutRows $rows) {}

    /** @param array<string, mixed> $payload */
    public function save(Canvas $canvas, array $payload): int
    {
        return $this->writes->run([$canvas], function () use ($canvas, $payload): int {
            $this->assertWritable($canvas);

            if ((int) $payload['structure_version'] !== (int) $canvas->structure_version) {
                $this->conflict('structure_changed');
            }

            if ((int) $payload['layout_version'] !== (int) $canvas->layout_version) {
                $this->conflict('layout_changed');
            }

            $elements = $this->elements($canvas);

            (new LayoutValidator($elements))->assertValid($payload);

            $before = $this->audit->current($canvas);

            $this->replace($canvas, $elements, $payload);

            $this->audit->record($canvas, $before, $this->audit->incoming($payload));

            $canvas->raiseLayoutVersion();

            return (int) $canvas->layout_version;
        });
    }

    public function reset(Canvas $canvas, string $scope, ?string $elementId): int
    {
        return $this->writes->run([$canvas], function () use ($canvas, $scope, $elementId): int {
            $this->assertWritable($canvas);

            $before = $this->audit->current($canvas);

            if ($scope === 'canvas') {
                $this->rows->clear($canvas);
            } else {
                $this->rows->remove($canvas, $this->resetIds($canvas, $scope, (string) $elementId));
            }

            $this->audit->record($canvas, $before, $this->audit->current($canvas));

            return (int) Canvas::query()->whereKey($canvas->getKey())->value('layout_version');
        });
    }

    /** @return list<string> */
    private function resetIds(Canvas $canvas, string $scope, string $elementId): array
    {
        $element = Element::query()->where('canvas_id', $canvas->getKey())->whereNull('archived_at')->find($elementId);

        if ($element === null) {
            throw ValidationException::withMessages(['rule' => __('layout.rule.unknown_element', ['id' => $elementId])]);
        }

        if ($scope === 'element') {
            return [$elementId];
        }

        if ($element->kind !== ElementKind::Domain) {
            throw ValidationException::withMessages(['rule' => __('layout.reset.not_domain', ['name' => $element->name])]);
        }

        return $this->rows->subtree($element);
    }

    /**
     * @param  list<array{id: string, area: string, parent: ?string, kind: string, name: string}>  $elements
     * @param  array<string, mixed>  $payload
     */
    private function replace(Canvas $canvas, array $elements, array $payload): void
    {
        $areas = Area::query()->pluck('id', 'key');
        $rows = [];

        foreach ($payload['areas'] as $key => $box) {
            $rows[] = $this->row($canvas, ['area_id' => $areas[$key]], $box);
        }

        foreach ($elements as $element) {
            $box = $payload['elements'][$element['id']];

            if ($box['mode'] === 'manual') {
                $rows[] = $this->row($canvas, ['element_id' => $element['id']], $box);
            }
        }

        Layout::query()->where('canvas_id', $canvas->getKey())->delete();
        Layout::query()->insert($rows);
    }

    /**
     * @param  array<string, mixed>  $owner
     * @param  array<string, mixed>  $box
     * @return array<string, mixed>
     */
    private function row(Canvas $canvas, array $owner, array $box): array
    {
        return [
            'canvas_id' => $canvas->getKey(),
            'area_id' => null,
            'element_id' => null,
            ...$owner,
            'x' => $box['x'],
            'y' => $box['y'],
            'width' => $box['width'],
            'height' => $box['height'],
            'z' => $box['z'] ?? 0,
            'style' => json_encode($box['style'] ?? [], JSON_THROW_ON_ERROR),
            'format_version' => self::FORMAT_VERSION,
            'updated_at' => now(),
        ];
    }

    /** @return list<array{id: string, area: string, parent: ?string, kind: string, name: string}> */
    private function elements(Canvas $canvas): array
    {
        $rows = Element::query()
            ->where('elements.canvas_id', $canvas->getKey())
            ->whereNull('elements.archived_at')
            ->join('areas', 'areas.id', '=', 'elements.area_id')
            ->orderBy('elements.id')
            ->get(['elements.id', 'areas.key as area', 'elements.parent_id as parent', 'elements.kind', 'elements.name']);

        return $rows->map(fn (Element $row): array => [
            'id' => (string) $row->getKey(),
            'area' => (string) $row->getAttribute('area'),
            'parent' => $row->getAttribute('parent') === null ? null : (string) $row->getAttribute('parent'),
            'kind' => $row->kind->value,
            'name' => (string) $row->name,
        ])->all();
    }

    private function conflict(string $code): never
    {
        throw new HttpResponseException(response()->json(['message' => (string) __('layout.conflict.'.$code), 'conflict' => $code], 409));
    }
}
