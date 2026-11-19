<?php

namespace Tests\Feature\Console;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ResetAdminPasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERN = '/^One-time password: (\S{16})$/m';

    public function test_a_new_one_time_password_is_printed_once_and_must_be_replaced(): void
    {
        $administrator = User::factory()->administrator()->create(['email' => 'boss@example.test', 'password' => 'the-old-password']);

        $this->assertSame(0, Artisan::call('app:reset-admin-password', ['email' => 'boss@example.test']));

        $output = Artisan::output();
        $this->assertSame(1, preg_match_all(self::PATTERN, $output, $found));
        $administrator->refresh();
        $this->assertTrue(Hash::check($found[1][0], $administrator->password));
        $this->assertFalse(Hash::check('the-old-password', $administrator->password));
        $this->assertTrue($administrator->must_change_password);
        $this->assertNotNull($administrator->one_time_password_expires_at);
    }

    public function test_the_address_is_matched_whatever_its_case(): void
    {
        User::factory()->administrator()->create(['email' => 'boss@example.test']);

        $this->artisan('app:reset-admin-password', ['email' => 'Boss@Example.test'])->assertSuccessful();
    }

    public function test_an_unknown_address_fails_and_changes_nothing(): void
    {
        $administrator = User::factory()->administrator()->create(['password' => 'the-old-password']);

        $this->assertSame(1, Artisan::call('app:reset-admin-password', ['email' => 'nobody@example.test']));

        $this->assertTrue(Hash::check('the-old-password', $administrator->fresh()->password));
        $this->assertStringContainsString('Geen Administrator met dit e-mailadres', Artisan::output());
        $this->assertStringNotContainsString('One-time password', Artisan::output());
    }

    public function test_a_reader_address_fails_and_changes_nothing(): void
    {
        $reader = User::factory()->create(['email' => 'reader@example.test', 'password' => 'the-old-password']);

        $this->artisan('app:reset-admin-password', ['email' => 'reader@example.test'])->assertFailed();

        $reader->refresh();
        $this->assertTrue(Hash::check('the-old-password', $reader->password));
        $this->assertFalse($reader->must_change_password);
    }

    public function test_the_reset_is_written_to_the_security_log_without_the_address_or_password(): void
    {
        $log = storage_path('logs/security-'.now()->format('Y-m-d').'.log');
        File::delete($log);
        User::factory()->administrator()->create(['email' => 'boss@example.test']);

        Artisan::call('app:reset-admin-password', ['email' => 'boss@example.test']);

        preg_match(self::PATTERN, Artisan::output(), $found);
        $written = (string) File::get($log);
        $this->assertStringContainsString('admin_password_reset', $written);
        $this->assertStringNotContainsString('boss@example.test', $written);
        $this->assertStringNotContainsString($found[1], $written);
    }
}
