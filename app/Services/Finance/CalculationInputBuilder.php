<?php

namespace App\Services\Finance;

use App\Calculation\CalculationInput;
use App\Models\Canvas;
use App\Models\FiscalYear;
use Illuminate\Support\Facades\DB;

class CalculationInputBuilder
{
    /** @param array{costKind?: string|null, ownerId?: int|string|null, applicationId?: int|string|null} $filter */
    public function build(Canvas $canvas, int $year, array $filter = []): CalculationInput
    {
        $fiscalYearId = FiscalYear::where('organisation_id', $canvas->organisation_id)->where('year', $year)->value('id');

        $elements = DB::table('elements')
            ->join('areas', 'areas.id', '=', 'elements.area_id')
            ->leftJoin('modalities', 'modalities.id', '=', 'elements.modality_id')
            ->where('elements.canvas_id', $canvas->id)
            ->whereNull('elements.archived_at')
            ->orderBy('elements.position')
            ->orderBy('elements.id')
            ->get(['elements.id', 'elements.parent_id', 'areas.key as area_key', 'elements.kind', 'modalities.key as modality_key', 'elements.is_unknown_allocation'])
            ->map(fn (object $row) => [
                'id' => $row->id,
                'parentId' => $row->parent_id,
                'areaKey' => $row->area_key,
                'kind' => $row->kind,
                'modalityKey' => $row->modality_key,
                'isUnknownAllocation' => (bool) $row->is_unknown_allocation,
            ])->all();

        $areas = DB::table('areas')->orderBy('position')->get(['key', 'position', 'carries_costs'])
            ->map(fn (object $row) => ['key' => $row->key, 'position' => (int) $row->position, 'carriesCosts' => (bool) $row->carries_costs])->all();

        $costRecords = DB::table('cost_records')
            ->join('yearly_figures', 'yearly_figures.cost_record_id', '=', 'cost_records.id')
            ->where('cost_records.organisation_id', $canvas->organisation_id)
            ->where('yearly_figures.fiscal_year_id', $fiscalYearId)
            ->when($filter['costKind'] ?? null, fn ($query, $value) => $query->where('cost_records.cost_kind', $value))
            ->when($filter['ownerId'] ?? null, fn ($query, $value) => $query->where('cost_records.owner_id', $value))
            ->when($filter['applicationId'] ?? null, fn ($query, $value) => $query->where('cost_records.application_id', $value))
            ->orderBy('cost_records.id')
            ->get(['cost_records.id', 'cost_records.cost_kind', 'cost_records.owner_id', 'cost_records.application_id', 'yearly_figures.budget_cents', 'yearly_figures.forecast_cents', 'yearly_figures.run_bp', 'yearly_figures.change_bp', 'yearly_figures.fte_hundredths'])
            ->map(fn (object $row) => [
                'id' => $row->id,
                'costKind' => $row->cost_kind,
                'ownerId' => $row->owner_id,
                'applicationId' => $row->application_id,
                'budgetCents' => (int) $row->budget_cents,
                'forecastCents' => $row->forecast_cents === null ? null : (int) $row->forecast_cents,
                'runBp' => $row->run_bp === null ? null : (int) $row->run_bp,
                'changeBp' => $row->change_bp === null ? null : (int) $row->change_bp,
                'fteHundredths' => $row->fte_hundredths === null ? null : (int) $row->fte_hundredths,
            ])->all();

        $included = array_flip(array_column($costRecords, 'id'));
        $sets = [];
        $lines = DB::table('allocation_lines')
            ->join('allocation_sets', 'allocation_sets.id', '=', 'allocation_lines.allocation_set_id')
            ->where('allocation_sets.canvas_id', $canvas->id)
            ->where('allocation_sets.fiscal_year_id', $fiscalYearId)
            ->orderBy('allocation_sets.id')
            ->orderBy('allocation_lines.position')
            ->get(['allocation_sets.cost_record_id', 'allocation_lines.element_id', 'allocation_lines.basis_points']);
        foreach ($lines as $line) {
            if (isset($included[$line->cost_record_id])) {
                $sets[$line->cost_record_id][] = ['elementId' => $line->element_id, 'basisPoints' => (int) $line->basis_points];
            }
        }

        return new CalculationInput($elements, $areas, $costRecords, $sets);
    }
}
