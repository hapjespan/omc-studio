<?php

namespace Tests\Feature\Canvas;

use App\Models\Canvas;
use App\Models\Element;
use App\Models\Modality;
use App\Models\Statement;
use App\Models\User;
use App\Services\Canvas\ViewData;
use App\Services\Operations\Steps\ReferenceDataStep;
use FilesystemIterator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class ViewDataPresenterTest extends TestCase
{
    use RefreshDatabase;

    private const ELEMENT_KEYS = ['id', 'area', 'parent', 'kind', 'name', 'position', 'modality', 'unknownAllocation', 'description', 'rationale'];

    public function test_the_layout_input_shapes_are_built_in_view_data_only(): void
    {
        $copies = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path(), FilesystemIterator::SKIP_DOTS)) as $file) {
            $source = (string) file_get_contents($file->getPathname());

            if ($file->getFilename() !== 'ViewData.php' && (str_contains($source, "'directionLabels'") || str_contains($source, '$letters['))) {
                $copies[] = $file->getFilename();
            }
        }

        $this->assertSame([], $copies);
    }

    public function test_layout_carries_eight_areas_and_the_direction_labels(): void
    {
        (new ReferenceDataStep)();

        $layout = app(ViewData::class)->layout();

        $this->assertCount(8, $layout['areas']);
        $this->assertSame(['key', 'kind', 'position', 'label'], array_keys($layout['areas'][0]));
        $this->assertSame(['mission', 'vision', 'goals'], array_keys($layout['directionLabels']));
        $this->assertSame('Strategische doelen', $layout['directionLabels']['goals']);
    }

    public function test_elements_present_models_and_snapshot_rows_in_the_same_shape(): void
    {
        (new ReferenceDataStep)();
        $canvas = Canvas::factory()->create();
        $element = Element::factory()->create(['canvas_id' => $canvas->id, 'name' => 'Inkoop', 'description' => 'd', 'rationale' => 'r', 'modality_id' => Modality::where('key', 'adaptive')->value('id')]);
        $stored = [
            'id' => $element->id, 'parent_id' => null, 'area' => $element->area->key, 'kind' => $element->kind->value, 'name' => 'Inkoop', 'description' => 'd',
            'modality' => 'adaptive', 'rationale' => 'r', 'position' => $element->position, 'is_unknown_allocation' => false,
        ];

        $fromModel = app(ViewData::class)->elements(Element::where('canvas_id', $canvas->id)->with('area:id,key')->get());
        $fromSnapshot = app(ViewData::class)->elements([$stored]);

        $this->assertSame(self::ELEMENT_KEYS, array_keys($fromModel[0]));
        $this->assertEquals($fromModel[0], $fromSnapshot[0]);
        $this->assertSame('A', $fromSnapshot[0]['modality']);
        $this->assertSame(array_diff(self::ELEMENT_KEYS, ['description', 'rationale']), array_keys(app(ViewData::class)->elements([$stored], false)[0]));
    }

    public function test_statements_present_models_and_snapshot_rows_in_the_same_shape(): void
    {
        $canvas = Canvas::factory()->create();
        $statement = Statement::create(['canvas_id' => $canvas->id, 'kind' => 'goal', 'title' => 'T', 'body' => 'B', 'position' => 2]);

        $fromModel = app(ViewData::class)->statements([$statement]);
        $fromSnapshot = app(ViewData::class)->statements([['kind' => 'goal', 'title' => 'T', 'body' => 'B', 'position' => 2]]);

        $this->assertSame(['kind', 'title', 'body', 'position'], array_keys($fromModel[0]));
        $this->assertEquals($fromModel, $fromSnapshot);
    }

    public function test_the_overview_looks_the_modalities_up_once_however_many_canvases_it_shows(): void
    {
        (new ReferenceDataStep)();

        foreach (range(1, 4) as $number) {
            $canvas = Canvas::factory()->create(['name' => 'Canvas '.$number]);
            Element::factory()->create(['canvas_id' => $canvas->id]);
        }

        $this->actingAs(User::factory()->create());
        DB::enableQueryLog();
        $this->get('/')->assertOk();

        $lookups = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "modalities"'))->count();

        $this->assertLessThanOrEqual(1, $lookups);
    }
}
