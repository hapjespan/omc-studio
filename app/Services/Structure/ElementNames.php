<?php

namespace App\Services\Structure;

use App\Models\Element;
use Illuminate\Validation\ValidationException;

class ElementNames
{
    public function assertAvailable(int $canvasId, int $areaId, ?string $parentId, string $name, ?string $ignoring = null): void
    {
        $this->refuseWhen(trim($name) === '', __('rule.name_required'));
        $this->refuseWhen(str_contains($name, '>'), __('validation.name_forbidden_sign', ['label' => __('fields.name')]));

        $taken = $this->isTaken($canvasId, $areaId, $parentId, $name, $ignoring);

        $this->refuseWhen($taken, __('rule.element_name_taken'));
    }

    public function isTaken(int $canvasId, int $areaId, ?string $parentId, string $name, ?string $ignoring = null): bool
    {
        return Element::query()
            ->where('canvas_id', $canvasId)
            ->where('area_id', $areaId)
            ->where('parent_id', $parentId)
            ->where('name_normalised', Element::normalise($name))
            ->whereNull('archived_at')
            ->when($ignoring, fn ($query) => $query->whereKeyNot($ignoring))
            ->exists();
    }

    private function refuseWhen(bool $condition, string $message): void
    {
        if ($condition) {
            throw ValidationException::withMessages(['rule' => $message]);
        }
    }
}
