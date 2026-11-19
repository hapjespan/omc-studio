<?php

namespace App\Services\Structure;

use App\Enums\ElementKind;
use App\Models\Element;

class SiblingPosition
{
    public function next(int $canvasId, int $areaId, ?string $parentId, ElementKind $kind): int
    {
        $highest = Element::query()
            ->where('canvas_id', $canvasId)
            ->where('area_id', $areaId)
            ->where('parent_id', $parentId)
            ->where('kind', $kind)
            ->where('is_unknown_allocation', false)
            ->whereNull('archived_at')
            ->max('position');

        return $highest === null ? 0 : (int) $highest + 1;
    }
}
