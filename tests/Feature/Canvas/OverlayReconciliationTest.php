<?php

namespace Tests\Feature\Canvas;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\Area;
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
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OverlayReconciliationTest extends TestCase
{
    use RefreshDatabase;

    private const ACTIVITY_AREAS = ['steering', 'governing', 'primary_activities', 'supporting_activities'];

    private Canvas $canvas;

    private Owner $ict;

    /** @var array<int, int> */
    private array $budgetByOwner = [];

    protected function setUp(): void
    {
        parent::setUp();
        app(ReferenceDataStep::class)();
        $organisation = Organisation::factory()->create();
        $this->canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        UnknownAllocationDomain::ensure($this->canvas);
        $year = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);
        $this->ict = Owner::factory()->create(['organisation_id' => $organisation->id]);
        $hr = Owner::factory()->create(['organisation_id' => $organisation->id]);
        $domains = array_map(fn (string $area) => Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => Area::where('key', $area)->value('id')]), self::ACTIVITY_AREAS);

        $records = [
            [$this->ict, 100001, 3, [$domains[0]->id, $domains[1]->id, $domains[2]->id]],
            [$this->ict, 33333, 2, [$domains[2]->id, $domains[3]->id]],
            [$hr, 5555555, 1, [$domains[3]->id]],
            [$hr, 99999, 3, [$domains[0]->id, $domains[1]->id, $domains[3]->id]],
            [$hr, 250001, 0, []],
        ];
        foreach ($records as [$owner, $budget, , $targets]) {
            $record = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $owner->id]);
            YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $year->id, 'budget_cents' => $budget, 'forecast_cents' => null]);
            $this->budgetByOwner[$owner->id] = ($this->budgetByOwner[$owner->id] ?? 0) + $budget;
            if ($targets === []) {
                continue;
            }
            $set = AllocationSet::factory()->create(['canvas_id' => $this->canvas->id, 'cost_record_id' => $record->id, 'fiscal_year_id' => $year->id]);
            $weights = match (count($targets)) {
                1 => [10000],
                2 => [6667, 3333],
                default => [3334, 3333, 3333],
            };
            foreach ($targets as $position => $element) {
                AllocationLine::factory()->create(['allocation_set_id' => $set->id, 'element_id' => $element, 'basis_points' => $weights[$position], 'position' => $position]);
            }
        }
    }

    public function test_the_hand_summed_budgets_equal_the_card_the_canvas_total_the_area_totals_and_the_whole_euro_figure(): void
    {
        $hand = array_sum($this->budgetByOwner);
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get("/canvases/{$this->canvas->id}/cost-records?year=2026")
            ->assertInertia(fn (Assert $page) => $page->where('cards.budgetCents', $hand));

        $this->actingAs($admin)->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026")
            ->assertInertia(function (Assert $page) use ($hand) {
                $page->where('figures.canvasTotal.budget', $hand);
                $areas = array_sum(array_map(fn (string $area) => $page->toArray()['props']['figures']['areas'][$area]['budget'], self::ACTIVITY_AREAS));
                $this->assertSame($hand, $areas);
                $closing = $page->toArray()['props']['figures']['sidePanel'];
                $this->assertSame($hand, $closing['allocated']['budget'] + $closing['unknown']['budget']);
            });
    }

    public function test_with_a_filter_the_same_holds_for_the_subset(): void
    {
        $hand = $this->budgetByOwner[$this->ict->id];

        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view?overlay=financial&year=2026&owner={$this->ict->id}")
            ->assertInertia(function (Assert $page) use ($hand) {
                $figures = $page->toArray()['props']['figures'];
                $page->where('figures.canvasTotal.budget', $hand);
                $this->assertSame($hand, array_sum(array_map(fn (string $area) => $figures['areas'][$area]['budget'], self::ACTIVITY_AREAS)));
                $this->assertSame($hand, $figures['sidePanel']['allocated']['budget'] + $figures['sidePanel']['unknown']['budget']);
                $this->assertSame($hand, array_sum(array_column($figures['breakdowns']['costKind'], 'budget')));
            });
    }
}
