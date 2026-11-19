<?php

namespace App\Services\Structure;

use App\Models\Element;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;

class ElementArchive
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly ElementNames $names,
        private readonly SiblingPosition $positions,
    ) {}

    public function archive(Element $element): void
    {
        $canvas = $element->canvas;

        $this->writes->run([$canvas], function () use ($canvas, $element) {
            $this->assertStructureWritable($canvas);

            $element->refresh();

            if ($element->archived_at === null) {
                $this->stamp($element, now(), null);
            }
        });
    }

    public function restore(Element $element): void
    {
        $canvas = $element->canvas;

        $this->writes->run([$canvas], function () use ($canvas, $element) {
            $this->assertStructureWritable($canvas);

            $element->refresh();

            if ($element->archived_at === null) {
                return;
            }

            $this->assertParentActive($element);
            $this->names->assertAvailable($element->canvas_id, $element->area_id, $element->parent_id, $element->name, $element->id);

            $element->position = $this->positions->next($element->canvas_id, $element->area_id, $element->parent_id, $element->kind);

            $this->stamp($element, null, $element->archived_at);
        });
    }

    private function assertParentActive(Element $element): void
    {
        if ($element->parent_id !== null && Element::query()->whereKey($element->parent_id)->whereNotNull('archived_at')->exists()) {
            $this->refuse('restore_parent_inactive');
        }
    }

    private function stamp(Element $element, mixed $archivedAt, mixed $matching): void
    {
        $element->update(['archived_at' => $archivedAt]);

        $children = $element->children()->get();

        foreach ($children as $child) {
            if ($matching === null ? $child->archived_at === null : $child->archived_at?->equalTo($matching)) {
                $this->stamp($child, $archivedAt, $matching);
            }
        }
    }
}
