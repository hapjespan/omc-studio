<?php

namespace Tests\Feature\Exchange;

use App\Models\Application;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\Owner;
use App\Models\Snapshot;
use App\Models\User;
use App\Services\Exchange\FullImportValidator;
use App\Services\Finance\FigureService;
use App\Services\Snapshot\Snapshots;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FullRoundTripTest extends CanvasExchangeTestCase
{
    /**
     * @param  array<string, mixed>  $filter
     * @return list<mixed>
     */
    private function figures(Canvas $canvas, int $year, array $filter): array
    {
        $figures = app(FigureService::class)->figures($canvas, $year, $filter);
        $this->assertNotNull($figures);
        $names = Element::where('canvas_id', $canvas->id)->pluck('name', 'id');
        $elements = [];

        foreach ($figures->elements as $id => $element) {
            $elements[$names[$id]] = [$element['direct'], $element['total'], $element['shareBp']];
        }

        ksort($elements);

        return [$elements, $figures->areas, $figures->canvasTotal, $figures->breakdowns, $figures->sidePanel];
    }

    /** @return array<string, array<string, mixed>> */
    private function filters(Canvas $canvas): array
    {
        $owner = Owner::where('organisation_id', $canvas->organisation_id)->where('name', 'Jansen')->firstOrFail();
        $application = Application::where('organisation_id', $canvas->organisation_id)->where('name', 'ERP')->firstOrFail();

        return [
            'alles' => [],
            'overig' => ['costKind' => 'other'],
            'personeel' => ['costKind' => 'personnel'],
            'eigenaar Jansen' => ['ownerId' => $owner->id],
            'applicatie ERP' => ['applicationId' => $application->id],
        ];
    }

    /** @return array<string, mixed> */
    private function everything(): array
    {
        $all = [];

        foreach (Canvas::where('kind', 'canvas')->orderBy('name')->get() as $canvas) {
            foreach ([2026, 2027] as $year) {
                foreach ($this->filters($canvas) as $label => $filter) {
                    $all[$canvas->name][$year][$label] = $this->figures($canvas, $year, $filter);
                }
            }
        }

        return $all;
    }

    /** @return array<string, array{string, string}> */
    private function snapshots(): array
    {
        $read = app(Snapshots::class);
        $all = [];

        foreach (Snapshot::orderBy('name')->get() as $snapshot) {
            $content = $read->read($snapshot);
            $this->assertNotNull($content, 'snapshot '.$snapshot->name.' does not pass its checksum');
            $all[$snapshot->name] = [$snapshot->checksum, (string) json_encode($content)];
        }

        return $all;
    }

    private function depth(mixed $node): int
    {
        return is_array($node) ? 1 + max([0, ...array_map(fn (mixed $value) => $this->depth($value), $node)]) : 0;
    }

    private function wipe(): void
    {
        DB::statement('PRAGMA foreign_keys = OFF');

        foreach (['snapshots', 'allocation_lines', 'allocation_sets', 'yearly_figures', 'finalised_years', 'elements', 'statements', 'cost_records', 'owners', 'applications', 'fiscal_years', 'canvases', 'organisations'] as $table) {
            DB::table($table)->delete();
        }

        DB::statement('PRAGMA foreign_keys = ON');
    }

    /** @param array<string, mixed> $extra */
    private function importCanvas(string $file, array $extra): void
    {
        $this->post('/imports/canvas', ['file' => UploadedFile::fake()->createWithContent('canvas.json', $file), ...$extra], ['Accept' => 'application/json'])->assertOk();
    }

    public function test_the_full_round_trip_gives_identical_figures_and_snapshots(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());

        $canvas = (string) json_encode($this->exported());

        $this->importCanvas($canvas, ['organisation_id' => $this->canvas->organisation_id, 'canvas_name' => 'Tweede canvas']);
        $this->importCanvas($canvas, ['organisation_name' => 'Tweede Zorg', 'canvas_name' => 'Canvas van Twee']);
        app(Snapshots::class)->take($this->canvas, $this->fiscalYear(2026), 'Voor de stuurgroep');

        $before = $this->everything();
        $snapshots = $this->snapshots();
        $finalised = DB::table('finalised_years')->count();

        $this->assertCount(3, $before);
        $this->assertCount(1, $snapshots);

        $file = (string) $this->get('/admin/export/full')->assertOk()->getContent();

        $this->assertLessThan(FullImportValidator::DEPTH, $this->depth(json_decode($file, true, 512)));

        $this->wipe();
        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $file)], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(2, Organisation::count());
        $this->assertSame($finalised, DB::table('finalised_years')->count());
        $this->assertSame($before, $this->everything());
        $this->assertSame($snapshots, $this->snapshots());
        $this->assertTrue(Snapshot::firstOrFail()->imported);
        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $file)], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
