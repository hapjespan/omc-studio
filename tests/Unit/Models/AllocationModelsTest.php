<?php

namespace Tests\Unit\Models;

use App\Models\AllocationLine;
use App\Models\AllocationSet;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\FinalisedYear;
use App\Models\SystemNotice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AllocationModelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_canvas_has_sets_and_a_set_has_lines_in_position_order(): void
    {
        $canvas = Canvas::factory()->create();
        $set = AllocationSet::factory()->for($canvas)->create();
        AllocationLine::factory()->for($set, 'allocationSet')->create(['position' => 2]);
        $first = AllocationLine::factory()->for($set, 'allocationSet')->create(['position' => 1]);

        $loaded = Canvas::with('allocationSets.lines')->find($canvas->id);

        $this->assertCount(1, $loaded->allocationSets);
        $this->assertSame($first->id, $loaded->allocationSets->sole()->lines->first()->id);
        $this->assertSame([1, 2], $loaded->allocationSets->sole()->lines->pluck('position')->all());
    }

    public function test_finalising_a_year_records_when_and_by_whom_and_is_audited(): void
    {
        $user = User::factory()->create();
        $canvas = Canvas::factory()->create();

        $year = FinalisedYear::factory()->for($canvas)->create(['finalised_by' => $user->id]);

        $this->assertTrue($year->fresh()->finalised_at->isToday());
        $this->assertSame($user->id, $year->fresh()->finalised_by);
        $this->assertSame(1, AuditEntry::where('subject_type', 'finalised_year')->count());
    }

    public function test_a_set_is_audited_with_its_canvas(): void
    {
        $set = AllocationSet::factory()->create();

        $this->assertSame($set->canvas_id, AuditEntry::where('subject_type', 'allocation_set')->sole()->canvas_id);
    }

    public function test_a_system_notice_is_not_audited_and_has_no_update_route(): void
    {
        SystemNotice::factory()->create();

        $this->assertSame(0, AuditEntry::where('subject_type', 'system_notice')->count());
        $this->assertSame(
            [],
            collect(Route::getRoutes()->getRoutes())->filter(fn ($r) => str_contains($r->uri(), 'notices') && array_intersect(['PUT', 'PATCH'], $r->methods()))->all(),
        );
    }

    public function test_a_system_notice_casts_details_and_times(): void
    {
        $notice = SystemNotice::factory()->create(['details' => ['year' => 2026]]);

        $this->assertSame(['year' => 2026], $notice->fresh()->details);
        $this->assertTrue($notice->fresh()->first_occurred_at->isToday());
        $this->assertSame(1, $notice->fresh()->occurrences);
        $this->assertNull($notice->fresh()->seen_at);
    }
}
