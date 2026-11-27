<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class SignInPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_sign_in_screen_renders_for_a_guest(): void
    {
        $this->get('/sign-in')->assertOk()->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SignIn'));
    }

    public function test_a_signed_in_user_is_sent_on_from_the_sign_in_screen(): void
    {
        $this->actingAs(User::factory()->create())->get('/sign-in')->assertRedirect('/');
    }

    public function test_the_right_credentials_sign_in_and_redirect_home(): void
    {
        $user = User::factory()->create(['password' => 'a-long-enough-secret']);

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'a-long-enough-secret'])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
    }

    public function test_the_page_a_guest_asked_for_is_the_page_after_signing_in(): void
    {
        $user = User::factory()->create(['password' => 'a-long-enough-secret']);

        Route::middleware(['web', 'auth'])->get('/probe', fn () => response('ok'));

        $this->get('/probe')->assertRedirect('/sign-in');
        $this->post('/sign-in', ['email' => $user->email, 'password' => 'a-long-enough-secret'])->assertRedirect('/probe');
    }

    public function test_a_wrong_password_shows_the_one_message_on_the_email_field(): void
    {
        $user = User::factory()->create();

        $this->from('/sign-in')->post('/sign-in', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/sign-in')
            ->assertSessionHasErrors(['email' => 'E-mailadres of wachtwoord is onjuist']);

        $this->assertGuest();
    }

    public function test_the_sixth_attempt_states_the_waiting_time_in_text(): void
    {
        $user = User::factory()->create(['password' => 'a-long-enough-secret']);
        $this->freezeTime();

        foreach (range(1, 5) as $attempt) {
            $this->post('/sign-in', ['email' => $user->email, 'password' => 'wrong']);
        }

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'a-long-enough-secret'])
            ->assertSessionHasErrors(['email' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);
    }

    public function test_missing_fields_are_refused_with_a_message_per_field(): void
    {
        $this->post('/sign-in', [])->assertSessionHasErrors(['email', 'password']);
    }
}
