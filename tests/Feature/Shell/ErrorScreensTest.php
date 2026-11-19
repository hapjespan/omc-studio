<?php

namespace Tests\Feature\Shell;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ErrorScreensTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reader_requesting_an_administrator_screen_sees_the_forbidden_screen(): void
    {
        $this->actingAs(User::factory()->create())->get('/admin')
            ->assertForbidden()
            ->assertSee('Geen toegang')
            ->assertSee('<h1>', false)
            ->assertSee('Overzicht');
    }

    public function test_an_unknown_address_shows_the_not_found_screen_with_a_link_to_the_overview(): void
    {
        $this->actingAs(User::factory()->create())->get('/bestaat-niet')
            ->assertNotFound()
            ->assertSee('Pagina niet gevonden')
            ->assertSee('<a href="/">Overzicht</a>', false)
            ->assertSee('lang="nl"', false);
    }

    public function test_a_server_error_shows_a_plain_screen_without_trace_query_or_path(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/probe-error', fn () => throw new \RuntimeException('secret-detail'));

        $response = $this->actingAs(User::factory()->create())->get('/probe-error?token=abc123');

        $response->assertStatus(500)->assertSee('Er is iets misgegaan');
        foreach (['secret-detail', 'RuntimeException', 'abc123', 'probe-error', 'vendor/', '.php'] as $leak) {
            $response->assertDontSee($leak, false);
        }
    }

    public function test_a_refused_screen_request_is_recorded_in_the_security_log(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });
        $reader = User::factory()->create();

        $this->actingAs($reader)->get('/admin')->assertForbidden();

        $entries = array_values(array_filter($logged, fn (MessageLogged $e) => $e->message === 'refused_screen'));
        $this->assertCount(1, $entries);
        $this->assertSame($reader->id, $entries[0]->context['user_id']);
        $this->assertSame('admin', $entries[0]->context['route']);
        $this->assertStringNotContainsString($reader->email, (string) json_encode($entries[0]->context));
    }

    public function test_a_found_screen_and_a_missing_one_log_no_refusal(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $this->actingAs(User::factory()->create())->get('/bestaat-niet')->assertNotFound();

        $this->assertSame([], array_filter($logged, fn (MessageLogged $e) => $e->message === 'refused_screen'));
    }

    public function test_an_expired_page_is_shown_in_dutch_with_a_link_to_the_overview(): void
    {
        Route::middleware('web')->get('/probe-expired', fn () => abort(419));

        $this->get('/probe-expired')->assertStatus(419)->assertSee('Pagina verlopen')->assertSee('<a href="/">Overzicht</a>', false);
    }
}
