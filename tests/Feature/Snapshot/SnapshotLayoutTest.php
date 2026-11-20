<?php

namespace Tests\Feature\Snapshot;

use App\Models\Element;
use App\Models\Layout;
use App\Models\Snapshot;
use App\Models\SystemNotice;
use App\Services\Snapshot\SnapshotContent;
use App\Services\Snapshot\Snapshots;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Feature\Allocation\AllocationTestCase;

class SnapshotLayoutTest extends AllocationTestCase
{
    private Element $domain;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs($this->admin());
        $this->allocate($this->costRecord('Licentie', 2026, 10_000), 2026, [$this->target()->id => 10000]);
        $this->domain = $this->domain('primary_activities', 'Inkoop');

        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->domain->id, 'x' => 40, 'y' => 220, 'width' => 300, 'height' => 120, 'z' => 2, 'style' => ['fill' => '#F6E6CC']]);
        Layout::create(['canvas_id' => $this->canvas->id, 'area_id' => $this->area('primary_activities')->id, 'x' => 560, 'y' => 400, 'width' => 1100, 'height' => 380, 'z' => 0, 'style' => []]);
    }

    private function content(): SnapshotContent
    {
        return app(SnapshotContent::class);
    }

    private function take(string $name = 'Voor de stuurgroep'): Snapshot
    {
        return app(Snapshots::class)->take($this->canvas, $this->fiscalYear(2026), $name);
    }

    private function stored(int $version): Snapshot
    {
        $content = [...$this->content()->build($this->canvas, $this->fiscalYear(2026)), 'schema_version' => $version];
        unset($content['layout'], $content['artboard']);
        $encoded = $this->content()->encode($content);

        return Snapshot::create([
            'canvas_id' => $this->canvas->id,
            'name' => 'Oude versie',
            'fiscal_year' => 2026,
            'created_at' => now(),
            'schema_version' => $version,
            'content' => $encoded,
            'checksum' => $this->content()->checksum($encoded),
        ]);
    }

    public function test_the_content_carries_the_layout_the_artboard_and_schema_version_two(): void
    {
        $snapshot = $this->take();
        $content = json_decode((string) $snapshot->content, true);

        $this->assertSame(2, $content['schema_version']);
        $this->assertSame(2, $snapshot->schema_version);
        $this->assertSame(['mode' => 'manual', 'x' => 40, 'y' => 220, 'width' => 300, 'height' => 120, 'z' => 2, 'style' => ['fill' => '#F6E6CC']], $content['layout'][$this->domain->id]);
        $this->assertSame(['mode' => 'manual', 'x' => 560, 'y' => 400, 'width' => 1100, 'height' => 380, 'z' => 0, 'style' => []], $content['layout']['primary_activities']);
        $this->assertSame(['width' => 1680, 'height' => 1188], $content['artboard']);
        $this->assertSame($this->content()->checksum((string) $snapshot->content), $snapshot->checksum);
    }

    public function test_the_stored_content_survives_a_decode_and_encode_without_a_byte_of_difference(): void
    {
        $snapshot = $this->take();

        $this->assertSame((string) $snapshot->content, $this->content()->encode(json_decode((string) $snapshot->content, true)));
    }

    public function test_an_element_without_a_row_is_absent_from_the_layout(): void
    {
        $other = $this->domain('primary_activities', 'Verkoop');

        $this->assertArrayNotHasKey($other->id, json_decode((string) $this->take()->content, true)['layout']);
    }

    public function test_a_later_change_of_the_live_layout_leaves_the_snapshot_untouched(): void
    {
        $snapshot = $this->take();

        Layout::query()->where('element_id', $this->domain->id)->update(['x' => 900]);
        Layout::query()->where('area_id', $this->area('primary_activities')->id)->delete();

        $content = app(Snapshots::class)->read(Snapshot::findOrFail($snapshot->getKey()));

        $this->assertSame(40, $content['layout'][$this->domain->id]['x']);
        $this->assertSame(1100, $content['layout']['primary_activities']['width']);
    }

    public function test_a_version_one_snapshot_reads_upgraded_with_an_empty_layout_and_keeps_its_bytes(): void
    {
        $snapshot = $this->stored(1);
        $encoded = (string) $snapshot->content;

        $content = app(Snapshots::class)->read($snapshot);

        $this->assertSame(2, $content['schema_version']);
        $this->assertSame([], $content['layout']);
        $this->assertSame(['width' => 1680, 'height' => 1188], $content['artboard']);
        $this->assertSame(0, SystemNotice::count());
        $this->assertSame([$encoded, $this->content()->checksum($encoded)], [(string) $snapshot->fresh()->content, (string) $snapshot->fresh()->checksum]);
    }

    public function test_the_page_of_a_version_one_snapshot_carries_no_layouts(): void
    {
        $snapshot = $this->stored(1);

        $this->get("/snapshots/{$snapshot->getKey()}/view")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('damaged', false)->has('layouts', 0));
    }

    public function test_the_page_of_a_version_two_snapshot_carries_the_layouts_of_its_moment(): void
    {
        $snapshot = $this->take();

        $this->get("/snapshots/{$snapshot->getKey()}/view")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('layouts', 2)
                ->where('layouts.'.$this->domain->id, ['mode' => 'manual', 'x' => 40, 'y' => 220, 'width' => 300, 'height' => 120, 'z' => 2, 'style' => ['fill' => '#F6E6CC']])
                ->where('layouts.primary_activities.width', 1100));
    }

    public function test_altered_bytes_fail_the_checksum_before_any_upgrade(): void
    {
        $snapshot = $this->stored(1);
        DB::table('snapshots')->where('id', $snapshot->getKey())->update(['content' => str_replace('"budget":10000', '"budget":99999', (string) $snapshot->content)]);

        $this->assertNull(app(Snapshots::class)->read(Snapshot::findOrFail($snapshot->getKey())));
        $this->assertSame('snapshot_damaged', SystemNotice::sole()->kind);

        $this->get("/snapshots/{$snapshot->getKey()}/view")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('damaged', true)->has('layouts', 0));
    }

    public function test_a_version_the_code_does_not_know_counts_as_damaged(): void
    {
        $snapshot = $this->stored(3);

        $this->assertNull(app(Snapshots::class)->read($snapshot));
        $this->assertSame('snapshot_damaged', SystemNotice::sole()->kind);

        $this->get("/snapshots/{$snapshot->getKey()}/view")->assertOk()->assertInertia(fn (Assert $page) => $page->where('damaged', true));
    }

    public function test_an_empty_style_reaches_the_page_as_an_object(): void
    {
        $snapshot = $this->take();

        $html = $this->get('/snapshots/'.$snapshot->getKey().'/view')->assertOk()->getContent();

        $this->assertStringContainsString('"style":{}', (string) $html);
    }

    public function test_content_whose_layout_is_not_a_list_of_boxes_counts_as_damaged(): void
    {
        $content = $this->content();
        $built = $content->build($this->canvas, $this->fiscalYear(2026));

        $this->assertNotNull($content->upgraded($built));
        $this->assertNull($content->upgraded([...$built, 'layout' => ['x' => 'text']]));

        unset($built['layout']);
        $this->assertNull($content->upgraded($built));
    }
}
