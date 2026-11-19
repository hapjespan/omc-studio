<?php

namespace App\Services\Lists;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BulkResult
{
    /** @var list<int|string> */
    private array $doneIds = [];

    /** @var list<array{name: string, reason: string}> */
    private array $skipped = [];

    /**
     * @template TModel of Model
     *
     * @param  Collection<int, TModel>  $rows
     * @param  Closure(TModel): mixed  $action
     */
    public static function apply(Collection $rows, Closure $action): self
    {
        $result = new self;

        DB::transaction(function () use ($rows, $action, $result) {
            foreach ($rows as $row) {
                try {
                    DB::transaction(fn () => $action($row));
                    $result->doneIds[] = $row->getKey();
                } catch (ValidationException $refusal) {
                    $result->skipped[] = ['name' => (string) $row->getAttribute('name'), 'reason' => (string) collect($refusal->errors())->flatten()->first()];
                }
            }
        });

        return $result;
    }

    public function doneCount(): int
    {
        return count($this->doneIds);
    }

    /** @return list<array{name: string, reason: string}> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @return array{done: int, done_ids: list<int|string>, skipped: list<array{name: string, reason: string}>} */
    public function toArray(): array
    {
        return ['done' => $this->doneCount(), 'done_ids' => $this->doneIds, 'skipped' => $this->skipped];
    }
}
