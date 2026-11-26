<?php

namespace Tests\Feature\Allocation;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use Tests\Feature\Structure\StructureTestCase;

abstract class AllocationTestCase extends StructureTestCase
{
    protected function admin(): User
    {
        return User::factory()->administrator()->create();
    }

    protected function fiscalYear(int $year, ?int $organisationId = null): FiscalYear
    {
        return FiscalYear::firstOrCreate(['organisation_id' => $organisationId ?? $this->canvas->organisation_id, 'year' => $year]);
    }

    protected function costRecord(string $name = 'Licentie', ?int $year = 2026, int $budget = 100_000, ?int $organisationId = null): CostRecord
    {
        $organisationId ??= $this->canvas->organisation_id;

        $record = CostRecord::factory()->create([
            'organisation_id' => $organisationId,
            'owner_id' => Owner::factory()->create(['organisation_id' => $organisationId])->id,
            'name' => $name,
        ]);

        if ($year !== null) {
            YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->fiscalYear($year, $organisationId)->id, 'budget_cents' => $budget]);
        }

        return $record;
    }

    /** @param array<string, int> $lines */
    protected function allocate(CostRecord $record, int $year, array $lines, ?int $canvasId = null): AllocationSet
    {
        $set = AllocationSet::create([
            'canvas_id' => $canvasId ?? $this->canvas->id,
            'cost_record_id' => $record->id,
            'fiscal_year_id' => $this->fiscalYear($year)->id,
        ]);

        $position = 0;
        foreach ($lines as $elementId => $basisPoints) {
            AllocationLine::create(['allocation_set_id' => $set->id, 'element_id' => $elementId, 'basis_points' => $basisPoints, 'position' => $position++]);
        }

        return $set;
    }

    protected function finalise(int $year, ?int $canvasId = null): FinalisedYear
    {
        return FinalisedYear::create([
            'canvas_id' => $canvasId ?? $this->canvas->id,
            'fiscal_year_id' => $this->fiscalYear($year)->id,
            'finalised_at' => now(),
        ]);
    }

    protected function target(string $name = 'Doel'): Element
    {
        return $this->activity($this->domain('primary_activities', $name.' domein'), $name);
    }
}
