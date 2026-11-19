<?php

namespace App\Services\Structure;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;

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
