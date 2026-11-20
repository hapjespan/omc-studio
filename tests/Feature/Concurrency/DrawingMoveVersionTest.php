<?php

namespace Tests\Feature\Concurrency;

use App\Enums\CanvasKind;
use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;
use Tests\Support\RaceDatabase;
use Tests\Support\TwoProcessRunner;

class DrawingMoveVersionTest extends StructureTestCase
{
    private RaceDatabase $race;

    private User $administrator;

    private Element $source;

    private Element $target;

    private Element $cluster;

    /** @var array<string, int> */
    private array $loaded;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = new RaceDatabase;
        $this->administrator = User::factory()->administrator()->create();
        $this->source = $this->domain('primary_activities', 'Inkoop');
        $this->target = $this->domain('primary_activities', 'Verkoop');
        $this->cluster = $this->cluster($this->source);

        $this->canvas->refresh();
        $this->loaded = ['structure_version' => (int) $this->canvas->structure_version, 'layout_version' => (int) $this->canvas->layout_version];
    }

    protected function tearDown(): void
    {
        $this->race->clean(['audit_entries', 'layouts', 'elements', 'canvases', 'areas', 'organisations']);

        parent::tearDown();
    }

    public function test_a_move_on_a_stale_structure_version_is_refused_and_stores_nothing(): void
    {
        $this->domain('primary_activities', 'Logistiek');

        $this->move($this->payload())
            ->assertStatus(409)
            ->assertJsonPath('conflict', 'structure_changed');

        $this->assertStored($this->loaded['structure_version'] + 1);
    }

    public function test_the_structure_version_is_checked_before_the_layout_version(): void
    {
        $this->domain('primary_activities', 'Logistiek');

        $this->move($this->payload(['layout_version' => 9]))
            ->assertStatus(409)
            ->assertJsonPath('conflict', 'structure_changed');
    }

    public function test_a_move_on_a_stale_layout_version_is_refused_and_stores_nothing(): void
    {
        $this->move($this->payload(['layout_version' => $this->loaded['layout_version'] + 1]))
            ->assertStatus(409)
            ->assertJsonPath('conflict', 'layout_changed');

        $this->assertStored($this->loaded['structure_version']);
    }

    public function test_two_moves_of_the_same_versions_end_with_one_success_and_one_refusal(): void
    {
        $environment = $this->race->prepare();

        $area = Area::on('race')->create(['key' => 'primary_activities', 'kind' => 'activities', 'position' => 7, 'carries_costs' => true]);
        $canvas = Canvas::on('race')->create(['kind' => CanvasKind::Canvas, 'name' => 'Race']);
        $source = $this->raceElement($canvas, $area, ElementKind::Domain, null, 'Inkoop');
        $target = $this->raceElement($canvas, $area, ElementKind::Domain, null, 'Verkoop');
        $cluster = $this->raceElement($canvas, $area, ElementKind::Cluster, $source, 'Werving');

        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/drawing-move-worker.php'),
            [(string) $canvas->getKey(), '0', '0', (string) $cluster->getKey(), (string) $target->getKey()],
            $environment,
        );

        sort($outcomes);

        $this->assertSame(['ok 1', 'structure_changed'], $outcomes);
        $this->assertSame(1, (int) Canvas::on('race')->findOrFail($canvas->getKey())->structure_version);
        $this->assertSame(1, Layout::on('race')->where('canvas_id', $canvas->getKey())->count());
    }

    private function raceElement(Canvas $canvas, Area $area, ElementKind $kind, ?Element $parent, string $name): Element
    {
        return Element::on('race')->create([
            'canvas_id' => $canvas->getKey(),
            'area_id' => $area->getKey(),
            'parent_id' => $parent?->getKey(),
            'kind' => $kind,
            'name' => $name,
        ]);
    }

    private function assertStored(int $structureVersion): void
    {
        $this->canvas->refresh();

        $this->assertSame($this->source->id, $this->cluster->fresh()?->parent_id);
        $this->assertSame(0, Layout::query()->count());
        $this->assertSame($structureVersion, (int) $this->canvas->structure_version);
        $this->assertSame($this->loaded['layout_version'], (int) $this->canvas->layout_version);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function move(array $payload): TestResponse
    {
        return $this->actingAs($this->administrator)->postJson('/canvases/'.$this->canvas->id.'/drawing/moves', $payload);
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    private function payload(array $values = []): array
    {
        return array_replace([
            ...$this->loaded,
            'element_id' => (string) $this->cluster->id,
            'target_id' => (string) $this->target->id,
            'layout' => ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 100, 'z' => 0, 'style' => []],
        ], $values);
    }
}
