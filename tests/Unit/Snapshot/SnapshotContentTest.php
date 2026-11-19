<?php

namespace Tests\Unit\Snapshot;

use App\Models\Snapshot;
use App\Services\Snapshot\SnapshotContent;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Tests\Feature\Allocation\AllocationTestCase;

class SnapshotContentTest extends AllocationTestCase
{
    private function assembler(): SnapshotContent
    {
        return app(SnapshotContent::class);
    }

    private function stored(): Snapshot
    {
        $encoded = $this->assembler()->encode($this->assembler()->build($this->canvas, $this->fiscalYear(2026)));

        return Snapshot::create([
            'canvas_id' => $this->canvas->id,
            'name' => 'Versie 1',
            'fiscal_year' => 2026,
            'created_at' => now(),
            'schema_version' => SnapshotContent::SCHEMA_VERSION,
            'content' => $encoded,
            'checksum' => $this->assembler()->checksum($encoded),
        ]);
    }

    public function test_the_content_carries_the_structure_the_year_and_the_figures(): void
    {
        $target = $this->target('Offreren');
        $this->allocate($this->costRecord('Licentie', 2026, 50_000), 2026, [$target->id => 10000]);
        $this->costRecord('Andere jaar', 2027);
        $this->domain('primary_activities', 'Gearchiveerd')->update(['archived_at' => now()]);

        $content = $this->assembler()->build($this->canvas, $this->fiscalYear(2026));

        $this->assertSame(SnapshotContent::SCHEMA_VERSION, $content['schema_version']);
        $this->assertSame($this->canvas->name, $content['canvas_name']);
        $this->assertSame(['Licentie'], array_column($content['cost_records'], 'name'));
        $this->assertSame(50_000, $content['cost_records'][0]['yearly_figure']['budget_cents']);
        $this->assertNotContains('Gearchiveerd', array_column($content['elements'], 'name'));
        $this->assertContains('Offreren', array_column($content['elements'], 'name'));
        $this->assertSame(10000, $content['allocation_sets'][0]['lines'][0]['basis_points']);
        $this->assertSame(50_000, $content['figures']['canvasTotal']['budget']);
    }

    public function test_identical_input_gives_identical_content_and_a_sha256_checksum(): void
    {
        $this->allocate($this->costRecord('Licentie', 2026), 2026, [$this->target()->id => 10000]);

        $first = $this->assembler()->encode($this->assembler()->build($this->canvas, $this->fiscalYear(2026)));
        $second = $this->assembler()->encode($this->assembler()->build($this->canvas, $this->fiscalYear(2026)));

        $this->assertSame($first, $second);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $this->assembler()->checksum($first));
        $this->assertNotSame($this->assembler()->checksum($first), $this->assembler()->checksum($first.' '));
    }

    public function test_a_stored_snapshot_refuses_a_change_to_its_content(): void
    {
        $snapshot = $this->stored();

        $this->expectException(InvalidArgumentException::class);

        $snapshot->update(['content' => '{"schema_version":1}', 'checksum' => str_repeat('a', 64)]);
    }

    public function test_a_stored_snapshot_keeps_name_remark_and_archive_editable(): void
    {
        $snapshot = $this->stored();
        $encoded = (string) $snapshot->content;

        $snapshot->update(['name' => 'Versie 2', 'remark' => 'Toelichting', 'archived_at' => now()]);

        $this->assertSame(['Versie 2', 'Toelichting', $encoded], [$snapshot->fresh()->name, $snapshot->fresh()->remark, (string) $snapshot->fresh()->content]);
        $this->assertNotNull($snapshot->fresh()->archived_at);
    }

    public function test_the_snapshots_table_has_the_columns_and_the_versions_index(): void
    {
        $this->assertSame(
            ['id', 'canvas_id', 'name', 'fiscal_year', 'remark', 'archived_at', 'created_by', 'created_at', 'imported', 'schema_version', 'content', 'checksum'],
            Schema::getColumnListing('snapshots'),
        );
        $this->assertContains(['canvas_id', 'archived_at', 'created_at'], array_column(Schema::getIndexes('snapshots'), 'columns'));
        $this->assertFalse((new Snapshot)->usesTimestamps());
    }
}
