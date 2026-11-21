<?php

namespace App\Services\Canvas;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Statement;
use App\Services\Layout\LayoutSnapshot;
use Illuminate\Support\Collection;

class ViewData
{
    /** @var array{keys: Collection<int, string>, letters: Collection<string, string>}|null */
    private ?array $lookups = null;

    public function __construct(private readonly LayoutSnapshot $layouts) {}

    /** @return array<string, mixed> */
    public function for(Canvas $canvas): array
    {
        return [
            'canWrite' => false,
            'layouts' => $this->layouts->for($canvas),
            'layout' => $this->layout(),
            'elements' => $this->elements(Element::where('canvas_id', $canvas->id)->whereNull('archived_at')->with('area:id,key')->get()),
            'statements' => $this->statements(Statement::where('canvas_id', $canvas->id)->orderBy('position')->get()),
            'modalities' => $this->modalities(),
        ];
    }

    /** @return array{areas: list<array{key: string, kind: string, position: int, label: string}>, directionLabels: array<string, string>} */
    public function layout(): array
    {
        return [
            'areas' => $this->areas(),
            'directionLabels' => ['mission' => __('overview.mission'), 'vision' => __('overview.vision'), 'goals' => __('view.direction.goals')],
        ];
    }

    /** @return list<array{key: string, kind: string, position: int, label: string}> */
    public function areas(): array
    {
        return Area::orderBy('position')->get()->map(fn (Area $area) => [
            'key' => $area->key,
            'kind' => $area->kind,
            'position' => $area->position,
            'label' => __('areas.'.$area->key.'.name'),
        ])->values()->all();
    }

    /**
     * @param  iterable<Element|array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public function elements(iterable $rows, bool $withText = true): array
    {
        $keys = $this->modalityLookups()['keys'];
        $byKey = $this->modalityLookups()['letters'];
        $out = [];

        foreach ($rows as $row) {
            $stored = $row instanceof Element ? [
                'id' => $row->id, 'parent_id' => $row->parent_id, 'area' => $row->area->key, 'kind' => $row->kind->value, 'name' => $row->name,
                'position' => $row->position, 'is_unknown_allocation' => $row->is_unknown_allocation, 'description' => $row->description, 'rationale' => $row->rationale,
                'modality' => $row->modality_id === null ? null : $keys[$row->modality_id] ?? null,
            ] : $row;
            $element = [
                'id' => $stored['id'],
                'area' => $stored['area'],
                'parent' => $stored['parent_id'],
                'kind' => $stored['kind'],
                'name' => $stored['name'],
                'position' => $stored['position'],
                'modality' => $byKey[$stored['modality']] ?? null,
                'unknownAllocation' => $stored['is_unknown_allocation'],
            ];

            $out[] = $withText ? [...$element, 'description' => $stored['description'], 'rationale' => $stored['rationale']] : $element;
        }

        return $out;
    }

    /**
     * @param  iterable<Statement|array<string, mixed>>  $rows
     * @return list<array{kind: string, title: ?string, body: ?string, position: int}>
     */
    public function statements(iterable $rows): array
    {
        $out = [];

        foreach ($rows as $row) {
            $stored = $row instanceof Statement ? ['kind' => $row->kind->value, 'title' => $row->title, 'body' => $row->body, 'position' => $row->position] : $row;
            $out[] = ['kind' => $stored['kind'], 'title' => $stored['title'], 'body' => $stored['body'], 'position' => $stored['position']];
        }

        return $out;
    }

    /** @return list<array{key: string, letter: string, colour: string}> */
    public function modalities(): array
    {
        return Modality::orderBy('position')->get()->map(fn (Modality $modality) => ['key' => $modality->key, 'letter' => $modality->letter, 'colour' => $modality->colour])->values()->all();
    }

    /** @return array{keys: Collection<int, string>, letters: Collection<string, string>} */
    private function modalityLookups(): array
    {
        if ($this->lookups === null) {
            $all = Modality::all();
            $this->lookups = ['keys' => $all->pluck('key', 'id'), 'letters' => $all->pluck('letter', 'key')];
        }

        return $this->lookups;
    }
}
