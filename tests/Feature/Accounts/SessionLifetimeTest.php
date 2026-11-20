<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class SessionLifetimeTest extends TestCase
{
    use RefreshDatabase;

    private const LIVE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const STALE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const ENDED = 'Je sessie is na 12 uur beëindigd. Log opnieuw in';

    protected function setUp(): void
    {
        parent::setUp();

        FrozenClock::freeze('2026-11-19 09:00:00');
        $this->withoutVite();
        Route::middleware(['web', 'auth'])->get('/probe', fn () => response('ok'));
    }

    protected function tearDown(): void
    {
        FrozenClock::release();

        parent::tearDown();
    }

    public function test_a_session_signed_in_eleven_hours_fifty_nine_minutes_ago_stays(): void
    {
        $this->signedInAt('-11 hours -59 minutes')->get('/probe')->assertOk();

        $this->assertAuthenticated();
    }

    public function test_a_session_exactly_twelve_hours_old_stays(): void
    {
        $this->signedInAt('-12 hours')->get('/probe')->assertOk();

        $this->assertAuthenticated();
    }

    public function test_a_session_past_twelve_hours_ends_with_the_message(): void
    {
        $this->signedInAt('-12 hours -1 second')->get('/probe')
            ->assertRedirect('/sign-in')
            ->assertSessionHas('status', self::ENDED);

        $this->assertGuest();
    }

    public function test_a_session_at_twelve_hours_and_one_minute_ends(): void
    {
        $this->signedInAt('-12 hours -1 minute')->get('/probe')->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_an_authenticated_session_without_a_sign_in_time_ends(): void
    {
        $this->actingAs(User::factory()->create())->withSession(['signed_in_at' => null])->get('/probe')->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_the_browser_history_is_cleared_when_the_absolute_limit_ends_a_session(): void
    {
        $this->signedInAt('-13 hours')->get('/probe')->assertSessionHas('inertia.clear_history', true);
    }

    public function test_the_sign_in_screen_shows_the_message_after_an_absolute_end(): void
    {
        $this->signedInAt('-13 hours')->get('/probe');

        $this->get('/sign-in')->assertOk()->assertInertia(
            fn (AssertableInertia $page) => $page->component('Auth/SignIn')->where('flash.status', self::ENDED)
        );
    }

    public function test_the_page_is_returned_to_after_signing_in_again(): void
    {
        $user = User::factory()->create(['password' => 'a-long-enough-secret']);

        $this->signedInAt('-13 hours', $user)->get('/probe')->assertRedirect('/sign-in');

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'a-long-enough-secret'])->assertRedirect('/probe');
    }

    public function test_a_state_changing_request_past_the_limit_leads_to_the_sign_in_screen_alone(): void
    {
        Route::middleware(['web', 'auth'])->post('/probe', fn () => response('ok'));

        $this->signedInAt('-13 hours')->post('/probe')->assertRedirect('/sign-in');

        $this->assertNull(session('url.intended'));
    }

    public function test_the_absolute_end_is_written_to_the_security_log_without_the_address(): void
    {
        $log = storage_path('logs/security-'.date('Y-m-d').'.log');
        File::delete($log);

        $this->signedInAt('-13 hours', User::factory()->create(['email' => 'reader@example.test']))->get('/probe');

        $written = (string) File::get($log);
        $this->assertStringContainsString('session_expired_absolute', $written);
        $this->assertStringNotContainsString('reader@example.test', $written);
    }

    public function test_a_session_idle_for_a_hundred_and_nineteen_minutes_is_still_there(): void
    {
        $user = $this->storedSession(self::LIVE, 119);

        $this->withCookie(config('session.cookie'), self::LIVE)->get('/probe')->assertOk();

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_session_idle_for_a_hundred_and_twenty_one_minutes_is_gone_and_leads_back_to_the_page(): void
    {
        $user = $this->storedSession(self::STALE, 121);

        $this->withCookie(config('session.cookie'), self::STALE)->get('/probe')->assertRedirect('/sign-in');

        $this->assertGuest();

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'a-long-enough-secret'])->assertRedirect('/probe');
    }

    public function test_an_idle_end_shows_no_message_on_the_sign_in_screen(): void
    {
        $this->storedSession(self::STALE, 121);

        $this->withCookie(config('session.cookie'), self::STALE)->get('/probe');

        $this->get('/sign-in')->assertInertia(fn (AssertableInertia $page) => $page->where('flash.status', null));
    }

    private function signedInAt(string $moment, ?User $user = null): static
    {
        $user ??= User::factory()->create();

        return $this->actingAs($user)->withSession(['signed_in_at' => now()->modify($moment)->getTimestamp()]);
    }

    private function storedSession(string $id, int $idleMinutes): User
    {
        config(['session.driver' => 'database']);
        $user = User::factory()->create(['password' => 'a-long-enough-secret']);

        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'payload' => base64_encode((string) json_encode([
                Auth::guard('web')->getName() => $user->id,
                'signed_in_at' => now()->getTimestamp(),
            ])),
            'last_activity' => now()->subMinutes($idleMinutes)->getTimestamp(),
        ]);

        return $user;
    }
}
