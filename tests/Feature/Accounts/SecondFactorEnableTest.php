<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\SecondFactorService;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class SecondFactorEnableTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    public function test_beginning_returns_a_secret_and_a_qr_code_made_inside_the_application(): void
    {
        $user = $this->user();

        $started = $this->service()->begin($user, self::PASSWORD);

        $this->assertMatchesRegularExpression('/^[A-Z2-7]{32}$/', $started['secret']);
        $this->assertStringStartsWith('data:image/svg+xml;base64,', $started['qr']);

        $svg = (string) base64_decode(substr($started['qr'], strlen('data:image/svg+xml;base64,')), true);
        $this->assertStringContainsString('<svg', $svg);
        $this->assertStringNotContainsString('<image', $svg);
        $this->assertDoesNotMatchRegularExpression('/(href|src)=/', $svg);
    }

    public function test_the_factor_is_not_active_before_a_code_confirms_it(): void
    {
        $user = $this->user();

        $this->service()->begin($user, self::PASSWORD);

        $this->assertNull($user->fresh()?->two_factor_confirmed_at);
    }

    public function test_the_secret_is_stored_encrypted_with_the_application_key(): void
    {
        $user = $this->user();

        $started = $this->service()->begin($user, self::PASSWORD);

        $stored = (string) DB::table('users')->where('id', $user->getKey())->value('two_factor_secret');
        $this->assertNotSame($started['secret'], $stored);
        $this->assertSame($started['secret'], Crypt::decryptString($stored));
    }

    public function test_a_wrong_password_refuses_the_start_and_stores_nothing(): void
    {
        $user = $this->user();

        try {
            $this->service()->begin($user, 'not-the-password');
            $this->fail('The wrong password was accepted.');
        } catch (ValidationException $refusal) {
            $this->assertArrayHasKey('current_password', $refusal->errors());
        }

        $this->assertNull($user->fresh()?->two_factor_secret);
    }

    public function test_a_valid_code_activates_the_factor(): void
    {
        $user = $this->user();
        $started = $this->service()->begin($user, self::PASSWORD);

        $this->service()->confirm($user, $this->code($started['secret']), 'this-session');

        $this->assertNotNull($user->fresh()?->two_factor_confirmed_at);
    }

    public function test_a_wrong_code_leaves_the_factor_inactive(): void
    {
        $user = $this->user();
        $started = $this->service()->begin($user, self::PASSWORD);

        try {
            $this->service()->confirm($user, $this->wrongCode($started['secret']), 'this-session');
            $this->fail('The wrong code was accepted.');
        } catch (ValidationException $refusal) {
            $this->assertArrayHasKey('code', $refusal->errors());
        }

        $this->assertNull($user->fresh()?->two_factor_confirmed_at);
    }

    public function test_confirming_without_a_started_enrolment_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        $this->service()->confirm($this->user(), '000000', 'this-session');
    }

    public function test_starting_again_while_the_factor_is_active_is_refused(): void
    {
        $user = $this->user();
        $started = $this->service()->begin($user, self::PASSWORD);
        $this->service()->confirm($user, $this->code($started['secret']), 'this-session');

        $this->expectException(ValidationException::class);

        $this->service()->begin($user, self::PASSWORD);
    }

    public function test_confirming_again_while_the_factor_is_active_is_refused(): void
    {
        $user = $this->user();
        $started = $this->service()->begin($user, self::PASSWORD);
        $this->service()->confirm($user, $this->code($started['secret']), 'this-session');
        $confirmed = $user->fresh()?->two_factor_confirmed_at;

        try {
            $this->service()->confirm($user, $this->code($started['secret']), 'this-session');
            $this->fail('The second confirmation was accepted.');
        } catch (ValidationException $refusal) {
            $this->assertSame(['Tweede factor staat al aan'], $refusal->errors()['rule']);
        }

        $this->assertEquals($confirmed, $user->fresh()?->two_factor_confirmed_at);
    }

    public function test_the_sixth_password_attempt_is_refused_even_with_the_right_password(): void
    {
        $user = $this->user();

        foreach (range(1, 5) as $attempt) {
            try {
                $this->service()->begin($user, 'not-the-password');
            } catch (ValidationException) {
                continue;
            }
        }

        try {
            $this->service()->begin($user, self::PASSWORD);
            $this->fail('The sixth attempt was accepted.');
        } catch (ValidationException $refusal) {
            $this->assertSame(['Te veel pogingen. Probeer het over 60 seconden opnieuw'], $refusal->errors()['current_password']);
        }

        $this->assertNull($user->fresh()?->two_factor_secret);
    }

    private function service(): SecondFactorService
    {
        return app(SecondFactorService::class);
    }

    private function code(string $secret): string
    {
        return (new Google2FA)->oathTotp($secret, app(TotpVerifier::class)->step());
    }

    private function wrongCode(string $secret): string
    {
        return str_pad((string) (((int) $this->code($secret) + 500000) % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function user(): User
    {
        return User::factory()->create(['password' => self::PASSWORD]);
    }
}
