<?php

namespace Tests\Feature\Shell;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
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

    public function test_a_template_opens_in_entry_mode_for_an_administrator_and_view_mode_for_a_reader(): void
    {
        $template = Canvas::factory()->template()->create();

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$template->id}")->assertRedirect("/canvases/{$template->id}/entry");
        $this->actingAs(User::factory()->create())->get("/canvases/{$template->id}")->assertRedirect("/canvases/{$template->id}/view");
    }

    public function test_an_archived_template_opens_in_entry_mode_for_an_administrator(): void
    {
        $template = Canvas::factory()->template()->create(['archived_at' => now()]);

        $this->actingAs(User::factory()->administrator()->create())->get("/canvases/{$template->id}")->assertRedirect("/canvases/{$template->id}/entry");
    }

    public function test_the_shared_canvas_prop_holds_kind_and_archived_state_and_allows_a_template_without_organisation(): void
    {
        $canvas = Canvas::factory()->create(['archived_at' => now()]);
        $template = Canvas::factory()->template()->create();
        $request = fn (Canvas $model) => tap(Request::create("/canvases/{$model->id}"), function (Request $request) use ($model) {
            $request->setLaravelSession($this->app->make('session.store'));
            $route = (new Route('GET', '/canvases/{canvas}', []))->bind($request);
            $route->setParameter('canvas', $model);
            $request->setRouteResolver(fn () => $route);
        });

        $shared = (new HandleInertiaRequests)->share($request($canvas));
        $this->assertSame(['id' => $canvas->id, 'name' => $canvas->name, 'kind' => 'canvas', 'archived' => true, 'organisation' => ['id' => $canvas->organisation->id, 'name' => $canvas->organisation->name, 'is_sample' => false]], $shared['canvas']);

        $sample = Canvas::factory()->create(['organisation_id' => Organisation::factory()->create(['is_sample' => true])->id]);
        $this->assertTrue((new HandleInertiaRequests)->share($request($sample))['canvas']['organisation']['is_sample']);

        $shared = (new HandleInertiaRequests)->share($request($template));
        $this->assertSame(['id' => $template->id, 'name' => $template->name, 'kind' => 'template', 'archived' => false, 'organisation' => null], $shared['canvas']);
    }
}
