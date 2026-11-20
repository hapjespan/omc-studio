<?php

namespace Tests\Feature\Layout;

use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use App\Services\Structure\ElementTree;
use Tests\Feature\Structure\StructureTestCase;

class ParentSelectResetTest extends StructureTestCase
{
    private User $administrator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->administrator = User::factory()->administrator()->create();
    }

    public function test_moving_through_the_parent_select_resets_the_element_and_its_subtree(): void
    {
        $first = $this->domain('primary_activities', 'Inkoop');
        $second = $this->domain('primary_activities', 'Verkoop');
        $cluster = $this->cluster($first);
        $activity = $this->activity($cluster);

        foreach ([$first, $second, $cluster, $activity] as $element) {
            $this->place($element);
        }

        $before = (int) $this->canvas->fresh()->layout_version;

        $this->actingAs($this->administrator)
            ->patch("/canvases/{$this->canvas->id}/elements/{$cluster->id}", ['parent_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($second->id, $cluster->fresh()->parent_id);
        $this->assertEqualsCanonicalizing([$first->id, $second->id], Layout::query()->pluck('element_id')->all());
        $this->assertSame($before + 1, (int) $this->canvas->fresh()->layout_version);
    }

    public function test_the_shared_move_writes_no_layout_and_keeps_the_subtrees_rows(): void
    {
        $first = $this->domain('primary_activities', 'Inkoop');
        $second = $this->domain('primary_activities', 'Verkoop');
        $cluster = $this->cluster($first);
        $activity = $this->activity($cluster);

        foreach ([$cluster, $activity] as $element) {
            $this->place($element);
        }

        $before = (int) $this->canvas->fresh()->layout_version;

        app(ElementTree::class)->move($cluster, $second);

        $this->assertEqualsCanonicalizing([$cluster->id, $activity->id], Layout::query()->pluck('element_id')->all());
        $this->assertSame($before, (int) $this->canvas->fresh()->layout_version);
    }

    public function test_a_parent_select_move_of_an_element_without_rows_leaves_the_layout_version_alone(): void
    {
        $first = $this->domain('primary_activities', 'Inkoop');
        $second = $this->domain('primary_activities', 'Verkoop');
        $activity = $this->activity($first);
        $before = (int) $this->canvas->fresh()->layout_version;

        $this->actingAs($this->administrator)
            ->patch("/canvases/{$this->canvas->id}/elements/{$activity->id}", ['parent_id' => $second->id])
            ->assertRedirect();

        $this->assertSame($before, (int) $this->canvas->fresh()->layout_version);
    }

    private function place(Element $element): void
    {
        Layout::create(['canvas_id' => $element->canvas_id, 'element_id' => $element->id, 'x' => 8, 'y' => 8, 'width' => 100, 'height' => 40, 'z' => 0, 'style' => []]);
    }
}
