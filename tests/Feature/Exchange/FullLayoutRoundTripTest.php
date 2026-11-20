<?php

namespace Tests\Feature\Exchange;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\Organisation;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Snapshot\SnapshotContent;
use App\Services\Snapshot\Snapshots;
use Illuminate\Http\UploadedFile;

class FullLayoutRoundTripTest extends CanvasExchangeTestCase
{
    /** @param array<string, mixed> $style */
    private function place(Canvas $canvas, string $name, int $x, int $y, int $width, int $height, array $style = []): void
    {
        $element = Element::where('canvas_id', $canvas->id)->where('name', $name)->firstOrFail();
        Layout::create(['canvas_id' => $canvas->id, 'element_id' => $element->id, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'z' => 2, 'style' => $style]);
    }

    /** @return array<string, array<string, list<mixed>>> */
    private function drawing(): array
    {
        $areas = Area::pluck('key', 'id');
        $names = Element::pluck('name', 'id');
        $drawing = [];

        foreach (Canvas::where('kind', 'canvas')->orderBy('name')->get() as $canvas) {
            foreach (Layout::where('canvas_id', $canvas->id)->get() as $row) {
                $owner = $row->element_id !== null ? 'element '.$names[$row->element_id] : 'area '.$areas[$row->area_id];
                $drawing[$canvas->name][$owner] = [$row->x, $row->y, $row->width, $row->height, $row->z, $row->style, $row->format_version];
            }
        }

        ksort($drawing);

        return array_map(function (array $rows) {
            ksort($rows);

            return $rows;
        }, $drawing);
    }

    public function test_every_layout_is_identical_after_export_wipe_and_import(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());

        $this->place($this->canvas, 'Inkoop', 20, 60, 400, 300, ['fill' => '#F6E6CC', 'fill_opacity' => 80, 'font' => 'inter']);
        $this->place($this->canvas, 'Werving', 16, 40, 200, 100, ['italic' => true]);
        $this->place($this->canvas, 'Facilitair', 8, 30, 300, 200);
        Layout::create(['canvas_id' => $this->canvas->id, 'area_id' => Area::where('key', 'primary_activities')->value('id'), 'x' => 560, 'y' => 400, 'width' => 1100, 'height' => 380, 'z' => 0, 'style' => ['fill' => '#ECE8F5']]);

        $file = (string) json_encode($this->exported());
        $this->post('/imports/canvas', ['file' => UploadedFile::fake()->createWithContent('canvas.json', $file), 'organisation_name' => 'Tweede Zorg', 'canvas_name' => 'Canvas van Twee'], ['Accept' => 'application/json'])->assertOk();

        $before = $this->drawing();
        $this->assertCount(2, $before);
        $this->assertCount(4, $before['Basis canvas']);
        $this->assertSame($before['Basis canvas'], $before['Canvas van Twee']);

        $full = (string) $this->get('/admin/export/full')->assertOk()->getContent();

        $this->wipe();
        $this->assertSame(0, Layout::count());
        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $full)], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(2, Organisation::count());
        $this->assertSame($before, $this->drawing());
    }

    public function test_a_full_file_without_layout_imports_every_canvas_automatic(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());

        $full = json_decode((string) $this->get('/admin/export/full')->assertOk()->getContent(), true);

        foreach ($full['organisations'] as &$organisation) {
            foreach ($organisation['canvases'] as &$canvas) {
                unset($canvas['layout']);
            }
        }
        unset($organisation, $canvas);

        $this->wipe();
        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', (string) json_encode($full))], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(0, Layout::count());
        $this->assertGreaterThan(0, Canvas::where('kind', 'canvas')->count());
    }

    public function test_a_snapshot_of_the_first_schema_keeps_its_stored_bytes_and_checksum_through_a_full_round_trip(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());

        $content = app(SnapshotContent::class);
        $built = $content->build($this->canvas, $this->fiscalYear(2026));
        unset($built['layout'], $built['artboard']);
        $built['schema_version'] = 1;
        $encoded = $content->encode($built);
        Snapshot::create(['canvas_id' => $this->canvas->id, 'name' => 'Oude versie', 'fiscal_year' => 2026, 'remark' => null, 'created_by' => null, 'created_at' => now(), 'imported' => false, 'schema_version' => 1, 'content' => $encoded, 'checksum' => $content->checksum($encoded)]);

        $full = (string) $this->get('/admin/export/full')->assertOk()->getContent();
        $this->wipe();
        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $full)], ['Accept' => 'application/json'])->assertOk();

        $stored = Snapshot::where('name', 'Oude versie')->firstOrFail();
        $this->assertSame($encoded, $stored->content);
        $this->assertSame($content->checksum($encoded), $stored->checksum);
        $this->assertSame(1, $stored->schema_version);
        $read = app(Snapshots::class)->read($stored);
        $this->assertSame(2, $read['schema_version']);
        $this->assertSame([], $read['layout']);
    }

    public function test_a_snapshot_whose_content_names_another_schema_than_its_row_is_refused_at_its_place(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
        app(Snapshots::class)->take($this->canvas, $this->fiscalYear(2026), 'Versie');

        $full = json_decode((string) $this->get('/admin/export/full')->assertOk()->getContent(), true);
        $full['organisations'][0]['canvases'][0]['snapshots'][0]['content']['schema_version'] = 3;
        $this->wipe();

        $response = $this->post('/admin/import/full/preview', ['file' => UploadedFile::fake()->createWithContent('full.json', (string) json_encode($full))], ['Accept' => 'application/json']);

        $this->assertContains('organisations[0].canvases[0].snapshots[0].content.schema_version', array_column($response->assertOk()->json('errors'), 'location'));
    }
}
