<?php

namespace Tests\Feature\Canvas;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Middleware;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViewFiguresTest extends TestCase
{
    use RefreshDatabase;

    private Canvas $canvas;

    private Element $domain;

    private CostRecord $first;

    private CostRecord $second;

    private Owner $owner;

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceDataStep::class)();
        $organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        UnknownAllocationDomain::ensure($this->canvas);
        $year = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);
        $this->owner = Owner::factory()->create(['organisation_id' => $organisation->id]);
        $this->domain = Element::factory()->create(['canvas_id' => $this->canvas->id]);
        $this->first = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $this->owner->id, 'cost_kind' => 'personnel', 'name' => 'Salaris']);
        $this->second = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => Owner::factory()->create(['organisation_id' => $organisation->id])->id, 'cost_kind' => 'project', 'name' => 'Project']);
        YearlyFigure::factory()->create(['cost_record_id' => $this->first->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 100000, 'forecast_cents' => 90000, 'fte_hundredths' => 150]);
        YearlyFigure::factory()->create(['cost_record_id' => $this->second->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 50000, 'forecast_cents' => null]);
        $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $this->first->id, 'fiscal_year_id' => $year->id]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $this->domain->id, 'basis_points' => 10000]);
    }

    public function test_the_financial_overlay_sends_element_and_area_figures_and_side_panel_totals_to_a_reader(): void
    {
        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026")
            ->assertInertia(fn (Assert $page) => $page
                ->where('overlay', 'financial')
                ->where('year', 2026)
                ->where('figuresMessage', null)
                ->where("figures.elements.{$this->domain->id}.total.budget", 100000)
                ->where("figures.elements.{$this->domain->id}.total.forecast", 90000)
                ->where("figures.elements.{$this->domain->id}.shareBp", 6667)
                ->where("figures.elements.{$this->domain->id}.fteHundredths", 150)
                ->where('figures.areas.primary_activities.budget', 100000)
                ->where('figures.canvasTotal.budget', 150000)
                ->where('figures.sidePanel.allocated.count', 1)
                ->where('figures.sidePanel.unknown.budget', 50000)
                ->where('figures.breakdowns.costKind.personnel.budget', 100000)
                ->where('figures.breakdowns.split.undetermined', 150000)
                ->missing('contributions'));
    }

    public function test_no_figures_are_sent_without_the_financial_overlay(): void
    {
        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?year=2026")
            ->assertInertia(fn (Assert $page) => $page->where('overlay', 'none')->where('figures', null));
    }

    public function test_the_filters_narrow_every_figure(): void
    {
        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026&cost_kind=project")
            ->assertInertia(fn (Assert $page) => $page
                ->where('filters.costKind', 'project')
                ->where('figures.canvasTotal.budget', 50000)
                ->where('figures.sidePanel.allocated.count', 0));

        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026&owner={$this->owner->id}")
            ->assertInertia(fn (Assert $page) => $page->where('figures.canvasTotal.budget', 100000));
    }

    public function test_the_contributions_of_one_element_come_only_on_a_partial_reload(): void
    {
        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026&element={$this->domain->id}", ['X-Inertia' => 'true', 'X-Inertia-Partial-Component' => 'Canvas/View', 'X-Inertia-Partial-Data' => 'contributions', 'X-Inertia-Version' => $this->inertiaVersion()])
            ->assertJsonPath('props.contributions.0.name', 'Salaris')
            ->assertJsonPath('props.contributions.0.amountCents', 100000)
            ->assertJsonPath('props.contributions.0.basisPoints', 10000);
    }

    public function test_a_failing_closing_check_sends_no_amounts_and_the_message(): void
    {
        $archived = Element::factory()->create(['canvas_id' => $this->canvas->id, 'archived_at' => now()]);
        AllocationLine::factory()->create(['allocation_set_id' => AllocationSet::firstOrFail()->id, 'element_id' => $archived->id, 'position' => 1]);

        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026")
            ->assertInertia(fn (Assert $page) => $page->where('figures', null)->where('figuresMessage', 'De cijfers sluiten niet. Er worden geen cijfers getoond; de beheerder ziet de melding onder Systeemmeldingen'));
    }

    private function inertiaVersion(): string
    {
        return (string) app(Middleware::class)->version(request());
    }
}
