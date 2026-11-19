<?php

namespace Tests\Feature\Audit;

use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Services\Audit\AuditRemoval;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuditRemovalTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_entries_of_a_canvas_make_way_for_one_entry_with_its_name_and_counts(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Noord 2027']);
        $other = Canvas::factory()->create();
        $canvas->update(['name' => 'Noord 2028']);
        $elsewhere = AuditEntry::where('canvas_id', $other->id)->count();

        app(AuditRemoval::class)->purge($canvas, ['elements' => 7]);

        $entries = AuditEntry::where('canvas_id', $canvas->id)->get();

        $this->assertCount(1, $entries);
        $this->assertSame('deleted', $entries[0]->action);
        $this->assertSame('canvas', $entries[0]->subject_type);
        $this->assertSame(['name' => ['Noord 2028', null], 'elements' => [7, null], 'audit_entries' => [2, null]], $entries[0]->changes);
        $this->assertSame($elsewhere, AuditEntry::where('canvas_id', $other->id)->count());
    }
}
