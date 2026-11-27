<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Accounts\PasswordService;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SignInService;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class FullPassphraseTest extends TestCase
{
    use RefreshDatabase;

    private function legacy(string $password): User
    {
        $user = User::factory()->create();
        DB::table('users')->where('id', $user->id)->update(['password' => password_hash($password, PASSWORD_BCRYPT, ['cost' => 4]), 'must_change_password' => false]);

        return $user->refresh();
    }

    /** @return array<string, array{string}> */
    public static function passphrases(): array
    {
        return [
            'ascii 72 bytes' => [str_repeat('a', 71).'Q'],
            'ascii 73 bytes' => [str_repeat('a', 72).'Q'],
            'ascii 128 chars' => [str_repeat('a', 127).'Q'],
            'multibyte 128 chars' => [str_repeat('é', 127).'Q'],
        ];
    }

    #[DataProvider('passphrases')]
    public function test_new_credentials_use_argon2id_and_cover_every_character(string $passphrase): void
    {
        $user = User::factory()->create(['password' => $passphrase]);

        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertTrue(Hash::check($passphrase, $user->password));
        $this->assertFalse(Hash::check(substr($passphrase, 0, -1).'Z', $user->password));
    }

    #[DataProvider('passphrases')]
    public function test_sign_in_and_reauthentication_use_the_whole_passphrase(string $passphrase): void
    {
        $user = User::factory()->create(['password' => $passphrase]);

        $this->post('/sign-in', ['email' => $user->email, 'password' => substr($passphrase, 0, -1).'Z'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->post('/sign-in', ['email' => $user->email, 'password' => $passphrase]);
        $this->assertAuthenticatedAs($user);

        $this->post('/session/reauthenticate', ['password' => substr($passphrase, 0, -1).'Z'])->assertSessionHasErrors('password');
        $this->post('/session/reauthenticate', ['password' => $passphrase])->assertSessionHasNoErrors();
    }

    public function test_changing_a_legacy_password_beyond_byte_72_is_accepted(): void
    {
        $old = str_repeat('a', 72).'X';
        $user = $this->legacy($old);
        session()->start();

        app(PasswordService::class)->change($user, $old, str_repeat('a', 72).'Y', app('session.store'));

        $this->assertStringStartsWith('$argon2id$', $user->refresh()->password);
        $this->assertTrue(Hash::check(str_repeat('a', 72).'Y', $user->password));
        $this->assertFalse(Hash::check(str_repeat('a', 72).'X', $user->password));
    }

    public function test_a_legacy_account_signs_in_once_then_must_change_its_passphrase(): void
    {
        $user = $this->legacy('Legacy-passphrase-1');

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'Legacy-passphrase-1']);

        $this->assertAuthenticatedAs($user);
        $this->assertTrue($user->refresh()->must_change_password);
        $this->get('/')->assertRedirect('/password/new');

        $this->put('/password/new', ['password' => 'Brand-new-passphrase-2', 'password_confirmation' => 'Brand-new-passphrase-2']);

        $user->refresh();
        $this->assertFalse($user->must_change_password);
        $this->assertStringStartsWith('$argon2id$', $user->password);
    }

    public function test_an_argon2id_account_is_not_forced_to_change(): void
    {
        $user = User::factory()->create(['password' => 'Modern-passphrase-1']);

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'Modern-passphrase-1']);

        $this->assertFalse($user->refresh()->must_change_password);
    }

    public function test_a_wrong_password_never_flags_a_legacy_account(): void
    {
        $user = $this->legacy('Legacy-passphrase-1');

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'Wrong-passphrase-123']);

        $this->assertFalse($user->refresh()->must_change_password);
    }

    public function test_legacy_recovery_codes_keep_working_and_new_ones_use_argon2id(): void
    {
        $user = User::factory()->create();
        DB::table('two_factor_recovery_codes')->insert(['user_id' => $user->id, 'code_hash' => password_hash('abcde-23456', PASSWORD_BCRYPT, ['cost' => 4])]);
        $codes = app(RecoveryCodes::class);

        $this->assertTrue($codes->consume($user, 'abcde-23456'));
        $this->assertFalse($codes->consume($user, 'abcde-23456'));

        $codes->regenerate($user);
        $this->assertStringStartsWith('$argon2id$', RecoveryCode::query()->where('user_id', $user->id)->firstOrFail()->code_hash);
    }

    public function test_one_time_passwords_use_argon2id(): void
    {
        $user = User::factory()->create();

        app(OneTimePassword::class)->issueTo($user);

        $this->assertStringStartsWith('$argon2id$', $user->refresh()->password);
    }

    public function test_the_dummy_hashes_use_the_configured_production_cost(): void
    {
        $argon = password_get_info((new \ReflectionClassConstant(SignInService::class, 'ABSENT'))->getValue());
        $bcrypt = password_get_info((new \ReflectionClassConstant(SignInService::class, 'ABSENT_BCRYPT'))->getValue());
        $defaults = ['memory_cost' => 65536, 'time_cost' => 4, 'threads' => 1];

        $this->assertSame('argon2id', $argon['algoName']);
        $this->assertSame($defaults, $argon['options']);
        $this->assertSame('bcrypt', $bcrypt['algoName']);
        $this->assertSame(12, $bcrypt['options']['cost']);
    }

    /** @return array<string, array{string, bool}> */
    public static function signInPaths(): array
    {
        return [
            'unknown address' => ['unknown', true],
            'argon2id account right password' => ['argon', true],
            'argon2id account wrong password' => ['argon', false],
            'legacy account right password' => ['legacy', true],
            'legacy account wrong password' => ['legacy', false],
        ];
    }

    #[DataProvider('signInPaths')]
    public function test_every_sign_in_path_checks_one_bcrypt_and_one_argon2id_hash(string $kind, bool $right): void
    {
        $user = match ($kind) {
            'argon' => User::factory()->create(['password' => 'Modern-passphrase-1']),
            'legacy' => $this->legacy('Modern-passphrase-1'),
            default => null,
        };
        $seen = [];
        Hash::partialMock()->shouldReceive('check')->andReturnUsing(function (string $plain, ?string $hash) use (&$seen): bool {
            $seen[] = str_starts_with((string) $hash, '$2y$') ? 'bcrypt' : 'argon2id';

            return password_verify($plain, (string) $hash);
        });

        $this->post('/sign-in', ['email' => $user === null ? 'nobody@example.test' : $user->email, 'password' => $right ? 'Modern-passphrase-1' : 'Wrong-passphrase-123']);

        sort($seen);
        $this->assertSame(['argon2id', 'bcrypt'], $seen);
    }

    public function test_setup_and_change_store_the_whole_passphrase_through_the_routes(): void
    {
        foreach ([str_repeat('é', 36), str_repeat('é', 37)] as $passphrase) {
            $user = $this->legacy('Legacy-passphrase-1');
            $this->post('/sign-in', ['email' => $user->email, 'password' => 'Legacy-passphrase-1']);
            $this->put('/password/new', ['password' => $passphrase, 'password_confirmation' => $passphrase])->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check($passphrase, $user->refresh()->password));
            $this->assertStringStartsWith('$argon2id$', $user->password);
            $this->assertFalse(Hash::check(str_repeat('é', 36).'x', $user->password));

            $next = $passphrase.'Z';
            $this->put('/account/password', ['current_password' => $passphrase, 'password' => $next, 'password_confirmation' => $next])->assertSessionHasNoErrors();
            $this->assertTrue(Hash::check($next, $user->refresh()->password));
            $this->assertFalse(Hash::check($passphrase.'Y', $user->password));
            $this->post('/sign-out');
        }
    }

    public function test_a_legacy_account_with_a_second_factor_is_flagged_before_the_challenge_and_lands_on_the_new_passphrase_screen(): void
    {
        $secret = 'ADUMJO5634NPDEKW';
        $user = $this->legacy('Legacy-passphrase-1');
        $user->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()])->save();

        $this->post('/sign-in', ['email' => $user->email, 'password' => 'Legacy-passphrase-1'])->assertRedirect('/sign-in/second-factor');
        $this->assertTrue($user->refresh()->must_change_password);
        $this->assertGuest();

        $code = (new Google2FA)->oathTotp($secret, app(TotpVerifier::class)->step());
        $this->post('/sign-in/second-factor', ['code' => $code]);

        $this->assertAuthenticatedAs($user);
        $this->get('/')->assertRedirect('/password/new');
    }

    public function test_the_flag_is_logged_once_when_it_flips_and_not_for_other_accounts(): void
    {
        $log = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        File::delete($log);
        $legacy = $this->legacy('Legacy-passphrase-1');
        $modern = User::factory()->create(['password' => 'Modern-passphrase-1']);

        $this->post('/sign-in', ['email' => $modern->email, 'password' => 'Modern-passphrase-1']);
        $this->assertStringNotContainsString('legacy_password_flagged', (string) File::get($log));
        $this->post('/sign-out');

        $this->post('/sign-in', ['email' => $legacy->email, 'password' => 'Legacy-passphrase-1']);
        $this->post('/sign-out');
        $this->post('/sign-in', ['email' => $legacy->email, 'password' => 'Legacy-passphrase-1']);

        $this->assertSame(1, substr_count((string) File::get($log), 'legacy_password_flagged'));
    }

    public function test_an_administrator_reset_replaces_a_legacy_hash_with_argon2id(): void
    {
        $user = $this->legacy('Legacy-passphrase-1');
        DB::table('users')->where('id', $user->id)->update(['role' => Role::Administrator->value]);

        $this->artisan('app:reset-admin-password', ['email' => $user->email])->assertSuccessful();

        $user->refresh();
        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertTrue($user->must_change_password);
    }
}
