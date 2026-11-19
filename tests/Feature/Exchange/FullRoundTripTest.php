<?php

namespace Tests\Feature\Exchange;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Finance\FigureService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class FullRoundTripTest extends CanvasExchangeTestCase
{
    /**
     * @param  array<string, string>  $filter
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

    /** @return list<mixed> */
    private function everything(): array
    {
        $all = [];

        foreach (Canvas::where('kind', 'canvas')->orderBy('name')->get() as $canvas) {
            foreach ([2026, 2027] as $year) {
                foreach ([[], ['costKind' => 'other'], ['costKind' => 'personnel']] as $filter) {
                    $all[$canvas->name][$year][json_encode($filter)] = $this->figures($canvas, $year, $filter);
                }
            }
        }

        return $all;
    }

    public function test_the_full_round_trip_gives_identical_figures_to_the_cent(): void
    {
        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
        $before = $this->everything();
        $finalised = DB::table('finalised_years')->count();
        $file = (string) $this->get('/admin/export/full')->assertOk()->getContent();

        DB::statement('PRAGMA foreign_keys = OFF');

        foreach (['allocation_lines', 'allocation_sets', 'yearly_figures', 'finalised_years', 'elements', 'statements', 'cost_records', 'owners', 'applications', 'fiscal_years', 'canvases', 'organisations'] as $table) {
            DB::table($table)->delete();
        }

        DB::statement('PRAGMA foreign_keys = ON');

        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $file)], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(1, Organisation::count());
        $this->assertSame($finalised, DB::table('finalised_years')->count());
        $this->assertSame($before, $this->everything());
        $this->post('/admin/import/full', ['file' => UploadedFile::fake()->createWithContent('full.json', $file)], ['Accept' => 'application/json'])->assertUnprocessable();
    }
}
