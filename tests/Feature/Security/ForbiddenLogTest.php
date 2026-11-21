<?php

namespace Tests\Feature\Security;

use App\Models\Canvas;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ForbiddenLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refused_write_is_recorded_in_the_security_log_without_personal_data(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });
        $reader = User::factory()->create(['role' => 'reader']);

        $this->actingAs($reader)->post('/undo/abc')->assertForbidden();

        $entries = array_values(array_filter($logged, fn (MessageLogged $e) => $e->message === 'forbidden'));
        $this->assertCount(1, $entries);
        $this->assertSame($reader->id, $entries[0]->context['user_id']);
        $this->assertSame('administer', $entries[0]->context['ability']);
        $this->assertStringNotContainsString($reader->email, (string) json_encode($entries[0]->context));
    }

    public function test_an_allowed_write_logs_nothing(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $this->actingAs(User::factory()->create(['role' => 'administrator']))->post('/undo/abc');

        $this->assertSame([], array_filter($logged, fn (MessageLogged $e) => $e->message === 'forbidden'));
    }

    public function test_a_reader_opening_the_overview_and_the_version_list_writes_no_forbidden_entry(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });
        $canvas = Canvas::factory()->create();

        $this->withoutVite()->actingAs(User::factory()->create(['role' => 'reader']));
        $this->get('/')->assertOk();
        $this->get('/canvases/'.$canvas->getKey().'/versions')->assertOk();

        $this->assertSame([], array_filter($logged, fn (MessageLogged $e) => $e->message === 'forbidden'));
    }

    public function test_a_reader_get_of_an_admin_route_writes_exactly_one_refusal_entry(): void
    {
        $logged = [];
        Log::listen(function (MessageLogged $event) use (&$logged): void {
            $logged[] = $event;
        });

        $this->actingAs(User::factory()->create(['role' => 'reader']))->get('/admin')->assertForbidden();

        $this->assertCount(1, array_filter($logged, fn (MessageLogged $e) => in_array($e->message, ['forbidden', 'refused_screen'], true)));
    }
}
