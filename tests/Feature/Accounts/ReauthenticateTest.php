<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\FrozenClock;
use Tests\TestCase;

class ReauthenticateTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    private const SECRET = 'ADUMJO5634NPDEKW';

    private const ALMOST = '-11 hours -55 minutes';

    protected function setUp(): void
    {
        parent::setUp();

        FrozenClock::freeze('2026-11-19 09:00:00');
        $this->withoutVite();
    }

    protected function tearDown(): void
    {
        FrozenClock::release();

        parent::tearDown();
    }

    public function test_the_right_password_starts_a_new_period_on_the_same_page(): void
    {
        $this->signedIn(self::ALMOST);

        $this->from('/account')->post('/session/reauthenticate', ['password' => self::PASSWORD])
            ->assertRedirect('/account')
            ->assertSessionHasNoErrors();

        $this->assertAuthenticated();
        $this->assertSame(Carbon::now()->getTimestamp(), session('signed_in_at'));
    }

    public function test_the_empty_code_field_of_a_user_without_a_factor_is_ignored(): void
    {
        $this->signedIn(self::ALMOST);

        $this->from('/account')->post('/session/reauthenticate', ['password' => self::PASSWORD, 'code' => ''])
            ->assertRedirect('/account')
            ->assertSessionHasNoErrors();

        $this->assertSame(Carbon::now()->getTimestamp(), session('signed_in_at'));
    }

    public function test_the_new_period_carries_the_next_request_past_the_old_limit(): void
    {
        $user = $this->signedIn(self::ALMOST);
        $this->post('/session/reauthenticate', ['password' => self::PASSWORD]);

        FrozenClock::advance('1 hour');

        $this->get('/account')->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_wrong_password_refuses_and_keeps_the_session_as_it_was(): void
    {
        $this->signedIn(self::ALMOST);

        $this->from('/account')->post('/session/reauthenticate', ['password' => 'not-the-password'])
            ->assertRedirect('/account')
            ->assertSessionHasErrors(['password' => 'Wachtwoord: dit wachtwoord is onjuist']);

        $this->assertAuthenticated();
        $this->assertSame($this->at(self::ALMOST), session('signed_in_at'));
    }

    public function test_an_empty_password_is_refused(): void
    {
        $this->signedIn(self::ALMOST);

        $this->post('/session/reauthenticate', [])
            ->assertSessionHasErrors(['password' => 'Wachtwoord: vul je wachtwoord in']);

        $this->assertSame($this->at(self::ALMOST), session('signed_in_at'));
    }

    public function test_a_code_is_required_when_a_factor_is_active(): void
    {
        $this->signedIn(self::ALMOST, $this->userWithFactor());

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD])
            ->assertSessionHasErrors(['code' => 'Code: vul je code of herstelcode in']);

        $this->assertSame($this->at(self::ALMOST), session('signed_in_at'));
    }

    public function test_the_password_with_a_valid_code_starts_a_new_period(): void
    {
        $this->signedIn(self::ALMOST, $this->userWithFactor());

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD, 'code' => $this->code()])
            ->assertSessionHasNoErrors();

        $this->assertSame(Carbon::now()->getTimestamp(), session('signed_in_at'));
    }

    public function test_a_recovery_code_starts_a_new_period_and_is_used_up(): void
    {
        $user = $this->userWithFactor();
        $codes = app(RecoveryCodes::class)->regenerate($user);
        $this->signedIn(self::ALMOST, $user);

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD, 'code' => $codes[0]])
            ->assertSessionHasNoErrors();

        $this->assertSame(Carbon::now()->getTimestamp(), session('signed_in_at'));
        $this->assertSame(7, app(RecoveryCodes::class)->remaining($user));
    }

    public function test_a_wrong_code_refuses_and_keeps_the_session_as_it_was(): void
    {
        $this->signedIn(self::ALMOST, $this->userWithFactor());

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD, 'code' => $this->wrongCode()])
            ->assertSessionHasErrors(['code' => 'Code: deze code of herstelcode is onjuist']);

        $this->assertAuthenticated();
        $this->assertSame($this->at(self::ALMOST), session('signed_in_at'));
    }

    public function test_a_reused_code_names_the_reuse_and_refuses(): void
    {
        $user = $this->userWithFactor();
        $user->fill(['two_factor_last_time_step' => app(TotpVerifier::class)->step()])->save();
        $this->signedIn(self::ALMOST, $user);

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD, 'code' => $this->code()])
            ->assertSessionHasErrors(['code' => 'Code: deze code is al gebruikt, wacht op de volgende code']);

        $this->assertSame($this->at(self::ALMOST), session('signed_in_at'));
    }

    public function test_the_sixth_wrong_password_within_a_minute_states_the_waiting_time(): void
    {
        $this->signedIn(self::ALMOST);

        foreach (range(1, 5) as $attempt) {
            $this->post('/session/reauthenticate', ['password' => 'not-the-password']);
        }

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD])
            ->assertSessionHasErrors(['password' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);

        $this->assertAuthenticated();
        $this->assertSame($this->at(self::ALMOST), session('signed_in_at'));
    }

    public function test_a_guest_is_sent_to_the_sign_in_screen(): void
    {
        $this->post('/session/reauthenticate', ['password' => self::PASSWORD])->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_the_security_log_records_the_renewal_without_the_address(): void
    {
        $log = storage_path('logs/security-'.date('Y-m-d').'.log');
        File::delete($log);
        $this->signedIn(self::ALMOST, User::factory()->create(['email' => 'writer@example.test', 'password' => self::PASSWORD]));

        $this->post('/session/reauthenticate', ['password' => self::PASSWORD]);

        $written = (string) File::get($log);
        $this->assertStringContainsString('session_reauthenticated', $written);
        $this->assertStringNotContainsString('writer@example.test', $written);
    }

    public function test_a_signed_in_page_carries_the_end_of_the_session_and_the_factor_state(): void
    {
        $this->signedIn(self::ALMOST);

        $this->get('/account')->assertInertia(fn (AssertableInertia $page) => $page
            ->where('auth.sessionEndsAt', $this->at(self::ALMOST) + 12 * 3600)
            ->where('auth.secondFactor', false)
        );
    }

    public function test_a_page_of_a_user_with_a_factor_says_so(): void
    {
        $this->signedIn(self::ALMOST, $this->userWithFactor());

        $this->get('/account')->assertInertia(fn (AssertableInertia $page) => $page->where('auth.secondFactor', true));
    }

    private function signedIn(string $moment, ?User $user = null): User
    {
        $user ??= User::factory()->create(['password' => self::PASSWORD]);
        $this->actingAs($user)->withSession(['signed_in_at' => $this->at($moment)]);

        return $user;
    }

    private function userWithFactor(): User
    {
        return User::factory()->create([
            'password' => self::PASSWORD,
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => Carbon::now(),
        ]);
    }

    private function at(string $moment): int
    {
        return Carbon::now()->modify($moment)->getTimestamp();
    }

    private function code(): string
    {
        return (new Google2FA)->oathTotp(self::SECRET, app(TotpVerifier::class)->step());
    }

    private function wrongCode(): string
    {
        return str_pad((string) (((int) $this->code() + 500000) % 1000000), 6, '0', STR_PAD_LEFT);
    }
}
