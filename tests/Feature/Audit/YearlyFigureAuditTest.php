<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Audit\AuditContext;
use App\Services\Audit\AuditLogQuery;
use App\Services\Audit\AuditTrail;
use App\Services\Canvas\OrganisationService;
use App\Services\Exchange\CostRecordBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class YearlyFigureAuditTest extends TestCase
{
    use RefreshDatabase;

    private Organisation $organisation;

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->administrator()->create());
        $this->organisation = Organisation::factory()->create();
        $this->year = FiscalYear::factory()->create(['organisation_id' => $this->organisation->id]);
    }

    public function test_a_yearly_figure_entry_carries_the_organisation_of_its_cost_record(): void
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);
        YearlyFigure::create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 1000]);

        $entry = AuditEntry::where('subject_type', 'yearly_figure')->firstOrFail();

        $this->assertSame($this->organisation->id, $entry->organisation_id);
    }

    public function test_a_batch_insert_of_yearly_figures_carries_the_organisation(): void
    {
        $batch = new CostRecordBatch(app(AuditContext::class), app(AuditTrail::class), $this->organisation->id);
        $batch->record('licence', null, ['name' => 'Licence', 'cost_kind' => 'other', 'supplier' => null, 'cost_centre' => null, 'owner_id' => Owner::factory()->create(['organisation_id' => $this->organisation->id])->id, 'application_id' => null]);
        $batch->figure('licence', $this->year->id, ['budget_cents' => 500]);
        $batch->flush();

        $entry = AuditEntry::where('subject_type', 'yearly_figure')->firstOrFail();

        $this->assertSame($this->organisation->id, $entry->organisation_id);
    }

    public function test_the_organisation_filter_shows_the_yearly_figure_entries_and_deleting_the_organisation_removes_them(): void
    {
        $record = CostRecord::factory()->create(['organisation_id' => $this->organisation->id]);
        $figure = YearlyFigure::create(['cost_record_id' => $record->id, 'fiscal_year_id' => $this->year->id, 'budget_cents' => 1000]);

        $shown = app(AuditLogQuery::class)->paginate(['organisation_id' => $this->organisation->id])->getCollection();
        $this->assertTrue($shown->contains(fn (AuditEntry $entry) => $entry->subject_type === 'yearly_figure'));

        $figure->delete();
        $record->delete();
        $this->year->delete();
        app(OrganisationService::class)->delete($this->organisation);

        $this->assertSame(0, AuditEntry::where('subject_type', 'yearly_figure')->count());
    }
}
