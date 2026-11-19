<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class SignOutTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_out_ends_the_session_and_leads_to_the_sign_in_screen(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/sign-out')->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_the_browser_history_is_cleared_for_the_next_page(): void
    {
        $this->actingAs(User::factory()->create())->post('/sign-out')->assertSessionHas('inertia.clear_history', true);
    }

    public function test_the_session_identifier_is_replaced(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/sign-in');
        $before = session()->getId();

        $this->post('/sign-out');

        $this->assertNotSame($before, session()->getId());
    }

    public function test_the_event_is_written_to_the_security_log_without_the_address(): void
    {
        $log = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        File::delete($log);
        $user = User::factory()->create(['email' => 'reader@example.test']);

        $this->actingAs($user)->post('/sign-out');

        $written = (string) File::get($log);
        $this->assertStringContainsString('sign_out', $written);
        $this->assertStringNotContainsString('reader@example.test', $written);
    }

    public function test_a_guest_cannot_sign_out(): void
    {
        $this->post('/sign-out')->assertRedirect('/sign-in');
    }
}
