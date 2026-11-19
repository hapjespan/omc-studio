<?php

namespace Tests\Feature\Finance;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Finance\YearlyFigures;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Response;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class YearlyFiguresTest extends TestCase
{
    use RefreshDatabase;

    private const AMOUNT_REFUSAL = 'Budget: vul een bedrag in van € 0,00 tot € 999.999.999,99, met een komma voor de centen';

    private Organisation $organisation;

    private CostRecord $record;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->record = CostRecord::factory()->create([
            'organisation_id' => $this->organisation->id,
            'owner_id' => Owner::factory()->create(['organisation_id' => $this->organisation->id])->id,
        ]);
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'budget' => '12.500,00',
            'forecast' => '13.000',
            'run' => '60',
            'change' => '30',
            'fte' => '1,5',
            'reference_date' => '2026-03-31',
            'remark' => 'Toelichting',
        ], $overrides);
    }

    /** @param array<string, mixed> $overrides
     * @return TestResponse<Response> */
    private function save(array $overrides = [], int $year = 2026): TestResponse
    {
        return $this->admin()->put("/cost-records/{$this->record->id}/years/{$year}", $this->payload($overrides));
    }

    private function figures(): YearlyFigures
    {
        return app(YearlyFigures::class);
    }

    public function test_yearly_figures_are_saved_with_every_field_and_the_undetermined_share_follows(): void
    {
        $this->save()->assertSessionHasNoErrors();

        $figure = YearlyFigure::firstOrFail();
        $this->assertSame(1_250_000, $figure->budget_cents);
        $this->assertSame(1_300_000, $figure->forecast_cents);
        $this->assertSame(6000, $figure->run_bp);
        $this->assertSame(3000, $figure->change_bp);
        $this->assertSame(150, $figure->fte_hundredths);
        $this->assertSame('2026-03-31', $figure->reference_date?->toDateString());
        $this->assertSame('Toelichting', $figure->remark);
        $this->assertSame(1000, $this->figures()->undeterminedShare($figure));
    }

    public function test_a_second_save_updates_the_single_row_of_that_year(): void
    {
        $this->save()->assertSessionHasNoErrors();
        $this->save(['budget' => '100', 'forecast' => '', 'run' => '', 'change' => '', 'fte' => '', 'reference_date' => '', 'remark' => ''])->assertSessionHasNoErrors();

        $figure = YearlyFigure::firstOrFail();
        $this->assertSame(1, YearlyFigure::count());
        $this->assertSame(10_000, $figure->budget_cents);
        $this->assertNull($figure->forecast_cents);
        $this->assertNull($figure->run_bp);
        $this->assertNull($figure->fte_hundredths);
        $this->assertNull($figure->reference_date);
        $this->assertSame(10_000, $this->figures()->undeterminedShare($figure));
    }

    public function test_run_and_change_count_at_most_one_hundred_percent_together(): void
    {
        $this->save(['run' => '70', 'change' => '40'])->assertSessionHasErrors(['rule' => 'Run en change: samen maximaal 100%']);
        $this->assertSame(0, YearlyFigure::count());

        $this->save(['run' => '70', 'change' => '30'])->assertSessionHasNoErrors();
    }

    public function test_budget_is_required_and_amounts_follow_the_amount_rule(): void
    {
        foreach (['', '12.50', '-5', '1000000000'] as $budget) {
            $this->save(['budget' => $budget])->assertSessionHasErrors(['budget' => self::AMOUNT_REFUSAL]);
        }

        $this->save(['forecast' => '12.50'])->assertSessionHasErrors('forecast');
        $this->save(['run' => '101'])->assertSessionHasErrors('run');
        $this->assertSame(0, YearlyFigure::count());
    }

    public function test_fte_runs_from_zero_to_nine_thousand_nine_hundred_ninety_nine_comma_ninety_nine(): void
    {
        $this->save(['fte' => '10.000'])->assertSessionHasErrors('fte');
        $this->assertSame(0, YearlyFigure::count());

        $this->save(['fte' => '9.999,99'])->assertSessionHasNoErrors();
        $this->assertSame(999_999, YearlyFigure::firstOrFail()->fte_hundredths);
    }

    public function test_a_year_finalised_in_any_canvas_of_the_organisation_refuses_saving_and_deleting(): void
    {
        $figure = YearlyFigure::factory()->create(['cost_record_id' => $this->record->id, 'fiscal_year_id' => $this->year->id]);
        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Canvas een']);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => $this->year->id]);

        $refusal = ['rule' => '2026 is definitief in canvas Canvas een. Heropen het jaar eerst'];

        $this->save()->assertSessionHasErrors($refusal);
        $this->admin()->delete("/cost-records/{$this->record->id}/years/2026")->assertSessionHasErrors($refusal);

        $this->assertSame($figure->budget_cents, YearlyFigure::firstOrFail()->budget_cents);
    }

    public function test_deleting_yearly_figures_removes_the_allocation_sets_of_that_year_in_every_canvas(): void
    {
        $figure = YearlyFigure::factory()->create(['cost_record_id' => $this->record->id, 'fiscal_year_id' => $this->year->id]);
        $other = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2025]);

        foreach (range(1, 2) as $number) {
            $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
            $set = AllocationSet::factory()->create(['canvas_id' => $canvas->id, 'cost_record_id' => $this->record->id, 'fiscal_year_id' => $this->year->id]);
            AllocationLine::factory()->create(['allocation_set_id' => $set->id]);
            AllocationSet::factory()->create(['canvas_id' => $canvas->id, 'cost_record_id' => $this->record->id, 'fiscal_year_id' => $other->id]);
        }

        $this->assertSame(2, $this->figures()->setCount($figure));
        $this->assertSame('Verwijdert ook 2 toewijzingssets', trans_choice('yearly_figures.delete_consequence', 2));

        $this->admin()->delete("/cost-records/{$this->record->id}/years/2026")->assertSessionHasNoErrors();

        $this->assertSame(0, YearlyFigure::count());
        $this->assertSame(0, AllocationLine::count());
        $this->assertSame([$other->id, $other->id], AllocationSet::pluck('fiscal_year_id')->all());
        $this->assertSame(2, AuditEntry::where('action', 'deleted')->where('subject_type', 'allocation_set')->count());
    }

    public function test_a_year_outside_the_organisation_is_not_found(): void
    {
        FiscalYear::factory()->create(['year' => 2027]);

        $this->save([], 2027)->assertNotFound();
        $this->save([], 2030)->assertNotFound();
        $this->admin()->delete("/cost-records/{$this->record->id}/years/2026")->assertNotFound();
    }

    public function test_an_archived_organisation_is_refused(): void
    {
        $this->organisation->update(['archived_at' => now()]);

        $this->save()->assertSessionHasErrors('rule');
        $this->assertSame(0, YearlyFigure::count());
    }

    public function test_a_reader_and_a_guest_are_refused(): void
    {
        $this->actingAs(User::factory()->create())->put("/cost-records/{$this->record->id}/years/2026", $this->payload())->assertForbidden();
        auth()->logout();
        $this->delete("/cost-records/{$this->record->id}/years/2026")->assertRedirect('/sign-in');
    }
}
