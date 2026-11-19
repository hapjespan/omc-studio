<?php

namespace Tests\Feature\Snapshot;

use App\Models\Snapshot;
use App\Models\SystemNotice;
use App\Services\Snapshot\Snapshots;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Allocation\AllocationTestCase;

class DamagedSnapshotTest extends AllocationTestCase
{
    private Snapshots $snapshots;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin());
        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [$this->target()->id => 10000]);
        $this->snapshots = app(Snapshots::class);
    }

    private function stored(string $name = 'Voor de stuurgroep'): Snapshot
    {
        $taken = $this->snapshots->take($this->canvas, $this->fiscalYear(2026), $name);

        return Snapshot::findOrFail($taken->getKey());
    }

    private function damage(Snapshot $snapshot): Snapshot
    {
        DB::table('snapshots')->where('id', $snapshot->getKey())
            ->update(['content' => str_replace('"budget":10000', '"budget":99999', $snapshot->content)]);

        return Snapshot::findOrFail($snapshot->getKey());
    }

    public function test_an_intact_snapshot_returns_its_content_and_records_no_notice(): void
    {
        $content = $this->snapshots->read($this->stored());

        $this->assertSame($this->canvas->name, $content['canvas_name']);
        $this->assertSame(10_000, $content['figures']['canvasTotal']['budget']);
        $this->assertSame(0, SystemNotice::count());
    }

    public function test_content_altered_in_the_database_gives_no_content_and_a_notice(): void
    {
        $snapshot = $this->damage($this->stored());

        $this->assertNull($this->snapshots->read($snapshot));

        $notice = SystemNotice::sole();
        $this->assertSame('snapshot_damaged', $notice->kind);
        $this->assertSame($this->canvas->id, $notice->canvas_id);
        $this->assertSame('Voor de stuurgroep', $notice->details['snapshot']);
        $this->assertSame(1, $notice->occurrences);
    }

    public function test_a_second_read_raises_the_count_instead_of_adding_a_notice(): void
    {
        $snapshot = $this->damage($this->stored());

        $this->assertNull($this->snapshots->read($snapshot));
        $this->assertNull($this->snapshots->read($snapshot));

        $this->assertSame(1, SystemNotice::count());
        $this->assertSame(2, SystemNotice::sole()->occurrences);
    }

    public function test_a_second_damaged_snapshot_of_the_same_canvas_gets_its_own_notice(): void
    {
        $this->damage($this->stored('Eerste'));
        $this->damage($this->stored('Tweede'));

        foreach (Snapshot::orderBy('id')->get() as $snapshot) {
            $this->assertNull($this->snapshots->read($snapshot));
        }

        $this->assertSame(['Eerste', 'Tweede'], SystemNotice::orderBy('id')->get()->map(fn (SystemNotice $notice) => $notice->details['snapshot'])->all());
    }

    public function test_the_caller_shows_the_dutch_message_of_the_damaged_snapshot(): void
    {
        $this->assertSame('Deze momentopname is beschadigd en wordt niet getoond', __(Snapshots::DAMAGED_MESSAGE));
    }

    public function test_two_damaged_snapshots_with_the_same_name_get_their_own_notice(): void
    {
        $this->damage($this->stored('Gelijk'));
        $this->damage($this->stored('Gelijk'));

        foreach (Snapshot::orderBy('id')->get() as $snapshot) {
            $this->assertNull($this->snapshots->read($snapshot));
        }

        $this->assertSame(2, SystemNotice::count());
    }
}
