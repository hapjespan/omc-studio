<?php

namespace Tests\Feature\Layout;

use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;

class DrawingMoveTest extends StructureTestCase
{
    private User $administrator;

    private Element $source;

    private Element $target;

    private Element $cluster;

    private Element $activity;

    /** @var array<string, int> */
    private array $loaded;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
        $this->source = $this->domain('primary_activities', 'Inkoop');
        $this->target = $this->domain('primary_activities', 'Verkoop');
        $this->cluster = $this->cluster($this->source);
        $this->activity = $this->activity($this->cluster);

        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->cluster->id, 'x' => 8, 'y' => 8, 'width' => 200, 'height' => 100, 'z' => 0, 'style' => []]);
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->activity->id, 'x' => 4, 'y' => 40, 'width' => 160, 'height' => 48, 'z' => 0, 'style' => []]);

        $this->canvas->refresh();
        $this->loaded = ['structure_version' => (int) $this->canvas->structure_version, 'layout_version' => (int) $this->canvas->layout_version];
    }

    public function test_a_move_reparents_the_block_and_stores_its_layout_under_the_new_parent(): void
    {
        $this->move($this->payload())
            ->assertOk()
            ->assertJsonPath('structure_version', $this->loaded['structure_version'] + 1)
            ->assertJsonPath('layout_version', $this->loaded['layout_version'] + 1);

        $this->assertSame($this->target->id, $this->cluster->fresh()?->parent_id);

        $row = Layout::query()->where('element_id', $this->cluster->id)->firstOrFail();

        $this->assertSame([16, 24, 200, 100, 0], [$row->x, $row->y, $row->width, $row->height, $row->z]);
        $this->assertSame([], $row->style);
        $this->assertSame(1, $row->format_version);

        $this->canvas->refresh();
        $this->assertSame($this->loaded['structure_version'] + 1, $this->canvas->structure_version);
        $this->assertSame($this->loaded['layout_version'] + 1, $this->canvas->layout_version);
    }

    public function test_the_subtree_moves_along_and_keeps_its_own_layout(): void
    {
        $this->move($this->payload())->assertOk();

        $activity = Element::query()->findOrFail($this->activity->id);
        $row = Layout::query()->where('element_id', $this->activity->id)->firstOrFail();

        $this->assertSame($this->cluster->id, $activity->parent_id);
        $this->assertSame($this->target->area_id, $activity->area_id);
        $this->assertSame([4, 40, 160, 48], [$row->x, $row->y, $row->width, $row->height]);
    }

    public function test_a_move_without_a_position_goes_to_the_end_of_the_order_of_the_new_parent(): void
    {
        $sitting = $this->cluster($this->target, 'Bestaand');

        $this->move($this->payload(['structure_version' => (int) $this->canvas->fresh()?->structure_version]))->assertOk();

        $this->assertSame(0, $sitting->fresh()?->position);
        $this->assertSame(1, $this->cluster->fresh()?->position);
    }

    public function test_a_position_puts_the_block_back_in_the_order_of_the_new_parent(): void
    {
        $sitting = $this->cluster($this->target, 'Bestaand');

        $this->move($this->payload(['structure_version' => (int) $this->canvas->fresh()?->structure_version, 'position' => 0]))->assertOk();

        $this->assertSame(0, $this->cluster->fresh()?->position);
        $this->assertSame(1, $sitting->fresh()?->position);
    }

    public function test_an_undo_without_a_layout_removes_the_row_of_the_block_and_keeps_the_subtree(): void
    {
        $this->move($this->payload(['layout' => null]))
            ->assertOk()
            ->assertJsonPath('layout_version', $this->loaded['layout_version'] + 1);

        $this->assertSame($this->target->id, $this->cluster->fresh()?->parent_id);
        $this->assertNull(Layout::query()->where('element_id', $this->cluster->id)->first());
        $this->assertNotNull(Layout::query()->where('element_id', $this->activity->id)->first());
    }

    public function test_an_unknown_element_is_refused_and_stores_nothing(): void
    {
        $this->move($this->payload(['element_id' => '0193c2e4-0000-7000-8000-000000000000']))
            ->assertStatus(422)
            ->assertJsonValidationErrors('rule');

        $this->assertSame($this->source->id, $this->cluster->fresh()?->parent_id);
        $this->assertSame($this->loaded['structure_version'], (int) $this->canvas->fresh()?->structure_version);
    }

    public function test_a_move_keeps_height_z_and_style_from_the_stored_row_whatever_the_request_sends(): void
    {
        Layout::query()->where('element_id', $this->cluster->id)->update(['z' => 2, 'style' => json_encode(['fill' => '#EFEFEF'])]);
        $this->canvas->refresh();
        $payload = $this->payload(['layout' => ['x' => 16, 'y' => 24, 'width' => 200, 'height' => 999, 'z' => 7, 'style' => ['fill' => '#F6E6CC']]]);

        $this->move($payload)->assertOk();

        $row = Layout::query()->where('element_id', $this->cluster->id)->firstOrFail();

        $this->assertSame([16, 24, 200, 100, 2], [$row->x, $row->y, $row->width, $row->height, $row->z]);
        $this->assertSame(['fill' => '#EFEFEF'], $row->style);
    }

    public function test_a_refused_layout_rolls_back_the_move_the_versions_and_the_rows(): void
    {
        $this->move($this->payload(['layout' => ['x' => 0, 'y' => 0, 'width' => 10, 'height' => 10, 'z' => 0, 'style' => []]]))
            ->assertStatus(422)
            ->assertJsonValidationErrors('rule');

        $row = Layout::query()->where('element_id', $this->cluster->id)->firstOrFail();

        $this->assertSame($this->source->id, $this->cluster->fresh()?->parent_id);
        $this->assertSame([8, 8, 200, 100], [$row->x, $row->y, $row->width, $row->height]);

        $this->canvas->refresh();
        $this->assertSame($this->loaded, ['structure_version' => (int) $this->canvas->structure_version, 'layout_version' => (int) $this->canvas->layout_version]);
    }

    public function test_a_block_outside_the_width_of_its_new_parent_is_pushed_inside(): void
    {
        $this->move($this->payload(['layout' => ['x' => 200, 'y' => 0, 'width' => 200, 'height' => 100, 'z' => 0, 'style' => []]]))->assertOk();

        $row = Layout::query()->where('element_id', $this->cluster->id)->firstOrFail();

        $this->assertSame($this->target->id, $this->cluster->fresh()?->parent_id);
        $this->assertSame([84, 0, 200, 100], [$row->x, $row->y, $row->width, $row->height]);
    }

    public function test_a_reader_is_refused(): void
    {
        $this->actingAs(User::factory()->create())->postJson($this->url(), $this->payload())->assertForbidden();

        $this->assertSame($this->source->id, $this->cluster->fresh()?->parent_id);
    }

    public function test_a_guest_is_sent_to_sign_in(): void
    {
        $this->post($this->url(), $this->payload())->assertRedirect('/sign-in');

        $this->assertSame($this->source->id, $this->cluster->fresh()?->parent_id);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return TestResponse<Response>
     */
    private function move(array $payload): TestResponse
    {
        return $this->actingAs($this->administrator)->postJson($this->url(), $payload);
    }

    private function url(): string
    {
        return '/canvases/'.$this->canvas->id.'/drawing/moves';
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
            'layout' => ['x' => 16, 'y' => 24, 'width' => 200, 'height' => 120, 'z' => 1, 'style' => ['fill' => '#F6E6CC']],
        ], $values);
    }
}
