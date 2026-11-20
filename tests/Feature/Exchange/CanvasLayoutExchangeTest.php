<?php

namespace Tests\Feature\Exchange;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Http\UploadedFile;

class CanvasLayoutExchangeTest extends CanvasExchangeTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->richCanvas();
        $this->actingAs(User::factory()->administrator()->create());
    }

    /** @param array<string, mixed> $style */
    private function place(string $name, int $x, int $y, int $width, int $height, array $style = []): Element
    {
        $element = Element::where('canvas_id', $this->canvas->id)->where('name', $name)->firstOrFail();
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $element->id, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'z' => 1, 'style' => $style]);

        return $element;
    }

    private function arrange(): void
    {
        $this->place('Inkoop', 20, 60, 400, 300, ['fill' => '#F6E6CC', 'fill_opacity' => 80]);
        $this->place('Werving', 16, 40, 200, 100);
        Layout::create(['canvas_id' => $this->canvas->id, 'area_id' => Area::where('key', 'primary_activities')->value('id'), 'x' => 560, 'y' => 400, 'width' => 1100, 'height' => 380, 'z' => 0, 'style' => []]);
    }

    /**
     * @param  array<string, mixed>  $file
     * @return array<string, mixed>
     */
    private function import(array $file, string $name = 'Kopie'): array
    {
        $response = $this->post('/imports/canvas', ['file' => UploadedFile::fake()->createWithContent('canvas.json', (string) json_encode($file)), 'organisation_name' => 'Doel '.$name, 'canvas_name' => $name], ['Accept' => 'application/json']);

        return ['status' => $response->getStatusCode(), 'json' => $response->json()];
    }

    /**
     * @param  array<string, mixed>  $file
     * @return list<string>
     */
    private function locations(array $file): array
    {
        $response = $this->post('/imports/canvas/preview', ['file' => UploadedFile::fake()->createWithContent('canvas.json', (string) json_encode($file)), 'organisation_name' => 'Doel', 'canvas_name' => 'Kopie'], ['Accept' => 'application/json']);

        return array_column($response->assertOk()->json('errors'), 'location');
    }

    public function test_the_export_carries_the_layout_of_every_active_element_without_version_fields(): void
    {
        $this->arrange();

        $layout = $this->exported()['canvas']['layout'];
        $active = Element::where('canvas_id', $this->canvas->id)->whereNull('archived_at')->count();

        $this->assertSame(['areas', 'elements'], array_keys($layout));
        $this->assertCount($active, $layout['elements']);
        $inkoop = Element::where('canvas_id', $this->canvas->id)->where('name', 'Inkoop')->value('id');
        $this->assertSame(['mode' => 'manual', 'x' => 20, 'y' => 60, 'width' => 400, 'height' => 300, 'z' => 1, 'style' => ['fill' => '#F6E6CC', 'fill_opacity' => 80]], $layout['elements'][$inkoop]);
        $offreren = Element::where('canvas_id', $this->canvas->id)->where('name', 'Offreren')->value('id');
        $this->assertSame(['mode' => 'automatic'], $layout['elements'][$offreren]);
        $this->assertSame(1100, $layout['areas']['primary_activities']['width']);
        $archived = Element::where('canvas_id', $this->canvas->id)->where('name', 'Oud')->value('id');
        $this->assertArrayNotHasKey($archived, $layout['elements']);
    }

    public function test_an_import_maps_the_layout_onto_the_new_element_identifiers(): void
    {
        $this->arrange();

        $this->assertSame(200, $this->import($this->exported())['status']);

        $copy = Canvas::where('name', 'Kopie')->firstOrFail();
        $rows = Layout::where('canvas_id', $copy->id)->get();

        $this->assertCount(3, $rows);
        $inkoop = Element::where('canvas_id', $copy->id)->where('name', 'Inkoop')->firstOrFail();
        $row = $rows->firstWhere('element_id', $inkoop->id);
        $this->assertSame([20, 60, 400, 300, 1], [$row->x, $row->y, $row->width, $row->height, $row->z]);
        $this->assertSame(['fill' => '#F6E6CC', 'fill_opacity' => 80], $row->style);
        $area = $rows->firstWhere('area_id', Area::where('key', 'primary_activities')->value('id'));
        $this->assertSame([560, 400, 1100, 380], [$area->x, $area->y, $area->width, $area->height]);
        $this->assertNull(Layout::where('canvas_id', $copy->id)->whereIn('element_id', Element::where('canvas_id', $this->canvas->id)->pluck('id'))->first());
    }

    public function test_a_file_without_layout_is_accepted_and_the_canvas_starts_automatic(): void
    {
        $file = $this->exported();
        $this->assertNull($file['canvas']['layout']);

        $this->assertSame(200, $this->import($file)['status']);
        $this->assertSame(0, Layout::where('canvas_id', Canvas::where('name', 'Kopie')->value('id'))->count());

        unset($file['canvas']['layout']);
        $this->assertSame(200, $this->import($file, 'Zonder')['status']);
    }

    public function test_an_invalid_layout_is_reported_with_its_location_and_nothing_is_imported(): void
    {
        $this->arrange();
        $file = $this->exported();
        $inkoop = Element::where('canvas_id', $this->canvas->id)->where('name', 'Inkoop')->value('id');
        $werving = Element::where('canvas_id', $this->canvas->id)->where('name', 'Werving')->value('id');

        $broken = $file;
        $broken['canvas']['layout']['elements'][$inkoop]['style']['fill'] = 'rood';
        $this->assertContains("canvas.layout.elements.$inkoop.style", $this->locations($broken));

        $broken = $file;
        $broken['canvas']['layout']['elements'][$werving]['width'] = 900;
        $this->assertContains("canvas.layout.elements.$werving", $this->locations($broken));

        $broken = $file;
        $broken['canvas']['layout']['areas']['direction'] = ['x' => 0, 'y' => 0, 'width' => 1680, 'height' => 180];
        $this->assertContains('canvas.layout.areas', $this->locations($broken));

        $broken = $file;
        unset($broken['canvas']['layout']['elements'][$inkoop]);
        $this->assertContains("canvas.layout.elements.$inkoop", $this->locations($broken));

        $broken = $file;
        $broken['canvas']['layout'] = 'x';
        $this->assertContains('canvas.layout', $this->locations($broken));

        $before = Canvas::count();
        $broken = $file;
        $broken['canvas']['layout']['elements'][$werving]['width'] = 900;
        $this->assertSame(422, $this->import($broken, 'Kapot')['status']);
        $this->assertSame($before, Canvas::count());
    }

    public function test_an_unknown_element_in_the_layout_is_reported_at_its_own_place(): void
    {
        $this->arrange();
        $file = $this->exported();
        $file['canvas']['layout']['elements']['ghost'] = ['mode' => 'automatic'];

        $this->assertContains('canvas.layout.elements.ghost', $this->locations($file));
    }

    public function test_a_broken_parent_is_reported_without_crashing_the_layout_check(): void
    {
        $this->arrange();
        $file = $this->exported();
        $index = array_search('Offreren', array_column($file['canvas']['elements'], 'name'), true);

        $dangling = $file;
        $dangling['canvas']['elements'][$index]['parent_id'] = 'nope';
        $this->assertContains("canvas.elements[$index].parent_id", $this->locations($dangling));

        $self = $file;
        $self['canvas']['elements'][$index]['parent_id'] = $self['canvas']['elements'][$index]['id'];
        $this->assertNotSame([], $this->locations($self));

        $archivedParent = $file;
        $parent = array_search($file['canvas']['elements'][$index]['parent_id'], array_column($file['canvas']['elements'], 'id'), true);
        $archivedParent['canvas']['elements'][$parent]['archived_at'] = '2026-01-01T00:00:00+00:00';
        $this->assertNotSame([], $this->locations($archivedParent));
    }
}
