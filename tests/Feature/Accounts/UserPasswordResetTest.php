<?php

namespace Tests\Feature\Accounts;

use App\Models\AuditEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    private const FIRST_DEVICE = 'dddddddddddddddddddddddddddddddddddddddd';

    private const SECOND_DEVICE = 'eeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeeee';

    private const OTHER_USER = 'ffffffffffffffffffffffffffffffffffffffff';

    public function test_a_reset_shows_a_new_password_once_ends_the_sessions_and_retires_the_old_password(): void
    {
        $user = $this->signedInOnTwoDevices();
        $untouched = User::factory()->create();
        $this->storedSession(self::OTHER_USER, $untouched);

        $response = $this->actingAs(User::factory()->administrator()->create())
            ->post('/admin/users/'.$user->getKey().'/password-reset');

        $response->assertRedirect('/admin/users');
        $password = (string) session('one_time_password');
        $user->refresh();

        $this->assertSame(16, strlen($password));
        $this->assertTrue(Hash::check($password, (string) $user->password));
        $this->assertFalse(Hash::check(self::PASSWORD, (string) $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->one_time_password_expires_at);
        $this->assertSame(0, DB::table('sessions')->where('user_id', $user->getKey())->count());
        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_USER]);
        $this->assertSame(1, AuditEntry::query()->where('subject_id', $user->getKey())->where('action', 'password_changed')->count());
    }

    public function test_a_reader_is_refused_and_nothing_changes(): void
    {
        $user = $this->signedInOnTwoDevices();

        $this->actingAs(User::factory()->create())
            ->post('/admin/users/'.$user->getKey().'/password-reset')
            ->assertForbidden();

        $this->assertTrue(Hash::check(self::PASSWORD, (string) $user->fresh()?->password));
        $this->assertNull(session('one_time_password'));
        $this->assertDatabaseHas('sessions', ['id' => self::FIRST_DEVICE]);
    }

    public function test_the_security_log_holds_the_reset_with_the_count_and_no_address(): void
    {
        $log = storage_path('logs/security-'.Carbon::now()->format('Y-m-d').'.log');
        File::delete($log);
        $user = $this->signedInOnTwoDevices('reader@example.test');

        $this->actingAs(User::factory()->administrator()->create())
            ->post('/admin/users/'.$user->getKey().'/password-reset');

        $written = (string) File::get($log);
        $this->assertStringContainsString('user_password_reset', $written);
        $this->assertStringContainsString('"sessions_ended":2', $written);
        $this->assertStringNotContainsString('reader@example.test', $written);
    }

    private function signedInOnTwoDevices(?string $email = null): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD] + array_filter(['email' => $email]));
        $this->storedSession(self::FIRST_DEVICE, $user);
        $this->storedSession(self::SECOND_DEVICE, $user);

        return $user;
    }

    private function storedSession(string $id, User $user): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->getKey(),
            'payload' => base64_encode((string) json_encode([Auth::guard('web')->getName() => $user->getKey()])),
            'last_activity' => Carbon::now()->getTimestamp(),
        ]);
    }
}
