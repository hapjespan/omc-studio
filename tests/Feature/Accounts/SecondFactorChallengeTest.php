<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecondFactorChallengeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    private const SECRET = 'ADUMJO5634NPDEKW';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_password_alone_starts_no_session_and_leads_to_the_second_step(): void
    {
        $user = $this->user();

        $this->post('/sign-in', ['email' => $user->email, 'password' => self::PASSWORD])
            ->assertRedirect('/sign-in/second-factor');

        $this->assertGuest();
    }

    public function test_the_second_step_renders_for_a_pending_sign_in(): void
    {
        $this->signInWithPassword();

        $this->get('/sign-in/second-factor')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Auth/SecondFactor'));
    }

    public function test_without_a_pending_sign_in_the_second_step_sends_back_to_the_sign_in_screen(): void
    {
        $this->get('/sign-in/second-factor')->assertRedirect('/sign-in');
        $this->post('/sign-in/second-factor', ['code' => '000000'])->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_a_valid_code_starts_the_session_on_the_page_the_user_asked_for(): void
    {
        Route::middleware(['web', 'auth'])->get('/probe', fn () => response('ok'));
        $user = $this->user();
        $this->get('/probe')->assertRedirect('/sign-in');
        $this->post('/sign-in', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->post('/sign-in/second-factor', ['code' => $this->code()])->assertRedirect('/probe');

        $this->assertAuthenticatedAs($user);
    }

    public function test_a_recovery_code_signs_in_once_and_leaves_seven(): void
    {
        $user = $this->user();
        $codes = app(RecoveryCodes::class)->regenerate($user);
        $this->post('/sign-in', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->post('/sign-in/second-factor', ['code' => $codes[0]])->assertRedirect('/');

        $this->assertAuthenticatedAs($user);
        $this->assertSame(7, app(RecoveryCodes::class)->remaining($user));
    }

    public function test_a_used_recovery_code_does_not_sign_in_again(): void
    {
        $user = $this->user();
        $codes = app(RecoveryCodes::class)->regenerate($user);
        app(RecoveryCodes::class)->consume($user, $codes[0]);
        $this->signInWithPassword($user);

        $this->post('/sign-in/second-factor', ['code' => $codes[0]])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_wrong_code_keeps_the_user_out(): void
    {
        $this->signInWithPassword();

        $this->from('/sign-in/second-factor')->post('/sign-in/second-factor', ['code' => $this->wrongCode()])
            ->assertRedirect('/sign-in/second-factor')
            ->assertSessionHasErrors(['code' => 'Code: deze code of herstelcode is onjuist']);

        $this->assertGuest();
    }

    public function test_the_same_code_twice_names_the_reuse(): void
    {
        $user = $this->user();
        $code = $this->code();
        $this->signInWithPassword($user);
        $this->post('/sign-in/second-factor', ['code' => $code]);
        $this->post('/sign-out');
        $this->signInWithPassword($user);

        $this->post('/sign-in/second-factor', ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Code: deze code is al gebruikt, wacht op de volgende code']);

        $this->assertGuest();
    }

    public function test_the_sixth_wrong_code_within_a_minute_states_the_waiting_time(): void
    {
        $user = $this->user();
        $this->signInWithPassword($user);

        foreach (range(1, 5) as $attempt) {
            $this->post('/sign-in/second-factor', ['code' => $this->wrongCode()]);
        }

        $this->post('/sign-in/second-factor', ['code' => $this->code()])
            ->assertSessionHasErrors(['code' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);

        $this->assertGuest();
    }

    public function test_a_reused_code_counts_towards_the_waiting_time(): void
    {
        $user = $this->user();
        $code = $this->code();
        $this->signInWithPassword($user);
        $this->post('/sign-in/second-factor', ['code' => $code]);
        $this->post('/sign-out');
        $this->signInWithPassword($user);

        foreach (range(1, 5) as $attempt) {
            $this->post('/sign-in/second-factor', ['code' => $code]);
        }

        $this->post('/sign-in/second-factor', ['code' => $code])
            ->assertSessionHasErrors(['code' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);

        $this->assertGuest();
    }

    public function test_an_empty_code_is_refused(): void
    {
        $this->signInWithPassword();

        $this->post('/sign-in/second-factor', [])->assertSessionHasErrors('code');

        $this->assertGuest();
    }

    public function test_a_password_change_in_between_ends_the_pending_sign_in(): void
    {
        $user = $this->signInWithPassword();
        $user->fill(['password' => 'another-long-secret'])->save();

        $this->post('/sign-in/second-factor', ['code' => $this->code()])->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_a_factor_removed_in_between_ends_the_pending_sign_in(): void
    {
        $user = $this->signInWithPassword();
        $user->fill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();

        $this->get('/sign-in/second-factor')->assertRedirect('/sign-in');
        $this->post('/sign-in/second-factor', ['code' => '000000'])->assertRedirect('/sign-in');

        $this->assertGuest();
    }

    public function test_the_waiting_time_holds_for_the_account_in_a_fresh_session(): void
    {
        $user = $this->user();
        $this->signInWithPassword($user);

        foreach (range(1, 5) as $attempt) {
            $this->post('/sign-in/second-factor', ['code' => $this->wrongCode()]);
        }

        $this->flushSession();
        $this->signInWithPassword($user);

        $this->post('/sign-in/second-factor', ['code' => $this->code()])
            ->assertSessionHasErrors(['code' => 'Te veel pogingen. Probeer het over 60 seconden opnieuw']);

        $this->assertGuest();
    }

    public function test_the_session_identifier_changes_at_the_password_step_and_at_the_second_step(): void
    {
        $user = $this->user();

        $this->get('/sign-in');
        $guest = session()->getId();

        $this->post('/sign-in', ['email' => $user->email, 'password' => self::PASSWORD]);
        $pending = session()->getId();

        $this->post('/sign-in/second-factor', ['code' => $this->code()]);

        $this->assertNotSame($guest, $pending);
        $this->assertNotSame($pending, session()->getId());
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_security_log_records_the_step_without_the_address(): void
    {
        $log = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        File::delete($log);
        $user = $this->user('writer@example.test');
        $this->signInWithPassword($user);

        $this->post('/sign-in/second-factor', ['code' => $this->code()]);

        $written = (string) File::get($log);
        $this->assertStringContainsString('second_factor_required', $written);
        $this->assertStringContainsString('sign_in_succeeded', $written);
        $this->assertStringNotContainsString('writer@example.test', $written);
    }

    private function signInWithPassword(?User $user = null): User
    {
        $user ??= $this->user();
        $this->post('/sign-in', ['email' => $user->email, 'password' => self::PASSWORD]);

        return $user;
    }

    private function user(?string $email = null): User
    {
        return User::factory()->create([
            'password' => self::PASSWORD,
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => Carbon::now(),
        ] + array_filter(['email' => $email]));
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
