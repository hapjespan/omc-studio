<?php

namespace Tests\Feature\Snapshot;

use App\Models\AuditEntry;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Snapshot\Snapshots;
use Tests\Feature\Allocation\AllocationTestCase;

class UpdateSnapshotTest extends AllocationTestCase
{
    private User $administrator;

    private Snapshot $snapshot;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->admin();
        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [$this->target()->id => 10000]);
        $this->actingAs($this->administrator);
        $this->snapshot = app(Snapshots::class)->take($this->canvas, $this->fiscalYear(2026), 'Eerste', null);
    }

    public function test_name_and_remark_change_and_each_change_is_audited(): void
    {
        $this->patchJson("/snapshots/{$this->snapshot->id}", ['name' => 'Tweede', 'remark' => 'Na overleg'])->assertRedirect();

        $this->assertSame('Tweede', $this->snapshot->fresh()->name);
        $this->assertSame('Na overleg', $this->snapshot->fresh()->remark);
        $entry = AuditEntry::where('subject_type', 'snapshot')->where('action', 'changed')->sole();
        $this->assertArrayNotHasKey('content', $entry->changes);
    }

    public function test_a_request_naming_content_or_any_other_field_is_refused_and_nothing_changes(): void
    {
        $stored = $this->snapshot->content;

        foreach (['content', 'checksum', 'fiscal_year', 'canvas_id', 'archived_at'] as $field) {
            $this->patchJson("/snapshots/{$this->snapshot->id}", ['name' => 'Gehackt', $field => 'x'])->assertUnprocessable()->assertJsonValidationErrors('rule');
        }

        $fresh = $this->snapshot->fresh();
        $this->assertSame($stored, $fresh->content);
        $this->assertSame('Eerste', $fresh->name);
    }

    public function test_an_empty_name_is_refused(): void
    {
        $this->patchJson("/snapshots/{$this->snapshot->id}", ['name' => ' '])->assertUnprocessable()->assertJsonValidationErrors('name');
    }

    public function test_archive_and_restore_stamp_the_snapshot_with_their_own_audit_action(): void
    {
        $this->postJson("/snapshots/{$this->snapshot->id}/archive")->assertRedirect();
        $this->assertNotNull($this->snapshot->fresh()->archived_at);

        $this->postJson("/snapshots/{$this->snapshot->id}/restore")->assertRedirect();
        $this->assertNull($this->snapshot->fresh()->archived_at);

        $this->assertSame(['archived', 'restored'], AuditEntry::where('subject_type', 'snapshot')->whereIn('action', ['archived', 'restored'])->orderBy('id')->pluck('action')->all());
    }

    public function test_delete_needs_confirmation_and_then_removes_the_snapshot_with_an_audit_entry(): void
    {
        $this->deleteJson("/snapshots/{$this->snapshot->id}")->assertUnprocessable();
        $this->assertSame(1, Snapshot::count());

        $this->deleteJson("/snapshots/{$this->snapshot->id}", ['confirm' => true])->assertRedirect();

        $this->assertSame(0, Snapshot::count());
        $this->assertSame(1, AuditEntry::where('subject_type', 'snapshot')->where('action', 'deleted')->count());
    }

    public function test_a_reader_is_refused_on_every_route(): void
    {
        $reader = User::factory()->create();
        $this->actingAs($reader);

        $this->patchJson("/snapshots/{$this->snapshot->id}", ['name' => 'X'])->assertForbidden();
        $this->postJson("/snapshots/{$this->snapshot->id}/archive")->assertForbidden();
        $this->postJson("/snapshots/{$this->snapshot->id}/restore")->assertForbidden();
        $this->deleteJson("/snapshots/{$this->snapshot->id}", ['confirm' => true])->assertForbidden();
    }
}
