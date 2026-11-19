<?php

namespace App\Services\Structure;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use InvalidArgumentException;

class ElementTree
{
    use WritableGuard;

    private const FIELDS = ['name', 'description', 'modality_id', 'rationale', 'icon'];

    public function __construct(private readonly SerialisedWrite $writes, private readonly ElementNames $names) {}

    /** @param array<string, mixed> $fields */
    public function create(Canvas $canvas, Area $area, ElementKind $kind, ?string $parentId, array $fields = []): Element
    {
        return $this->writes->run([$canvas], function () use ($canvas, $area, $kind, $parentId, $fields) {
            $this->assertStructureWritable($canvas);

            $parent = $this->parent($canvas, $area, $kind, $parentId);

            $this->names->assertAvailable($canvas->id, $area->id, $parent?->id, (string) ($fields['name'] ?? ''));

            return Element::create([
                ...array_intersect_key($fields, array_flip(self::FIELDS)),
                'canvas_id' => $canvas->id,
                'area_id' => $area->id,
                'parent_id' => $parent?->id,
                'kind' => $kind,
                'position' => $this->position($canvas, $area, $kind, $parent),
            ]);
        });
    }

    public function move(Element $element, Area|Element $target): Element
    {
        $canvas = $element->canvas;

        return $this->writes->run([$canvas], function () use ($canvas, $element, $target) {
            $this->assertStructureWritable($canvas);

            $element->refresh();

            $area = $target instanceof Area ? $target : $target->area;

            if ($area->kind === 'text') {
                $this->refuse('direction_no_elements');
            }

            if ($area->kind !== $element->area->kind) {
                $this->refuse('move_other_kind');
            }

            $parent = $this->parent($canvas, $area, $element->kind, $target instanceof Element ? (string) $target->id : null);

            $this->names->assertAvailable($canvas->id, $area->id, $parent?->id, (string) $element->name, (string) $element->id);

            $element->update([
                'area_id' => $area->id,
                'parent_id' => $parent?->id,
                'position' => $this->position($canvas, $area, $element->kind, $parent),
            ]);

            $this->carry($element);

            return $element;
        });
    }

    public function reorder(Element $element, string $direction): void
    {
        $step = match ($direction) {
            'up' => -1,
            'down' => 1,
            default => throw new InvalidArgumentException('Unknown direction '.$direction),
        };

        $canvas = $element->canvas;

        $this->writes->run([$canvas], function () use ($canvas, $element, $step) {
            $this->assertStructureWritable($canvas);

            $siblings = Element::query()
                ->where('canvas_id', $element->canvas_id)
                ->where('area_id', $element->area_id)
                ->where('parent_id', $element->parent_id)
                ->where('kind', $element->kind)
                ->where('is_unknown_allocation', false)
                ->whereNull('archived_at')
                ->orderBy('position')
                ->orderBy('id')
                ->get()
                ->values();

            $from = $siblings->search(fn (Element $sibling) => $sibling->is($element));
            $to = $from + $step;

            if ($from === false || ! isset($siblings[$to])) {
                return;
            }

            $order = $siblings->all();
            [$order[$from], $order[$to]] = [$order[$to], $order[$from]];

            foreach ($order as $index => $sibling) {
                if ($sibling->position !== $index) {
                    $sibling->update(['position' => $index]);
                }
            }
        });
    }

    private function carry(Element $element): void
    {
        $children = $element->children()->get();

        foreach ($children as $child) {
            $child->update(['area_id' => $element->area_id]);

            $this->carry($child);
        }
    }

    private function parent(Canvas $canvas, Area $area, ElementKind $kind, ?string $parentId): ?Element
    {
        if ($area->kind === 'text') {
            $this->refuse('direction_no_elements');
        }

        if ($kind === ElementKind::Cluster && $area->kind === 'list') {
            $this->refuse('no_clusters_in_area');
        }

        $parent = $parentId === null ? null : Element::query()->where('canvas_id', $canvas->id)->whereKey($parentId)->first();

        $fits = $parent !== null && $parent->area_id === $area->id;

        return match (true) {
            $kind === ElementKind::Domain && $parent !== null => $this->refuse('domain_parent'),
            $kind === ElementKind::Domain => null,
            $kind === ElementKind::Cluster && $parent?->kind === ElementKind::Cluster => $this->refuse('cluster_in_cluster'),
            $kind === ElementKind::Cluster => $fits && $parent->kind === ElementKind::Domain ? $parent : $this->refuse('cluster_parent'),
            default => $fits && $parent->kind !== ElementKind::Activity ? $parent : $this->refuse('activity_parent'),
        };
    }

    private function position(Canvas $canvas, Area $area, ElementKind $kind, ?Element $parent): int
    {
        $highest = Element::query()
            ->where('canvas_id', $canvas->id)
            ->where('area_id', $area->id)
            ->where('parent_id', $parent?->id)
            ->where('kind', $kind)
            ->where('is_unknown_allocation', false)
            ->whereNull('archived_at')
            ->max('position');

        return $highest === null ? 0 : (int) $highest + 1;
    }
}
