<?php

namespace Tests\Feature\Allocation;

use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\YearlyFigure;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;

class CarryOverRefusalTest extends AllocationTestCase
{
    /** @return TestResponse<Response> */
    private function carry(bool $withFigures = true): TestResponse
    {
        return $this->actingAs($this->admin())->postJson("/canvases/{$this->canvas->id}/years/2027/carry-over", ['source_year' => 2026, 'with_figures' => $withFigures]);
    }

    private function seed2026(): CostRecord
    {
        $record = $this->costRecord('Licentie', 2026);
        $this->fiscalYear(2027);
        $this->allocate($record, 2026, [$this->target()->id => 10000]);

        return $record;
    }

    public function test_the_run_reports_counts_names_and_reasons(): void
    {
        $this->seed2026();
        $skipped = $this->costRecord('Hosting', 2026);
        YearlyFigure::factory()->create(['cost_record_id' => $skipped->id, 'fiscal_year_id' => $this->fiscalYear(2027)->id]);

        $this->carry()->assertSessionHas('carry_over.summary', 'Overgenomen: 1, overgeslagen: 1, handmatig toewijzen: 0')
            ->assertSessionHas('carry_over.copied.0.name', 'Licentie')
            ->assertSessionHas('carry_over.skipped.0.name', 'Hosting')
            ->assertSessionHas('carry_over.skipped.0.reason', 'doeljaar niet leeg');
    }

    public function test_a_target_year_finalised_in_this_canvas_refuses_the_whole_run(): void
    {
        $record = $this->seed2026();
        $this->finalise(2027);

        $this->carry(false)->assertUnprocessable()
            ->assertJsonPath('errors.rule.0', "2027 is definitief in canvas {$this->canvas->name}. Heropen het jaar eerst");
        $this->assertSame(0, AllocationSet::where('cost_record_id', $record->id)->where('fiscal_year_id', $this->fiscalYear(2027)->id)->count());
    }

    public function test_with_the_option_on_a_year_finalised_in_another_canvas_refuses_and_with_it_off_it_does_not(): void
    {
        $this->seed2026();
        $other = Canvas::factory()->create(['organisation_id' => $this->canvas->organisation_id, 'name' => 'Tweede']);
        $this->finalise(2027, $other->id);

        $this->carry()->assertUnprocessable()->assertJsonPath('errors.rule.0', '2027 is definitief in canvas Tweede. Heropen het jaar eerst');
        $this->carry(false)->assertSessionHas('carry_over');
    }

    public function test_an_archived_canvas_refuses(): void
    {
        $this->seed2026();
        $this->canvas->update(['archived_at' => now()]);

        $this->carry()->assertUnprocessable();
    }

    public function test_only_an_administrator_may_run_it(): void
    {
        $this->postJson("/canvases/{$this->canvas->id}/years/2027/carry-over", [])->assertUnauthorized();
    }
}
