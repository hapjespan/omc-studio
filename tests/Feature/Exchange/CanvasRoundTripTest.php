<?php

namespace Tests\Feature\Exchange;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Organisation;
use App\Models\User;
use App\Services\Finance\FigureService;
use Illuminate\Http\UploadedFile;

class CanvasRoundTripTest extends ExchangeTestCase
{
    /**
     * @param  array<string, string>  $filter
     * @return list<mixed>
     */
    private function snapshot(Canvas $canvas, int $year, array $filter): array
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

    public function test_an_exported_canvas_imported_into_an_empty_organisation_gives_identical_figures(): void
    {
        $this->richCanvas();
        $admin = User::factory()->administrator()->create();
        $this->actingAs($admin);

        $file = (string) $this->get('/canvases/'.$this->canvas->id.'/export/json')->assertOk()->getContent();

        $this->post('/imports/canvas', [
            'file' => UploadedFile::fake()->createWithContent('canvas.json', $file),
            'organisation_name' => 'Doelorganisatie',
            'canvas_name' => 'Kopie',
        ])->assertOk();

        $copy = Canvas::where('name', 'Kopie')->firstOrFail();
        $this->assertNotSame($this->canvas->organisation_id, $copy->organisation_id);
        $this->assertSame('Doelorganisatie', Organisation::findOrFail($copy->organisation_id)->name);

        foreach ([2026, 2027] as $year) {
            foreach ([[], ['costKind' => 'other'], ['costKind' => 'personnel']] as $filter) {
                $this->assertSame($this->snapshot($this->canvas, $year, $filter), $this->snapshot($copy, $year, $filter), "year $year");
            }
        }
    }
}
