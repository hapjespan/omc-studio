<?php

namespace App\Services\Support;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SerialisedWrite
{
    private const ORDER = [Organisation::class, Canvas::class, User::class];

    /**
     * @template TReturn
     *
     * @param  array<int, Model>  $aggregates
     * @param  Closure(): TReturn  $write
     * @return TReturn
     */
    public function run(array $aggregates, Closure $write): mixed
    {
        $ordered = $this->ordered($aggregates);

        return DB::transaction(function () use ($ordered, $write) {
            foreach ($ordered as $aggregate) {
                $locked = $aggregate->newQueryWithoutScopes()->whereKey($aggregate->getKey())->lockForUpdate()->firstOrFail();

                $aggregate->setRawAttributes($locked->getAttributes(), true);
            }

            return $write();
        });
    }

    /**
     * @param  array<int, Model>  $aggregates
     * @return array<int, Model>
     */
    private function ordered(array $aggregates): array
    {
        foreach ($aggregates as $aggregate) {
            $this->position($aggregate);
        }

        usort($aggregates, fn (Model $first, Model $second) => [$this->position($first), $first->getKey()] <=> [$this->position($second), $second->getKey()]);

        return $aggregates;
    }

    private function position(Model $aggregate): int
    {
        $position = array_search($aggregate::class, self::ORDER, true);

        if ($position === false) {
            throw new InvalidArgumentException($aggregate::class.' holds no place in the lock order');
        }

        return $position;
    }
}
