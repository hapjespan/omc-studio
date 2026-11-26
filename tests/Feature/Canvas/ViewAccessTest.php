<?php

namespace Tests\Feature\Canvas;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ViewAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_opens_a_canvas_in_view_mode_without_a_write_flag(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Model']);

        foreach ([User::factory()->administrator()->create(), User::factory()->create()] as $user) {
            $this->actingAs($user)->get("/canvases/{$canvas->id}/view")->assertInertia(fn (Assert $page) => $page
                ->component('Canvas/View')
                ->where('canvas.name', 'Model')
                ->where('canvas.archived', false)
                ->where('canWrite', false));
        }
    }

    public function test_the_view_page_carries_the_organisation_for_the_breadcrumb(): void
    {
        $canvas = Canvas::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/view")->assertInertia(fn (Assert $page) => $page
            ->where('canvas.organisation.name', $canvas->organisation->name));
    }

    public function test_the_shell_gets_the_organisation_of_the_canvas(): void
    {
        $canvas = Canvas::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$canvas->id}/view")
            ->assertInertia(fn (Assert $page) => $page->where('canvas.organisation.id', $canvas->organisation_id));
    }

    public function test_an_archived_canvas_and_a_template_open_read_only(): void
    {
        $archived = Canvas::factory()->create(['archived_at' => now()]);
        $template = Canvas::factory()->template()->create();
        $admin = User::factory()->administrator()->create();

        $this->actingAs($admin)->get("/canvases/{$archived->id}/view")->assertInertia(fn (Assert $page) => $page->where('canvas.archived', true)->where('canWrite', false));
        $this->actingAs($admin)->get("/canvases/{$template->id}/view")->assertInertia(fn (Assert $page) => $page->component('Canvas/View')->where('canWrite', false));
    }

    public function test_a_guest_is_sent_to_sign_in_and_an_unknown_canvas_is_404(): void
    {
        $this->get('/canvases/1/view')->assertRedirect('/sign-in');
        $this->actingAs(User::factory()->create())->get('/canvases/999/view')->assertNotFound();
    }
}
