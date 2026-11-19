<?php

namespace Tests\Feature\Exchange;

use App\Models\AuditEntry;
use App\Models\CostRecord;
use App\Models\FiscalYear;
use App\Models\Organisation;
use App\Models\User;
use App\Models\YearlyFigure;
use App\Services\Audit\AuditTrail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditParityTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_bulk_audit_row_matches_the_observer_row_for_the_same_change(): void
    {
        $this->actingAs(User::factory()->administrator()->create());
        $organisation = Organisation::factory()->create();
        $year = FiscalYear::factory()->create(['organisation_id' => $organisation->id]);
        $record = CostRecord::factory()->create(['organisation_id' => $organisation->id]);
        $attributes = ['cost_record_id' => $record->id, 'fiscal_year_id' => $year->id, 'budget_cents' => 1000];
        $figure = YearlyFigure::create($attributes);
        $observed = AuditEntry::where('subject_type', 'yearly_figure')->firstOrFail();

        app(AuditTrail::class)->recordMany('yearly_figure', 'created', null, [
            ['bulk', 'bulk', array_map(fn ($value) => [null, $value], $attributes)],
        ]);
        $bulk = AuditEntry::where('subject_id', 'bulk')->firstOrFail();

        $keys = ['actor_type', 'actor_id', 'action', 'subject_type', 'organisation_id', 'canvas_id'];
        $this->assertSame($observed->only($keys), $bulk->only($keys));
        $this->assertNull($bulk->organisation_id);
        $this->assertSame(array_keys($observed->changes), array_keys($bulk->changes));
        $this->assertSame((string) $figure->id, $observed->subject_id);
    }
}
