<?php

namespace Tests\Feature\Layout;

use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Feature\Structure\StructureTestCase;

class DrawingMoveNarrowTest extends StructureTestCase
{
    private const TARGET_WIDTH = 300;

    private const AUTOMATIC_DOMAIN_WIDTH = 284;

    private User $administrator;

    private Element $target;

    private Element $block;

    private Element $child;

    private Element $automaticChild;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();

        $source = $this->domain('primary_activities', 'Inkoop');
        $this->target = $this->domain('primary_activities', 'Verkoop');
        $this->block = $this->cluster($source);
        $this->child = $this->activity($this->block, 'Offreren');
        $this->automaticChild = $this->activity($this->block, 'Nabellen');

        $this->row($this->target, 0, 200, self::TARGET_WIDTH, 200);
        $this->row($this->block, 8, 8, 200, 100);
        $this->row($this->child, 10, 10, 150, 48);
    }

    public function test_a_block_that_already_fits_is_stored_unchanged(): void
    {
        $this->move(['x' => 10, 'y' => 20, 'width' => 200, 'height' => 100, 'z' => 2, 'style' => ['fill' => '#F6E6CC']])->assertOk();

        $row = $this->stored($this->block);

        $this->assertSame([10, 20, 200, 100, 2], [$row->x, $row->y, $row->width, $row->height, $row->z]);
        $this->assertSame(['fill' => '#F6E6CC'], $row->style);
    }

    public function test_a_block_released_left_of_the_target_is_pushed_to_x_zero(): void
    {
        $this->move(['x' => -40, 'y' => 20, 'width' => 200, 'height' => 100])->assertOk();

        $row = $this->stored($this->block);

        $this->assertSame([0, 20, 200], [$row->x, $row->y, $row->width]);
    }

    public function test_a_block_released_above_the_target_is_pushed_to_y_zero(): void
    {
        $this->move(['x' => 10, 'y' => -60, 'width' => 200, 'height' => 100])->assertOk();

        $this->assertSame([10, 0], [$this->stored($this->block)->x, $this->stored($this->block)->y]);
    }

    public function test_a_block_released_past_the_right_edge_is_pushed_inside_the_target(): void
    {
        $this->move(['x' => 250, 'y' => 20, 'width' => 200, 'height' => 100])->assertOk();

        $row = $this->stored($this->block);

        $this->assertSame([100, 20, 200, 100], [$row->x, $row->y, $row->width, $row->height]);
    }

    public function test_a_block_released_below_the_content_keeps_its_y_and_stores_nothing_for_the_target(): void
    {
        $this->move(['x' => 10, 'y' => 4000, 'width' => 200, 'height' => 100])->assertOk();

        $this->assertSame(4000, $this->stored($this->block)->y);

        $target = $this->stored($this->target);

        $this->assertSame([0, 200, self::TARGET_WIDTH, 200], [$target->x, $target->y, $target->width, $target->height]);
    }

    public function test_a_block_wider_than_the_target_becomes_exactly_the_target_width(): void
    {
        $this->move(['x' => 60, 'y' => 20, 'width' => 400, 'height' => 100, 'z' => 3, 'style' => ['fill' => '#F6E6CC']])->assertOk();

        $row = $this->stored($this->block);

        $this->assertSame([0, 20, self::TARGET_WIDTH, 100, 3], [$row->x, $row->y, $row->width, $row->height, $row->z]);
        $this->assertSame(['fill' => '#F6E6CC'], $row->style);
    }

    public function test_a_target_without_a_stored_layout_narrows_the_block_to_its_automatic_width(): void
    {
        $bare = $this->domain('primary_activities', 'Marketing');

        $this->move(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 100], ['target_id' => (string) $bare->id])->assertOk();

        $this->assertSame(self::AUTOMATIC_DOMAIN_WIDTH, $this->stored($this->block)->width);
    }

    public function test_a_manual_child_is_pushed_left_before_it_is_narrowed(): void
    {
        $this->row($this->child, 260, 10, 120, 48);

        $this->move(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 100])->assertOk();

        $row = $this->stored($this->child);

        $this->assertSame([180, 10, 120, 48], [$row->x, $row->y, $row->width, $row->height]);
    }

    public function test_a_manual_child_that_stays_outside_is_narrowed_to_the_remaining_width(): void
    {
        $this->row($this->child, 20, 10, 400, 48, 5, ['fill' => '#FFFFFF']);

        $this->move(['x' => 0, 'y' => 0, 'width' => 500, 'height' => 100])->assertOk();

        $row = $this->stored($this->child);

        $this->assertSame([0, 10, self::TARGET_WIDTH, 48, 5], [$row->x, $row->y, $row->width, $row->height, $row->z]);
        $this->assertSame(['fill' => '#FFFFFF'], $row->style);
    }

    public function test_children_are_narrowed_when_the_client_already_narrowed_the_block_to_the_target_width(): void
    {
        $this->row($this->child, 0, 10, 400, 48);

        $this->move(['x' => 0, 'y' => 0, 'width' => self::TARGET_WIDTH, 'height' => 100])->assertOk();

        $this->assertSame(self::TARGET_WIDTH, $this->stored($this->child)->width);
    }

    public function test_a_manual_child_is_never_narrowed_below_the_minimum_width(): void
    {
        $this->row($this->target, 0, 200, 40, 200);
        $this->row($this->child, 100, 10, 200, 48);

        $this->move(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 100])->assertOk();

        $row = $this->stored($this->child);

        $this->assertSame([0, 40], [$row->x, $row->width]);
    }

    public function test_a_manual_child_that_fits_is_left_alone_and_an_automatic_child_gets_no_row(): void
    {
        $this->move(['x' => 0, 'y' => 0, 'width' => 400, 'height' => 100])->assertOk();

        $row = $this->stored($this->child);

        $this->assertSame([10, 10, 150, 48], [$row->x, $row->y, $row->width, $row->height]);
        $this->assertNull(Layout::query()->where('element_id', $this->automaticChild->id)->first());
    }

    public function test_an_undo_to_automatic_layout_clamps_nothing_and_removes_the_row(): void
    {
        $this->move(null)->assertOk()->assertJsonPath('layout', null);

        $this->assertNull(Layout::query()->where('element_id', $this->block->id)->first());

        $row = $this->stored($this->child);

        $this->assertSame([10, 10, 150, 48], [$row->x, $row->y, $row->width, $row->height]);
    }

    public function test_the_response_carries_the_stored_layout_of_the_block(): void
    {
        $this->move(['x' => 60, 'y' => 20, 'width' => 400, 'height' => 100, 'z' => 3, 'style' => ['fill' => '#F6E6CC']])
            ->assertOk()
            ->assertJsonPath('layout.x', 0)
            ->assertJsonPath('layout.y', 20)
            ->assertJsonPath('layout.width', self::TARGET_WIDTH)
            ->assertJsonPath('layout.height', 100)
            ->assertJsonPath('layout.z', 3)
            ->assertJsonPath('layout.style.fill', '#F6E6CC');
    }

    public function test_a_refused_block_rolls_back_the_move_and_the_rewritten_children(): void
    {
        $this->row($this->child, 20, 10, 400, 48);
        $parent = (string) $this->block->parent_id;

        $this->move(['x' => 0, 'y' => 0, 'width' => 500, 'height' => 10])->assertUnprocessable()->assertJsonValidationErrors('rule');

        $this->assertSame($parent, (string) $this->block->fresh()?->parent_id);

        $row = $this->stored($this->child);

        $this->assertSame([20, 10, 400, 48], [$row->x, $row->y, $row->width, $row->height]);
    }

    /** @param array<string, mixed> $style */
    private function row(Element $element, int $x, int $y, int $width, int $height, int $z = 0, array $style = []): void
    {
        Layout::query()->updateOrCreate(
            ['element_id' => $element->id],
            ['canvas_id' => $this->canvas->id, 'x' => $x, 'y' => $y, 'width' => $width, 'height' => $height, 'z' => $z, 'style' => $style],
        );
    }

    private function stored(Element $element): Layout
    {
        return Layout::query()->where('element_id', $element->id)->firstOrFail();
    }

    /**
     * @param  ?array<string, mixed>  $layout
     * @param  array<string, mixed>  $values
     * @return TestResponse<Response>
     */
    private function move(?array $layout, array $values = []): TestResponse
    {
        $this->canvas->refresh();

        return $this->actingAs($this->administrator)->postJson('/canvases/'.$this->canvas->id.'/drawing/moves', array_replace([
            'element_id' => (string) $this->block->id,
            'target_id' => (string) $this->target->id,
            'structure_version' => (int) $this->canvas->structure_version,
            'layout_version' => (int) $this->canvas->layout_version,
            'layout' => $layout,
        ], $values));
    }
}
