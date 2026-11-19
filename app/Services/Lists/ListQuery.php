<?php

namespace App\Services\Lists;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class ListQuery
{
    /**
     * @param  list<string>  $searchable
     * @param  list<string>  $sortable
     * @param  array<string, string>  $filters
     */
    public function __construct(
        private readonly array $searchable,
        private readonly array $sortable,
        private readonly array $filters = [],
        private readonly ?string $archivedColumn = null,
    ) {}

    /**
     * @param  Builder<*>  $query
     * @param  array<string, mixed>  $input
     * @return array{rows: list<array<string, mixed>>, count: int, query: array<string, mixed>}
     */
    public function run(Builder $query, array $input): array
    {
        if (array_filter([$input['search'] ?? null, $input['sort'] ?? null, $input['direction'] ?? null, $input['archived'] ?? null], 'is_array') !== []) {
            $this->refuse('query');
        }

        $search = trim((string) ($input['search'] ?? ''));
        $sort = isset($input['sort']) ? (string) $input['sort'] : null;
        $direction = ($input['direction'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        $filters = (array) ($input['filters'] ?? []);

        if (array_filter($filters, 'is_array') !== []) {
            $this->refuse('filters');
        }

        $filters = array_map('strval', $filters);
        $archived = filter_var($input['archived'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if ($sort !== null && ! in_array($sort, $this->sortable, true)) {
            $this->refuse('sort');
        }

        foreach ($filters as $name => $value) {
            if (! isset($this->filters[$name])) {
                $this->refuse('filters');
            }

            $query->where($this->filters[$name], $value);
        }

        if ($search !== '') {
            $like = '%'.addcslashes(mb_strtolower($search), '\\%_').'%';
            $query->where(function (Builder $inner) use ($like) {
                foreach ($this->searchable as $column) {
                    $inner->orWhereRaw('LOWER('.$inner->getGrammar()->wrap($column).") LIKE ? ESCAPE '\\'", [$like]);
                }
            });
        }

        if ($this->archivedColumn !== null && ! $archived) {
            $query->whereNull($this->archivedColumn);
        }

        if ($sort !== null) {
            $query->reorder();

            if (in_array($sort, $this->searchable, true)) {
                $query->orderByRaw('LOWER('.$query->getGrammar()->wrap($sort).") $direction");
            }

            $query->orderBy($sort, $direction);
        }

        $rows = $query->get()->toArray();

        return [
            'rows' => $rows,
            'count' => count($rows),
            'query' => ['search' => $search, 'sort' => $sort, 'direction' => $direction, 'filters' => $filters, 'archived' => $archived],
        ];
    }

    private function refuse(string $key): never
    {
        throw ValidationException::withMessages([$key => __('list.invalid_'.$key)]);
    }
}
