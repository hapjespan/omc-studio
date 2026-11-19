<?php

namespace Tests\Feature\Finance;

use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResolveFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function years(int ...$years): void
    {
        foreach ($years as $year) {
            FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => $year]);
        }
    }

    public function test_the_year_parameter_wins_when_the_organisation_has_that_year(): void
    {
        $this->years(2025, 2026, 2030);

        $this->get("/organisations/{$this->organisation->id}/cost-records?year=2025")->assertInertia(fn (Assert $page) => $page->where('fiscalYears.selected', 2025)->has('fiscalYears.options', 3));
    }

    public function test_the_default_is_the_current_calendar_year_when_it_exists_else_the_latest(): void
    {
        $this->years(2025, (int) now()->year, 2099);
        $this->get("/organisations/{$this->organisation->id}/cost-records")->assertInertia(fn (Assert $page) => $page->where('fiscalYears.selected', (int) now()->year));

        FiscalYear::where('year', (int) now()->year)->delete();
        $this->get("/organisations/{$this->organisation->id}/cost-records?year=1")->assertInertia(fn (Assert $page) => $page->where('fiscalYears.selected', 2099));
    }

    public function test_an_organisation_without_years_selects_nothing(): void
    {
        $this->get("/organisations/{$this->organisation->id}/cost-records")->assertInertia(fn (Assert $page) => $page->where('fiscalYears.selected', null)->has('fiscalYears.options', 0));
    }

    public function test_a_canvas_adds_open_and_final_state_per_year(): void
    {
        $this->years(2025, 2026);
        $canvas = Canvas::factory()->create(['organisation_id' => $this->organisation->id]);
        FinalisedYear::factory()->create(['canvas_id' => $canvas->id, 'fiscal_year_id' => FiscalYear::where('year', 2026)->value('id')]);
        Route::middleware('web')->get('/probe/{canvas}', fn (Canvas $canvas) => Inertia::render('Home'));

        $this->get("/probe/{$canvas->id}?year=2026")->assertInertia(fn (Assert $page) => $page
            ->where('fiscalYears.selected', 2026)
            ->where('fiscalYears.options.0', ['year' => 2025, 'state' => 'open'])
            ->where('fiscalYears.options.1', ['year' => 2026, 'state' => 'final']));
    }
}
