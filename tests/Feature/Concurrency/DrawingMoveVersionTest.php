<?php

namespace Tests\Feature\Concurrency;

use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;

class DrawingMoveVersionTest extends StructureTestCase
{
    private User $administrator;

    private Element $source;

    private Element $target;

    private Element $cluster;

    /** @var array<string, int> */
    private array $loaded;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->source = $this->domain('primary_activities', 'Inkoop');
        $this->target = $this->domain('primary_activities', 'Verkoop');
        $this->cluster = $this->cluster($this->source);

        $this->canvas->refresh();
        $this->loaded = ['structure_version' => (int) $this->canvas->structure_version, 'layout_version' => (int) $this->canvas->layout_version];
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
