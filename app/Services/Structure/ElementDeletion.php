<?php

namespace App\Services\Structure;

use App\Models\Element;
use App\Models\User;
use App\Services\Audit\DeletesModelByModel;
use App\Services\Support\SerialisedWrite;
use App\Services\Support\WritableGuard;
use App\Services\Undo\UndoStore;

class ElementDeletion
{
    use WritableGuard;

    public function __construct(
        private readonly SerialisedWrite $writes,
        private readonly DeletesModelByModel $removal,
        private readonly UndoStore $undo,
    ) {}

    public function delete(Element $element, User $user): string
    {
        $canvas = $element->canvas;

        return $this->writes->run([$canvas], function () use ($canvas, $element, $user) {
            $this->assertStructureWritable($canvas);

            $element->refresh();

            $token = $this->undo->remember(UndoStore::ELEMENT_DELETION, ['elements' => $this->rows($element)], $user);

            $this->removal->delete($element);

            return $token;
        });
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
