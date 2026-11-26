<?php

namespace Tests\Feature\Security;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

class RouteEnumerationTest extends TestCase
{
    use RefreshDatabase;

    private const EXEMPT = [
        'sign-in',
        'sign-in/second-factor',
        'sign-out',
        'password/new',
        'account',
        'account/*',
        'session/keep-alive',
        'session/reauthenticate',
    ];

    private const WRITE_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    public function test_every_write_route_outside_the_allowlist_carries_the_gate(): void
    {
        $this->assertSame([], $this->unguarded());
    }

    public function test_a_planted_write_route_without_the_gate_is_caught(): void
    {
        Route::post('/planted', fn () => response('ok'));
        Route::delete('/planted/{id}', fn () => response('ok'));

        $this->assertSame(['DELETE planted/{id}', 'POST planted'], $this->unguarded());
    }

    public function test_a_guarded_route_an_allowlisted_route_and_a_read_route_pass(): void
    {
        Route::middleware('can:administer')->post('/planted', fn () => response('ok'));
        Route::put('/account/password', fn () => response('ok'));
        Route::post('/sign-out', fn () => response('ok'));
        Route::get('/planted-reading', fn () => response('ok'));

        $this->assertSame([], $this->unguarded());
    }

    public function test_a_reader_is_refused_on_a_guarded_write_route_and_nothing_changes(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Operating model 2027']);
        $this->guardedWriteRoute();

        $this->actingAs(User::factory()->create())->patch('/planted/'.$canvas->id)->assertForbidden();

        $this->assertSame('Operating model 2027', $canvas->fresh()?->name);
    }

    public function test_a_guest_is_refused_on_a_guarded_write_route(): void
    {
        $this->guardedWriteRoute();

        $this->patch('/planted/'.Canvas::factory()->create()->id)->assertForbidden();
    }

    public function test_an_administrator_passes_the_same_route(): void
    {
        $canvas = Canvas::factory()->create(['name' => 'Operating model 2027']);
        $this->guardedWriteRoute();

        $this->actingAs(User::factory()->administrator()->create())->patch('/planted/'.$canvas->id)->assertOk();

        $this->assertSame('Gewijzigd', $canvas->fresh()?->name);
    }

    private function guardedWriteRoute(): void
    {
        Route::middleware('can:administer')->patch('/planted/{canvas}', function (string $canvas) {
            Canvas::findOrFail($canvas)->update(['name' => 'Gewijzigd']);

            return response('ok');
        });
    }

    /** @return list<string> */
    private function unguarded(): array
    {
        $found = [];

        foreach (Route::getRoutes()->getRoutes() as $route) {
            $methods = array_values(array_intersect($route->methods(), self::WRITE_METHODS));

            if ($methods === [] || Str::is(self::EXEMPT, $route->uri()) || in_array('can:administer', $route->gatherMiddleware(), true)) {
                continue;
            }

            $found[] = implode(',', $methods).' '.$route->uri();
        }

        sort($found);

        return $found;
    }
}
