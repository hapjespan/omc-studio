<?php

namespace Tests\Feature\Concurrency;

use App\Enums\CanvasKind;
use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\RaceDatabase;
use Tests\Support\TwoProcessRunner;
use Tests\TestCase;

class LayoutVersionTest extends TestCase
{
    use RefreshDatabase;

    private RaceDatabase $race;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = new RaceDatabase;
    }

    protected function tearDown(): void
    {
        $this->race->clean(['audit_entries', 'layouts', 'elements', 'canvases', 'areas', 'organisations']);

        parent::tearDown();
    }

    public function test_two_saves_of_the_same_layout_version_end_with_one_success_and_one_refusal(): void
    {
        $environment = $this->race->prepare();

        $area = Area::on('race')->firstOrCreate(['key' => 'primary_activities'], ['kind' => 'activities', 'position' => 7, 'carries_costs' => true]);
        $canvas = Canvas::on('race')->create(['kind' => CanvasKind::Canvas, 'name' => 'Race']);
        $element = Element::on('race')->create([
            'canvas_id' => $canvas->getKey(),
            'area_id' => $area->getKey(),
            'kind' => ElementKind::Domain,
            'name' => 'Inkoop',
        ]);

        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/layout-version-worker.php'),
            [(string) $canvas->getKey(), '0', '0', (string) $element->getKey()],
            $environment,
        );

        sort($outcomes);

        $this->assertSame(['layout_changed', 'ok 1'], $outcomes);
        $this->assertSame(1, (int) Canvas::on('race')->findOrFail($canvas->getKey())->layout_version);
        $this->assertSame(1, Layout::on('race')->where('canvas_id', $canvas->getKey())->count());
    }
}
