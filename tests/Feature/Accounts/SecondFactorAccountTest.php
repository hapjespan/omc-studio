<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecondFactorAccountTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_account_screen_shows_the_factor_as_off(): void
    {
        $this->actingAs($this->user())->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('secondFactor.active', false)
                ->where('secondFactor.enrolment', null)
                ->missingFlash('recoveryCodes')
                ->etc());
    }

    public function test_a_guest_reaches_none_of_the_factor_routes(): void
    {
        $this->post('/account/second-factor')->assertRedirect('/sign-in');
        $this->post('/account/second-factor/confirm')->assertRedirect('/sign-in');
        $this->post('/account/second-factor/recovery-codes')->assertRedirect('/sign-in');
        $this->delete('/account/second-factor')->assertRedirect('/sign-in');
    }

    public function test_the_password_starts_the_enrolment_and_the_screen_shows_the_key_and_the_qr_code(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/account/second-factor', ['current_password' => self::PASSWORD])
            ->assertRedirect('/account');

        $this->actingAs($user)->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('secondFactor.active', false)
                ->where('secondFactor.enrolment.secret', $user->fresh()?->two_factor_secret)
                ->where('secondFactor.enrolment.qr', fn (string $qr) => str_starts_with($qr, 'data:image/svg+xml;base64,'))
                ->etc());
    }

    public function test_a_wrong_password_starts_nothing(): void
    {
        $user = $this->user();

        $this->actingAs($user)->post('/account/second-factor', ['current_password' => 'not-the-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertNull($user->fresh()?->two_factor_secret);
    }

    public function test_a_missing_password_is_refused(): void
    {
        $this->actingAs($this->user())->post('/account/second-factor', [])->assertSessionHasErrors('current_password');
    }

    public function test_the_code_turns_the_factor_on_and_shows_eight_codes_once(): void
    {
        $user = $this->user();
        $secret = $this->begin($user);

        $this->actingAs($user)->post('/account/second-factor/confirm', ['code' => $this->code($secret)])
            ->assertRedirect('/account')
            ->assertSessionHas('status', 'Tweede factor staat aan');

        $this->assertNotNull($user->fresh()?->two_factor_confirmed_at);

        $this->actingAs($user)->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('secondFactor.active', true)
                ->where('secondFactor.remainingLabel', 'Nog 8 herstelcodes')
                ->where('secondFactor.enrolment', null)
                ->hasFlash('recoveryCodes')
                ->etc());

        $this->actingAs($user)->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page->missingFlash('recoveryCodes')->etc());
    }

    public function test_another_session_does_not_see_the_started_enrolment(): void
    {
        $user = $this->user();
        $this->begin($user);

        $this->flushSession();

        $this->actingAs($user)->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('secondFactor.enrolment', null)->etc());

        $this->assertNotNull($user->fresh()?->two_factor_secret);
    }

    public function test_a_wrong_code_leaves_the_factor_off(): void
    {
        $user = $this->user();
        $this->begin($user);

        $this->actingAs($user)->post('/account/second-factor/confirm', ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNull($user->fresh()?->two_factor_confirmed_at);
    }

    public function test_new_recovery_codes_replace_the_earlier_ones(): void
    {
        $user = $this->active();
        $earlier = app(RecoveryCodes::class)->regenerate($user);

        $this->actingAs($user)->post('/account/second-factor/recovery-codes', ['current_password' => self::PASSWORD])
            ->assertRedirect('/account')
            ->assertSessionHas('status', 'Nieuwe herstelcodes aangemaakt');

        $this->actingAs($user)->get('/account')
            ->assertInertia(fn (AssertableInertia $page) => $page->hasFlash('recoveryCodes')->etc());

        $this->assertFalse(app(RecoveryCodes::class)->consume($user, $earlier[0]));
        $this->assertSame(8, app(RecoveryCodes::class)->remaining($user));
    }

    public function test_turning_the_factor_off_removes_it_with_its_codes(): void
    {
        $user = $this->active();
        app(RecoveryCodes::class)->regenerate($user);

        $this->actingAs($user)->delete('/account/second-factor', ['current_password' => self::PASSWORD])
            ->assertRedirect('/account')
            ->assertSessionHas('status', 'Tweede factor staat uit');

        $this->assertNull($user->fresh()?->two_factor_confirmed_at);
        $this->assertNull($user->fresh()?->two_factor_secret);
        $this->assertSame(0, app(RecoveryCodes::class)->remaining($user));
    }

    public function test_turning_the_factor_off_with_a_wrong_password_keeps_it(): void
    {
        $user = $this->active();

        $this->actingAs($user)->delete('/account/second-factor', ['current_password' => 'not-the-password'])
            ->assertSessionHasErrors('current_password');

        $this->assertNotNull($user->fresh()?->two_factor_confirmed_at);
    }

    public function test_new_recovery_codes_need_an_active_factor(): void
    {
        $this->actingAs($this->user())->post('/account/second-factor/recovery-codes', ['current_password' => self::PASSWORD])
            ->assertSessionHasErrors('rule');
    }

    private function begin(User $user): string
    {
        $this->actingAs($user)->post('/account/second-factor', ['current_password' => self::PASSWORD]);

        return (string) $user->fresh()?->two_factor_secret;
    }

    private function active(): User
    {
        $user = $this->user();
        $user->fill(['two_factor_secret' => 'ADUMJO5634NPDEKW', 'two_factor_confirmed_at' => Carbon::now()])->save();

        return $user;
    }

    private function user(): User
    {
        return User::factory()->create(['password' => self::PASSWORD]);
    }

    private function code(string $secret): string
    {
        return (new Google2FA)->oathTotp($secret, app(TotpVerifier::class)->step());
    }
}
