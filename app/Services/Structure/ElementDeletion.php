<?php

namespace App\Services\Structure;

use App\Enums\ElementKind;
use App\Models\Element;
use App\Models\User;
use App\Services\Audit\DeletesModelByModel;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Services\Undo\UndoStore;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ElementDeletion
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly DeletesModelByModel $removal,
        private readonly UndoStore $undo,
        private readonly ElementNames $names,
        private readonly SiblingPosition $positions,
    ) {}

    public function delete(Element $element, User $user, ?string $clusterChildren = null): string
    {
        $canvas = $element->canvas;

        return $this->writes->run([$canvas], function () use ($canvas, $element, $user, $clusterChildren) {
            $this->assertStructureWritable($canvas);

            $element->refresh();

            $this->liftWhenAsked($element, $clusterChildren);

            $token = $this->undo->remember(UndoStore::ELEMENT_DELETION, ['elements' => $this->rows($element)], $user);

            $this->removal->delete($element);

            return $token;
        });
    }

    private function liftWhenAsked(Element $element, ?string $choice): void
    {
        if ($element->kind !== ElementKind::Cluster) {
            return;
        }

        $children = $element->children()->orderBy('position')->get();

        if ($children->isEmpty()) {
            return;
        }

        if ($choice === 'delete') {
            return;
        }

        if ($choice !== 'lift') {
            $this->refuse('cluster_choice_required');
        }

        $this->lift($element, $children);
    }

    /** @param Collection<int, Element> $children */
    private function lift(Element $cluster, Collection $children): void
    {
        $taken = $children->filter(fn (Element $child) => $this->names->isTaken($cluster->canvas_id, $cluster->area_id, $cluster->parent_id, $child->name));

        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages(['rule' => __('rule.cluster_lift_taken', ['names' => $taken->pluck('name')->join(', ')])]);
        }

        $next = $this->positions->next($cluster->canvas_id, $cluster->area_id, $cluster->parent_id, ElementKind::Activity);

        foreach ($children as $offset => $child) {
            $child->update(['parent_id' => $cluster->parent_id, 'position' => $next + $offset]);
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(Element $element): array
    {
        $rows = [$element->getAttributes()];

        foreach ($element->children()->orderBy('position')->get() as $child) {
            array_push($rows, ...$this->rows($child));
        }

        return $rows;
    }
}
