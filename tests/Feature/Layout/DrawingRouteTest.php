<?php

namespace Tests\Feature\Layout;

use App\Models\Area;
use App\Models\Canvas;
use App\Models\Layout;
use App\Models\User;
use App\Services\Operations\Steps\ReferenceDataStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DrawingRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_opens_drawing_with_the_view_data(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Model']);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/drawing")->assertInertia(fn (Assert $page) => $page
            ->component('Drawing/Index')
            ->where('canvas.name', 'Model')
            ->has('layout')
            ->has('elements')
            ->has('statements')
            ->has('modalities'));
    }

    public function test_the_stored_layouts_and_the_real_versions_reach_the_page(): void
    {
        app(ReferenceDataStep::class)();
        $canvas = Canvas::factory()->create();
        $canvas->forceFill(['structure_version' => 7, 'layout_version' => 3])->save();
        $area = Area::query()->where('key', 'primary_activities')->firstOrFail();
        Layout::query()->create(['canvas_id' => $canvas->id, 'area_id' => $area->id, 'element_id' => null, 'x' => 5, 'y' => 200, 'width' => 900, 'height' => 300, 'z' => 0, 'style' => [], 'format_version' => 1]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/drawing")->assertInertia(fn (Assert $page) => $page
            ->where('versions.structure', 7)
            ->where('versions.layout', 3)
            ->where('layouts.primary_activities.width', 900)
            ->where('layouts.primary_activities.mode', 'manual'));
    }

    public function test_a_reader_is_sent_to_view(): void
    {
        $canvas = Canvas::factory()->create();

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}/drawing")->assertRedirect("/canvases/{$canvas->id}/view");
    }

    public function test_an_archived_canvas_is_sent_to_view(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/drawing")->assertRedirect("/canvases/{$canvas->id}/view");
    }

    public function test_a_template_is_sent_to_entry(): void
    {
        $template = Canvas::factory()->template()->create();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$template->id}/drawing")->assertRedirect("/canvases/{$template->id}/entry");
    }

    public function test_a_guest_is_sent_to_sign_in_and_an_unknown_canvas_is_not_found(): void
    {
        $this->get('/canvases/1/drawing')->assertRedirect('/sign-in');
        $this->actingAs(User::factory()->administrator()->create())->get('/canvases/999/drawing')->assertNotFound();
    }
}
