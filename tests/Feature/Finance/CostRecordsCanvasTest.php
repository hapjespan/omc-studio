<?php

namespace Tests\Feature\Finance;

use App\Models\AllocationSet;
use App\Models\Canvas;
use App\Models\CostRecord;
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

class CostRecordsCanvasTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_canvas_variant_reports_status_per_canvas_and_the_cards_and_refuses_readers(): void
    {
        app(ReferenceDataStep::class)();
        $organisation = Organisation::factory()->create();
        $canvas = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        $other = Canvas::factory()->create(['organisation_id' => $organisation->id]);
        UnknownAllocationDomain::ensure($canvas);
        $year = FiscalYear::factory()->create(['organisation_id' => $organisation->id, 'year' => 2026]);
        $owner = Owner::factory()->create(['organisation_id' => $organisation->id]);
        $records = [];
        foreach (['A', 'B'] as $name) {
            $records[$name] = CostRecord::factory()->create(['organisation_id' => $organisation->id, 'owner_id' => $owner->id, 'name' => $name]);
            YearlyFigure::factory()->create(['cost_record_id' => $records[$name]->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 1000]);
        }
        AllocationSet::factory()->create(['canvas_id' => $other->id, 'cost_record_id' => $records['A']->id, 'fiscal_year_id' => $year->id]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/cost-records?year=2026")
            ->assertInertia(fn (Assert $page) => $page
                ->component('Finance/CostRecords')
                ->where('canvas.id', $canvas->id)
                ->where('rows.0.allocation', 'unallocated')
                ->where('rows.1.allocation', 'unallocated')
                ->where('cards.budgetCents', 2000)
                ->where('cards.unknown.count', 2)
                ->where('cards.allocated.count', 0));

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}/cost-records")->assertForbidden();
    }
}
