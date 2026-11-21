<?php

namespace Tests\Feature\Layout;

use App\Enums\ElementKind;
use App\Models\Area;
use App\Models\Canvas;
use App\Models\Element;
use App\Models\Layout;
use App\Models\User;
use App\Services\Layout\LayoutSnapshot;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViewPropsTest extends TestCase
{
    use RefreshDatabase;

    private Canvas $canvas;

    private Element $domain;

    protected function setUp(): void
    {
        parent::setUp();

        (new ReferenceDataStep)();
        $this->canvas = Canvas::factory()->create();
        $this->domain = Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => Area::where('key', 'primary_activities')->value('id'), 'kind' => ElementKind::Domain]);
        Element::factory()->create(['canvas_id' => $this->canvas->id, 'area_id' => Area::where('key', 'primary_activities')->value('id'), 'kind' => ElementKind::Domain]);
        Layout::create(['canvas_id' => $this->canvas->id, 'element_id' => $this->domain->id, 'x' => 40, 'y' => 220, 'width' => 300, 'height' => 120, 'z' => 2, 'style' => ['fill' => '#F6E6CC']]);
        Layout::create(['canvas_id' => $this->canvas->id, 'area_id' => Area::where('key', 'primary_activities')->value('id'), 'x' => 560, 'y' => 400, 'width' => 1100, 'height' => 380, 'z' => 0, 'style' => []]);
    }

    public function test_view_carries_the_stored_layouts_by_element_id_and_area_key(): void
    {
        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view")->assertInertia(fn (Assert $page) => $page
            ->has('layouts', 2)
            ->where('layouts.'.$this->domain->id, ['mode' => 'manual', 'x' => 40, 'y' => 220, 'width' => 300, 'height' => 120, 'z' => 2, 'style' => ['fill' => '#F6E6CC']])
            ->where('layouts.primary_activities.width', 1100));
    }

    public function test_entry_carries_the_stored_layouts(): void
    {
        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$this->canvas->id}/entry")->assertInertia(fn (Assert $page) => $page
            ->component('Canvas/Entry')
            ->has('layouts', 2)
            ->where('layouts.'.$this->domain->id.'.x', 40));
    }

    public function test_an_empty_style_is_carried_as_an_object(): void
    {
        $this->assertStringContainsString('"style":{}', (string) json_encode(app(LayoutSnapshot::class)->for($this->canvas)['primary_activities']));
    }

    public function test_a_canvas_without_layout_carries_none(): void
    {
        $other = Canvas::factory()->create();

        $this->actingAs(User::factory()->create())->get("/canvases/{$other->id}/view")->assertInertia(fn (Assert $page) => $page->has('layouts', 0));
    }

    public function test_another_canvases_layout_is_not_carried(): void
    {
        $other = Canvas::factory()->create();
        $foreign = Element::factory()->create(['canvas_id' => $other->id]);
        Layout::create(['canvas_id' => $other->id, 'element_id' => $foreign->id, 'x' => 1, 'y' => 1, 'width' => 100, 'height' => 50, 'z' => 0, 'style' => []]);

        $this->actingAs(User::factory()->create())->get("/canvases/{$this->canvas->id}/view")->assertInertia(fn (Assert $page) => $page->missing('layouts.'.$foreign->id));
    }
}
