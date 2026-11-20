<?php

namespace Tests\Feature\Allocation;

use App\Enums\ElementKind;
use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\CostRecord;
use App\Models\Element;
use App\Models\FinalisedYear;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Finance\FigureService;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AllocationPageTest extends TestCase
{
    use RefreshDatabase;

    private Canvas $canvas;

    private FiscalYear $year;

    private CostRecord $allocated;

    private CostRecord $open;

    private Element $domain;

    private Element $activity;

    protected function setUp(): void
    {
        parent::setUp();

        app(ReferenceDataStep::class)();
        $organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        $this->year = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);
        $owner = Owner::factory()->create(['organisation_id' => $organisation->id]);
        $this->allocated = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $owner->id, 'name' => 'Licentie']);
        $this->open = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $owner->id, 'name' => 'Beheer']);
        CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $owner->id, 'name' => 'Zonder jaarwaarden']);
        foreach ([$this->allocated, $this->open] as $record) {
            YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 23_000]);
        }
        $areaId = Area::query()->where('key', 'primary_activities')->firstOrFail()->id;
        $this->domain = Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => $areaId, 'kind' => ElementKind::Domain, 'name' => 'Inkoop']);
        $this->activity = Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => $areaId, 'kind' => ElementKind::Activity, 'parent_id' => $this->domain->id, 'name' => 'Offreren']);
        Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => Area::query()->where('key', 'actors')->firstOrFail()->id, 'kind' => ElementKind::Domain, 'name' => 'Leveranciers']);
        UnknownAllocationDomain::ensure($this->canvas);
        $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $this->allocated->id, 'fiscal_year_id' => $this->year->id]);
        AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $this->activity->id, 'basis_points' => 10_000, 'position' => 0]);
    }

    private function admin(): static
    {
        return $this->actingAs(User::factory()->administrator()->create());
    }

    public function test_it_is_for_administrators_only(): void
    {
        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/allocation")->assertForbidden();
    }

    public function test_props_hold_records_with_figures_status_targets_totals_and_the_chosen_set(): void
    {
        $this->admin()->get("/canvases/{$this->canvas->id}/allocation?year=2026&cost_record={$this->allocated->id}")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/Allocation')
                ->where('year', 2026)
                ->where('finalised', false)
                ->has('costRecords', 2)
                ->where('costRecords.0.name', 'Beheer')
                ->where('costRecords.0.status', 'unallocated')
                ->where('costRecords.1.status', 'allocated')
                ->where('costRecords.1.budgetCents', 23_000)
                ->where('costRecords.1.costKind', $this->allocated->cost_kind->value)
                ->where('costRecords.1.owner', Owner::query()->firstOrFail()->name)
                ->has('targets', 1)
                ->where('targets.0.name', 'Inkoop')
                ->where('targets.0.totalCents', 23_000)
                ->where('targets.0.children.0.name', 'Offreren')
                ->where('set.0.elementId', $this->activity->id)
                ->where('set.0.basisPoints', 10_000)
                ->where('figuresAvailable', true)
                ->where('canvasTotalCents', 46_000));
    }

    public function test_area_totals_come_from_the_calculation_and_count_the_unknown_allocation(): void
    {
        $expected = app(FigureService::class)->figures($this->canvas, 2026)->areas;

        $this->admin()->get("/canvases/{$this->canvas->id}/allocation?year=2026")
            ->assertInertia(fn (Assert $page) => $page
                ->where('areaTotals.supporting_activities', $expected['supporting_activities']['budget'])
                ->where('areaTotals.primary_activities', $expected['primary_activities']['budget']));
        $this->assertSame(23_000, $expected['supporting_activities']['budget']);
        $this->assertSame(23_000, $expected['primary_activities']['budget']);
    }

    public function test_the_finalised_state_is_reported_and_no_set_without_a_choice(): void
    {
        FinalisedYear::factory()->create(['canvas_id' => $this->canvas->id, 'fiscal_year_id' => $this->year->id]);

        $this->admin()->get("/canvases/{$this->canvas->id}/allocation?year=2026")
            ->assertInertia(fn (Assert $page) => $page->where('finalised', true)->where('chosen', null)->where('set', []));
    }

    public function test_the_canvas_total_is_never_shown_when_the_closing_check_fails(): void
    {
        $archived = Element::factory()->create(['canvas_id' => $this->canvas->id, 'archived_at' => now()]);
        AllocationLine::query()->update(['element_id' => $archived->id]);

        $this->admin()->get("/canvases/{$this->canvas->id}/allocation?year=2026")
            ->assertInertia(fn (Assert $page) => $page->where('areaTotals', null)->where('figuresAvailable', false)->where('canvasTotalCents', null)->where('targets.0.totalCents', null));
    }
}
