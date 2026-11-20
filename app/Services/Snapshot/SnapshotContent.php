<?php

namespace App\Services\Snapshot;

use App\Calculation\FigureCalculator;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Modality;
use App\Models\Statement;
use App\Models\YearlyFigure;
use App\Services\Finance\CalculationInputBuilder;

class SnapshotContent
{
    public const SCHEMA_VERSION = 1;

    public function __construct(private readonly CalculationInputBuilder $builder, private readonly FigureCalculator $calculator) {}

    /** @return array<string, mixed> */
    public function build(Canvas $canvas, FiscalYear $year): array
    {
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'canvas_name' => $canvas->name,
            'fiscal_year' => $year->year,
            'statements' => $this->statements($canvas),
            'elements' => $this->elements($canvas),
            'cost_records' => $this->costRecords($canvas, $year),
            'allocation_sets' => $this->sets($canvas, $year),
            'figures' => get_object_vars($this->calculator->calculate($this->builder->build($canvas, $year->year))),
        ];
    }

    /** @param array<string, mixed> $content */
    public function encode(array $content): string
    {
        return json_encode($content, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function checksum(string $encoded): string
    {
        return hash('sha256', $encoded);
    }

    /** @return list<array<string, mixed>> */
    private function statements(Canvas $canvas): array
    {
        return Statement::where('canvas_id', $canvas->getKey())->orderBy('kind')->orderBy('position')->orderBy('id')->get()
            ->map(fn (Statement $statement) => [...$statement->only(['title', 'body', 'position']), 'kind' => $statement->kind->value])->all();
    }

    /** @return list<array<string, mixed>> */
    private function elements(Canvas $canvas): array
    {
        $modalities = Modality::pluck('key', 'id')->all();

        return Element::without('canvas')->with('area')->where('canvas_id', $canvas->getKey())->whereNull('archived_at')->orderBy('position')->orderBy('id')->get()
            ->map(fn (Element $element) => [
                'id' => $element->id,
                'parent_id' => $element->parent_id,
                'area' => $element->area->key,
                'kind' => $element->kind->value,
                'name' => $element->name,
                'description' => $element->description,
                'modality' => $element->modality_id === null ? null : $modalities[$element->modality_id],
                'rationale' => $element->rationale,
                'icon' => $element->icon,
                'position' => $element->position,
                'is_unknown_allocation' => $element->is_unknown_allocation,
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function costRecords(Canvas $canvas, FiscalYear $year): array
    {
        return CostRecord::with(['owner', 'application', 'yearlyFigures' => fn ($query) => $query->where('fiscal_year_id', $year->getKey())])
            ->where('organisation_id', $canvas->organisation_id)
            ->whereHas('yearlyFigures', fn ($query) => $query->where('fiscal_year_id', $year->getKey()))
            ->orderBy('id')->get()
            ->map(fn (CostRecord $record) => [
                'id' => $record->id,
                'code' => $record->code,
                'name' => $record->name,
                'cost_kind' => $record->cost_kind->value,
                'supplier' => $record->supplier,
                'owner' => $record->owner->name,
                'cost_centre' => $record->cost_centre,
                'application' => $record->application?->name,
                'description' => $record->description,
                'yearly_figure' => $record->yearlyFigures->map(fn (YearlyFigure $figure) => [
                    ...$figure->only(['budget_cents', 'forecast_cents', 'run_bp', 'change_bp', 'fte_hundredths']),
                    'reference_date' => $figure->reference_date?->format('Y-m-d'),
                    'remark' => $figure->remark,
                ])->first(),
            ])->all();
    }

    /** @return list<array<string, mixed>> */
    private function sets(Canvas $canvas, FiscalYear $year): array
    {
        return AllocationSet::with('lines')->where('canvas_id', $canvas->getKey())->where('fiscal_year_id', $year->getKey())->orderBy('id')->get()
            ->map(fn (AllocationSet $set) => [
                'cost_record_id' => $set->cost_record_id,
                'lines' => $set->lines->sortBy('position')->map(fn ($line) => ['element_id' => $line->element_id, 'basis_points' => $line->basis_points])->values()->all(),
            ])->all();
    }
}
