<?php

namespace Tests\Feature\Finance;

use App\Models\AllocationSet;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CostRecordListTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = Organisation::factory()->create();
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2026]);
        $this->actingAs(User::factory()->administrator()->create());
    }

    private function record(string $name, ?int $budget = null, bool $allocated = false): CostRecord
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'name' => $name, 'code' => $name]);

        if ($budget !== null) {
            YearlyFigure::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => $budget, 'forecast_cents' => 1300000]);
        }

        if ($allocated) {
            AllocationSet::factory()->create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id]);
        }

        return $record;
    }

    public function test_it_lists_active_records_with_the_figures_of_the_year(): void
    {
        $this->record('Alfa', 1250000, true);
        $this->record('Beta');
        CostRecord::factory()->create(['organisation_id' => $this->organisation->id, 'name' => 'Oud', 'archived_at' => now()]);

        $this->get("/organisations/{$this->organisation->id}/cost-records?year=2026")->assertInertia(fn (Assert $page) => $page
            ->component('Finance/CostRecords')
            ->where('count', 2)
            ->where('year', 2026)
            ->where('rows.0.name', 'Alfa')
            ->where('rows.0.budget', '€ 12.500,00')
            ->where('rows.0.forecast', '€ 13.000,00')
            ->where('rows.0.allocation', 'allocated')
            ->where('rows.1.budget', null)
            ->where('rows.1.allocation', 'unallocated'));
    }

    public function test_search_and_filters_apply(): void
    {
        $this->record('Alfa', 100, true);
        $this->record('Beta', 100);
        $owner = Owner::factory()->create(['organisation_id' => $this->organisation->id]);
        CostRecord::where('name', 'Beta')->update(['owner_id' => $owner->id]);

        $base = "/organisations/{$this->organisation->id}/cost-records?year=2026";

        $this->get("$base&search=alf")->assertInertia(fn (Assert $page) => $page->where('count', 1));
        $this->get("$base&filters[allocation]=unallocated")->assertInertia(fn (Assert $page) => $page->where('count', 1)->where('rows.0.name', 'Beta'));
        $this->get("$base&filters[owner_id]={$owner->id}")->assertInertia(fn (Assert $page) => $page->where('count', 1)->where('rows.0.name', 'Beta'));
        $this->get("$base&archived=1")->assertInertia(fn (Assert $page) => $page->where('count', 2));
    }

    public function test_a_thousand_records_take_a_constant_number_of_queries(): void
    {
        $owner = Owner::factory()->create(['organisation_id' => $this->organisation->id]);
        $now = now();
        DB::table('cost_records')->insert(array_map(fn ($n) => ['id' => (string) Str::uuid(), 'organisation_id' => $this->organisation->id, 'name' => "R$n", 'cost_kind' => 'other', 'owner_id' => $owner->id, 'created_at' => $now, 'updated_at' => $now], range(1, 1000)));

        DB::enableQueryLog();
        $this->get("/organisations/{$this->organisation->id}/cost-records?year=2026")->assertInertia(fn (Assert $page) => $page->where('count', 1000));

        $this->assertLessThan(20, count(DB::getQueryLog()));
    }

    public function test_the_year_defaults_to_the_latest_and_a_reader_is_refused(): void
    {
        FiscalYear::factory()->create(['organisation_id' => $this->organisation->id, 'year' => 2030]);

        $this->get("/organisations/{$this->organisation->id}/cost-records")->assertInertia(fn (Assert $page) => $page->where('year', 2030)->has('years', 2));
        $this->actingAs(User::factory()->create())->get("/organisations/{$this->organisation->id}/cost-records")->assertForbidden();
    }
}
