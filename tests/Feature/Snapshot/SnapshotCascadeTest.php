<?php

namespace Tests\Feature\Snapshot;

use App\Enums\CanvasKind;
use App\Models\AuditEntry;
use App\Models\Canvas;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Canvas\CanvasLifecycle;
use App\Services\Canvas\UnknownAllocationDomain;
use App\Services\Operations\Steps\BlankTemplateStep;
use App\Services\Snapshot\Snapshots;
use Tests\Feature\Allocation\AllocationTestCase;

class SnapshotCascadeTest extends AllocationTestCase
{
    private User $administrator;

    private CanvasLifecycle $lifecycle;

    private Canvas $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = $this->admin();
        $this->actingAs($this->administrator);
        $this->lifecycle = app(CanvasLifecycle::class);
        $this->canvas->update(['name' => 'Operating model 2027']);
        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [$this->target()->id => 10000]);

        $this->other = Canvas::factory()->create(['organisation_id' => $this->canvas->organisation_id, 'name' => 'Operating model 2028']);
        UnknownAllocationDomain::ensure($this->other);

        $this->take($this->canvas, 'Voor de stuurgroep');
        $this->take($this->canvas, 'Na de stuurgroep');
        $this->take($this->other, 'Van het andere canvas');
    }

    private function take(Canvas $canvas, string $name): Snapshot
    {
        return app(Snapshots::class)->take($canvas, $this->fiscalYear(2026), $name);
    }

    public function test_the_deletion_counts_name_the_snapshots_of_that_canvas(): void
    {
        $counts = $this->lifecycle->deletionCounts($this->canvas);

        $this->assertSame(2, $counts['snapshots']);
        $this->assertSame('2 momentopnamen', __('canvas_dialog.count_snapshots', ['count' => $counts['snapshots']]));
        $this->assertSame(1, $this->lifecycle->deletionCounts($this->other)['snapshots']);
    }

    public function test_deleting_a_canvas_removes_its_snapshots_and_leaves_the_others(): void
    {
        $this->lifecycle->delete($this->canvas, 'Operating model 2027');

        $this->assertSame(0, Snapshot::where('canvas_id', $this->canvas->id)->count());
        $this->assertSame(1, Snapshot::where('canvas_id', $this->other->id)->count());
    }

    public function test_the_remaining_audit_entry_counts_the_removed_snapshots(): void
    {
        $this->lifecycle->delete($this->canvas, 'Operating model 2027');

        $this->assertSame([2, null], AuditEntry::where('canvas_id', $this->canvas->id)->sole()->changes['snapshots']);
    }

    public function test_a_copy_of_a_canvas_holds_no_snapshots(): void
    {
        $copy = $this->lifecycle->copy($this->canvas, $this->canvas->organisation()->firstOrFail(), 'Operating model 2029');

        $this->assertSame(0, Snapshot::where('canvas_id', $copy->id)->count());
        $this->assertSame(2, Snapshot::where('canvas_id', $this->canvas->id)->count());
    }

    public function test_a_canvas_created_from_a_template_holds_no_snapshots(): void
    {
        (new BlankTemplateStep)();
        $template = Canvas::where('kind', CanvasKind::Template->value)->firstOrFail();

        $canvas = $this->lifecycle->createFromTemplate($this->canvas->organisation()->firstOrFail(), $template, 'Operating model 2030');

        $this->assertSame(0, Snapshot::where('canvas_id', $canvas->id)->count());
    }
}
