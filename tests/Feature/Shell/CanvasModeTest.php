<?php

namespace Tests\Feature\Shell;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CanvasModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_administrator_is_sent_to_entry_mode(): void
    {
        $canvas = Canvas::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}")->assertRedirect("/canvases/{$canvas->id}/entry");
    }

    public function test_a_reader_is_sent_to_view_mode(): void
    {
        $canvas = Canvas::factory()->create();

        $this->actingAs(User::factory()->create())->get("/canvases/{$canvas->id}")->assertRedirect("/canvases/{$canvas->id}/view");
    }

    public function test_an_archived_canvas_opens_in_view_mode_for_an_administrator(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}")->assertRedirect("/canvases/{$canvas->id}/view");
    }

    public function test_an_unknown_canvas_is_not_found_and_a_guest_signs_in(): void
    {
        $this->actingAs(User::factory()->create())->get('/canvases/999')->assertNotFound();
        auth()->logout();
        $this->get('/canvases/1')->assertRedirect('/sign-in');
    }
}
