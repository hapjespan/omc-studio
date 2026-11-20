<?php

namespace App\Http\Resources;

use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Services\Allocation\FinalisedYears;
use App\Services\Finance\FigureService;

class AllocationPage
{
    public function __construct(private readonly FigureService $figures, private readonly FinalisedYears $finalisedYears) {}

    /** @return array<string, mixed> */
    public function props(Canvas $canvas, ?FiscalYear $year, ?string $chosen): array
    {
        $figures = $year === null ? null : $this->figures->figures($canvas, $year->year);

        return [
            'canvas' => ['id' => $canvas->id, 'name' => $canvas->name, 'archived' => $canvas->archived_at !== null],
            'year' => $year?->year,
            'finalised' => $year !== null && $this->finalisedYears->finalisedInCanvas($canvas, $year),
            'costRecords' => $year === null ? [] : $this->costRecords($canvas, $year),
            'chosen' => $chosen,
            'set' => $year === null || $chosen === null ? [] : $this->set($canvas, $year, $chosen),
            'targets' => $this->targets($canvas, $figures->elements ?? null),
            'canvasTotalCents' => $figures?->canvasTotal['budget'] ?? null,
            'figuresAvailable' => $figures !== null,
        ];
    }

    /** @return list<array<string, mixed>> */
    private function costRecords(Canvas $canvas, FiscalYear $year): array
    {
        $allocated = AllocationSet::where('canvas_id', $canvas->getKey())->where('fiscal_year_id', $year->getKey())->pluck('cost_record_id')->all();

        return $canvas->organisation->costRecords()->with('owner:id,name')
            ->join('yearly_figures', fn ($join) => $join->on('yearly_figures.cost_record_id', '=', 'cost_records.id')->where('yearly_figures.fiscal_year_id', '=', $year->getKey()))
            ->orderByRaw('LOWER(cost_records.name)')
            ->get(['cost_records.id', 'cost_records.code', 'cost_records.name', 'cost_records.archived_at', 'cost_records.cost_kind', 'cost_records.owner_id', 'yearly_figures.budget_cents'])
            ->map(fn ($record) => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'archived' => $record->archived_at !== null,
                'costKind' => $record->cost_kind->value,
                'owner' => $record->owner?->name,
                'budgetCents' => (int) $record->getAttribute('budget_cents'),
                'status' => in_array($record->id, $allocated, true) ? 'allocated' : 'unallocated',
            ])->all();
    }

    /** @return list<array{elementId: string, basisPoints: int, position: int}> */
    private function set(Canvas $canvas, FiscalYear $year, string $costRecordId): array
    {
        $set = AllocationSet::where('canvas_id', $canvas->getKey())->where('fiscal_year_id', $year->getKey())->where('cost_record_id', $costRecordId)->first();

        return $set === null ? [] : $set->lines()->get()
            ->map(fn ($line) => ['elementId' => (string) $line->element_id, 'basisPoints' => $line->basis_points, 'position' => $line->position])->all();
    }

    /**
     * @param  array<string|int, array{total: array<string, int>}>|null  $totals
     * @return list<array<string, mixed>>
     */
    public function targets(Canvas $canvas, ?array $totals): array
    {
        $elements = Element::query()->with('area')
            ->where('canvas_id', $canvas->getKey())->whereNull('archived_at')->where('is_unknown_allocation', false)
            ->orderBy('position')->orderBy('name_normalised')->get()
            ->filter(fn (Element $element) => $element->area?->carries_costs === true)
            ->values();

        $byParent = [];
        foreach ($elements as $element) {
            $byParent[(string) $element->parent_id][] = $element;
        }

        return $this->branch($byParent, $totals, '');
    }

    /**
     * @param  array<string, list<Element>>  $elements
     * @param  array<string|int, array{total: array<string, int>}>|null  $totals
     * @return list<array<string, mixed>>
     */
    private function branch(array $elements, ?array $totals, string $parent): array
    {
        return collect($elements[$parent] ?? [])->map(fn (Element $element) => [
            'id' => $element->id,
            'name' => $element->name,
            'kind' => $element->kind->value,
            'area' => $element->area?->key,
            'totalCents' => $totals[$element->id]['total']['budget'] ?? null,
            'children' => $this->branch($elements, $totals, (string) $element->id),
        ])->values()->all();
    }
}
