<?php

namespace Tests\Feature\Layout;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DrawingRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_opens_tekenen_with_the_view_data(): void
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

    public function test_a_reader_is_sent_to_bekijken(): void
    {
        $canvas = Canvas::factory()->create();

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}/drawing")->assertRedirect("/canvases/{$canvas->id}/view");
    }

    public function test_an_archived_canvas_is_sent_to_bekijken(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/drawing")->assertRedirect("/canvases/{$canvas->id}/view");
    }

    public function test_a_template_is_sent_to_invoeren(): void
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
