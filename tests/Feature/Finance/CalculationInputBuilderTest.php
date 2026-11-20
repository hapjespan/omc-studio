<?php

namespace Tests\Feature\Finance;

use App\Enums\CostKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Modality;
use App\Models\Organisation;
use App\Models\YearlyFigure;
use App\Services\Finance\CalculationInputBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CalculationInputBuilderTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private Canvas $canvas;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        $this->organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $figure
     */
    private function record(array $attributes = [], array $figure = ['budget_cents' => 1000]): CostRecord
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id] + $attributes);
        YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id] + $figure);

        return $record;
    }

    public function test_elements_are_the_active_ones_with_area_and_modality_keys(): void
    {
        $modality = Modality::firstOrCreate(['key' => 'm1'], ['letter' => 'A', 'colour' => '#000000', 'position' => 1]);
        $active = Element::factory()->create(['canvas_id' => $this->canvas->id, 'modality_id' => $modality->id]);
        Element::factory()->create(['canvas_id' => $this->canvas->id, 'archived_at' => now()]);
        Element::factory()->create();

        $input = (new CalculationInputBuilder)->build($this->canvas, 2026);

        $this->assertCount(1, $input->elements);
        $this->assertSame($active->id, $input->elements[0]['id']);
        $this->assertSame('primary_activities', $input->elements[0]['areaKey']);
        $this->assertSame('m1', $input->elements[0]['modalityKey']);
        $this->assertSame(Area::count(), count($input->areas));
    }

    public function test_cost_records_need_yearly_figures_in_the_year_and_archived_ones_count(): void
    {
        $this->record(['archived_at' => now()], ['budget_cents' => 500, 'forecast_cents' => 600, 'run_bp' => 7000, 'change_bp' => 1000, 'fte_hundredths' => 150]);
        CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);
        $other = CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);
        YearlyFigure::factory()->create(['cost_record_id' => $other->id, 'fiscal_year_id' => FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2025])->id]);
        CostRecord::factory()->has(YearlyFigure::factory()->state(['fiscal_year_id' => $this->year->id]))->create();

        $input = (new CalculationInputBuilder)->build($this->canvas, 2026);

        $this->assertCount(1, $input->costRecords);
        $this->assertSame(
            ['budgetCents' => 500, 'forecastCents' => 600, 'runBp' => 7000, 'changeBp' => 1000, 'fteHundredths' => 150],
            array_intersect_key($input->costRecords[0], array_flip(['budgetCents', 'forecastCents', 'runBp', 'changeBp', 'fteHundredths'])),
        );
    }

    public function test_filters_on_cost_kind_owner_and_application(): void
    {
        $personnel = $this->record(['cost_kind' => CostKind::Personnel]);
        $this->record(['cost_kind' => CostKind::Project]);

        $builder = new CalculationInputBuilder;
        $this->assertSame([$personnel->id], array_column($builder->build($this->canvas, 2026, ['costKind' => 'personnel'])->costRecords, 'id'));
        $this->assertSame([$personnel->id], array_column($builder->build($this->canvas, 2026, ['ownerId' => $personnel->owner_id])->costRecords, 'id'));
        $this->assertCount(2, $builder->build($this->canvas, 2026)->costRecords);
    }

    public function test_sets_are_keyed_by_cost_record_in_line_order(): void
    {
        $record = $this->record();
        $first = Element::factory()->create(['canvas_id' => $this->canvas->id]);
        $second = Element::factory()->create(['canvas_id' => $this->canvas->id]);
        $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $second->id, 'basis_points' => 4000, 'position' => 0]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $first->id, 'basis_points' => 6000, 'position' => 1]);

        $input = (new CalculationInputBuilder)->build($this->canvas, 2026);

        $this->assertSame([
            ['elementId' => $second->id, 'basisPoints' => 4000],
            ['elementId' => $first->id, 'basisPoints' => 6000],
        ], $input->sets[$record->id]);
    }

    public function test_the_number_of_queries_does_not_grow_with_the_data(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            (new CalculationInputBuilder)->build($this->canvas, 2026);

            return count(DB::getQueryLog());
        };
        $before = $count();

        foreach (range(1, 5) as $ignored) {
            $record = $this->record();
            $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id]);
            AllocationLine::factory()->create(['allocation_set_id' => $set->id]);
        }

        $this->assertSame($before, $count());
    }
}
