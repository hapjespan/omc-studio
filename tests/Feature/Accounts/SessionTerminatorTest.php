<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SecondFactorService;
use App\Services\Accounts\SessionTerminator;
use App\Services\Accounts\TotpVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;
use Tests\Support\StoresSessions;
use Tests\TestCase;

class SessionTerminatorTest extends TestCase
{
    use RefreshDatabase;
    use StoresSessions;

    private const PASSWORD = 'a-long-enough-secret';

    private const SECRET = 'ADUMJO5634NPDEKW';

    private const THIS_DEVICE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SECOND_DEVICE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const OTHER_USER = 'cccccccccccccccccccccccccccccccccccccccc';

    protected function setUp(): void
    {
        parent::setUp();

        config(['session.driver' => 'database']);
        session()->setId(self::THIS_DEVICE);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_ending_every_session_leaves_other_users_alone(): void
    {
        $user = $this->onTwoDevices();
        $this->storedSession(self::OTHER_USER, User::factory()->create());

        $this->assertSame(2, app(SessionTerminator::class)->endAll($user));

        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_USER]);
    }

    public function test_ending_the_other_sessions_keeps_the_current_one(): void
    {
        $user = $this->onTwoDevices();
        $this->storedSession(self::OTHER_USER, User::factory()->create());

        $this->assertSame(1, app(SessionTerminator::class)->endOthers($user, self::THIS_DEVICE));

        $this->assertDatabaseHas('sessions', ['id' => self::THIS_DEVICE]);
        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_USER]);
        $this->assertDatabaseMissing('sessions', ['id' => self::SECOND_DEVICE]);
    }

    public function test_turning_the_factor_on_ends_the_session_on_the_other_device(): void
    {
        $log = $this->emptySecurityLog();
        $user = $this->onTwoDevices();
        $started = app(SecondFactorService::class)->begin($user, self::PASSWORD);

        app(SecondFactorService::class)->confirm($user, $this->code($started['secret']), self::THIS_DEVICE);

        $this->assertDatabaseHas('sessions', ['id' => self::THIS_DEVICE]);
        $this->assertDatabaseMissing('sessions', ['id' => self::SECOND_DEVICE]);
        $this->assertStringContainsString('second_factor_enabled', (string) File::get($log));
    }

    public function test_turning_the_factor_off_removes_it_with_its_codes_and_ends_the_other_session(): void
    {
        $log = $this->emptySecurityLog();
        $user = $this->withActiveFactor();
        app(RecoveryCodes::class)->regenerate($user);

        app(SecondFactorService::class)->disable($user, self::PASSWORD, self::THIS_DEVICE);

        $this->assertNull($user->fresh()?->two_factor_secret);
        $this->assertNull($user->fresh()?->two_factor_confirmed_at);
        $this->assertNull($user->fresh()?->two_factor_last_time_step);
        $this->assertSame(0, app(RecoveryCodes::class)->remaining($user));
        $this->assertDatabaseHas('sessions', ['id' => self::THIS_DEVICE]);
        $this->assertDatabaseMissing('sessions', ['id' => self::SECOND_DEVICE]);
        $this->assertStringContainsString('second_factor_disabled', (string) File::get($log));
    }

    public function test_turning_the_factor_off_with_a_wrong_password_changes_nothing(): void
    {
        $user = $this->withActiveFactor();

        $this->expectException(ValidationException::class);

        try {
            app(SecondFactorService::class)->disable($user, 'not-the-password', self::THIS_DEVICE);
        } finally {
            $this->assertNotNull($user->fresh()?->two_factor_confirmed_at);
            $this->assertDatabaseHas('sessions', ['id' => self::SECOND_DEVICE]);
        }
    }

    public function test_new_recovery_codes_invalidate_the_earlier_ones_and_end_the_other_session(): void
    {
        $log = $this->emptySecurityLog();
        $user = $this->withActiveFactor();
        $earlier = app(RecoveryCodes::class)->regenerate($user);

        $later = app(SecondFactorService::class)->regenerate($user, self::PASSWORD, self::THIS_DEVICE);

        $this->assertCount(8, $later);
        $this->assertFalse(app(RecoveryCodes::class)->consume($user, $earlier[0]));
        $this->assertDatabaseHas('sessions', ['id' => self::THIS_DEVICE]);
        $this->assertDatabaseMissing('sessions', ['id' => self::SECOND_DEVICE]);
        $this->assertStringContainsString('recovery_codes_regenerated', (string) File::get($log));
    }

    public function test_new_recovery_codes_need_an_active_factor(): void
    {
        $this->expectException(ValidationException::class);

        app(SecondFactorService::class)->regenerate($this->onTwoDevices(), self::PASSWORD, self::THIS_DEVICE);
    }

    public function test_turning_off_a_factor_that_is_already_off_is_refused(): void
    {
        $this->expectException(ValidationException::class);

        app(SecondFactorService::class)->disable($this->onTwoDevices(), self::PASSWORD, self::THIS_DEVICE);
    }

    public function test_new_recovery_codes_with_a_wrong_password_keep_the_earlier_ones(): void
    {
        $user = $this->withActiveFactor();
        $earlier = app(RecoveryCodes::class)->regenerate($user);

        try {
            app(SecondFactorService::class)->regenerate($user, 'not-the-password', self::THIS_DEVICE);
            $this->fail('The wrong password was accepted.');
        } catch (ValidationException) {
            $this->assertTrue(app(RecoveryCodes::class)->consume($user, $earlier[0]));
            $this->assertDatabaseHas('sessions', ['id' => self::SECOND_DEVICE]);
        }
    }

    public function test_the_security_log_holds_the_count_and_no_address(): void
    {
        $log = $this->emptySecurityLog();
        $user = $this->withActiveFactor('writer@example.test');

        app(SecondFactorService::class)->disable($user, self::PASSWORD, self::THIS_DEVICE);

        $written = (string) File::get($log);
        $this->assertStringContainsString('"sessions_ended":1', $written);
        $this->assertStringNotContainsString('writer@example.test', $written);
    }

    private function onTwoDevices(?string $email = null): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD] + array_filter(['email' => $email]));
        $this->storedSession(self::THIS_DEVICE, $user);
        $this->storedSession(self::SECOND_DEVICE, $user);

        return $user;
    }

    private function withActiveFactor(?string $email = null): User
    {
        $user = $this->onTwoDevices($email);
        $user->fill(['two_factor_secret' => self::SECRET, 'two_factor_confirmed_at' => Carbon::now()])->save();

        return $user;
    }

    private function emptySecurityLog(): string
    {
        $log = storage_path('logs/security-'.Carbon::now()->format('Y-m-d').'.log');
        File::delete($log);

        return $log;
    }

    private function code(string $secret): string
    {
        return (new Google2FA)->oathTotp($secret, app(TotpVerifier::class)->step());
    }
}
