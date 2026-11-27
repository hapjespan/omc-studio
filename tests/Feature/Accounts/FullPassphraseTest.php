<?php

namespace Tests\Feature\Accounts;

use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Accounts\PasswordService;
use App\Services\Accounts\RecoveryCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
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

    public function test_unknown_accounts_are_refused_with_an_argon2id_dummy(): void
    {
        $this->post('/sign-in', ['email' => 'nobody@example.test', 'password' => str_repeat('a', 100)])->assertSessionHasErrors('email');

        $dummy = (new \ReflectionClassConstant(\App\Services\Accounts\SignInService::class, 'ABSENT'))->getValue();
        $this->assertStringStartsWith('$argon2id$', $dummy);
    }

    public function test_an_administrator_reset_replaces_a_legacy_hash_with_argon2id(): void
    {
        $user = $this->legacy('Legacy-passphrase-1');
        DB::table('users')->where('id', $user->id)->update(['role' => \App\Enums\Role::Administrator->value]);

        $this->artisan('app:reset-admin-password', ['email' => $user->email])->assertSuccessful();

        $user->refresh();
        $this->assertStringStartsWith('$argon2id$', $user->password);
        $this->assertTrue($user->must_change_password);
    }
}
