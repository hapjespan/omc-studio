<?php

namespace App\Services\Audit;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

class DeletesModelByModel
{
    public function delete(Model $model, string $children = 'children'): void
    {
        $relation = $model->{$children}();

        if ($relation instanceof Relation) {
            foreach ($relation->get() as $child) {
                $this->delete($child, $children);
            }
        }

        $model->delete();
    }
}
