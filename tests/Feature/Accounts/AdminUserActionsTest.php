<?php

namespace Tests\Feature\Accounts;

use App\Models\AuditEntry;
use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\StoresSessions;
use Tests\TestCase;

class AdminUserActionsTest extends TestCase
{
    use RefreshDatabase;
    use StoresSessions;

    private const EMAIL = 'target@example.test';

    private const DEVICE = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    private const SECOND_DEVICE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    private const OTHER_DEVICE = 'cccccccccccccccccccccccccccccccccccccccc';

    private const SECRET = 'KRMVATZTJFZUC53FONXW2ZJB';

    public function test_a_reset_removes_the_factor_and_codes_ends_the_sessions_and_records_who(): void
    {
        $target = $this->targetWithSecondFactor();
        $bystander = User::factory()->create();
        $this->storedSession(self::OTHER_DEVICE, $bystander);
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->delete('/admin/users/'.$target->getKey().'/second-factor');

        $response->assertRedirect('/admin/users');
        $this->assertSame(__('users.second_factor_reset'), session('status'));

        $target->refresh();
        $this->assertNull($target->two_factor_secret);
        $this->assertNull($target->two_factor_confirmed_at);
        $this->assertNull($target->two_factor_last_time_step);
        $this->assertSame(0, RecoveryCode::query()->where('user_id', $target->getKey())->count());
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->getKey())->count());
        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_DEVICE]);

        $this->assertSame(1, AuditEntry::query()
            ->where('subject_type', 'user')
            ->where('subject_id', (string) $target->getKey())
            ->where('action', 'second_factor_changed')
            ->where('actor_id', $administrator->getKey())
            ->count());
    }

    public function test_a_reset_of_ones_own_factor_is_refused_and_changes_nothing(): void
    {
        $administrator = $this->targetWithSecondFactor(administrator: true);

        $response = $this->actingAs($administrator)
            ->from('/admin/users')
            ->delete('/admin/users/'.$administrator->getKey().'/second-factor');

        $response->assertRedirect('/admin/users');
        $response->assertSessionHasErrors(['rule' => __('users.own_row_action')]);

        $administrator->refresh();
        $this->assertNotNull($administrator->two_factor_confirmed_at);
        $this->assertSame(RecoveryCodes::COUNT, RecoveryCode::query()->where('user_id', $administrator->getKey())->count());
        $this->assertDatabaseHas('sessions', ['id' => self::DEVICE]);
    }

    public function test_ending_the_sessions_of_another_user_changes_nothing_else_and_records_who(): void
    {
        $target = $this->targetWithSecondFactor();
        $bystander = User::factory()->create();
        $this->storedSession(self::OTHER_DEVICE, $bystander);
        $administrator = User::factory()->administrator()->create();

        $response = $this->actingAs($administrator)->delete('/admin/users/'.$target->getKey().'/sessions');

        $response->assertRedirect('/admin/users');
        $this->assertSame(__('users.sessions_ended'), session('status'));

        $target->refresh();
        $this->assertSame(0, DB::table('sessions')->where('user_id', $target->getKey())->count());
        $this->assertDatabaseHas('sessions', ['id' => self::OTHER_DEVICE]);
        $this->assertNotNull($target->two_factor_confirmed_at);
        $this->assertSame(RecoveryCodes::COUNT, RecoveryCode::query()->where('user_id', $target->getKey())->count());

        $entries = AuditEntry::query()->where('action', 'sessions_ended')->get();
        $this->assertCount(1, $entries);
        $entry = $entries->first();
        $this->assertSame((string) $target->getKey(), $entry->subject_id);
        $this->assertSame($administrator->getKey(), $entry->actor_id);
        $this->assertStringNotContainsString(self::EMAIL, (string) json_encode($entry->getAttributes()));
    }

    public function test_ending_ones_own_sessions_is_refused(): void
    {
        $administrator = $this->targetWithSecondFactor(administrator: true);

        $response = $this->actingAs($administrator)
            ->from('/admin/users')
            ->delete('/admin/users/'.$administrator->getKey().'/sessions');

        $response->assertRedirect('/admin/users');
        $response->assertSessionHasErrors(['rule' => __('users.own_row_action')]);
        $this->assertDatabaseHas('sessions', ['id' => self::DEVICE]);
    }

    public function test_a_reader_is_refused_both_actions(): void
    {
        $target = $this->targetWithSecondFactor();
        $reader = User::factory()->create();

        $this->actingAs($reader)->delete('/admin/users/'.$target->getKey().'/second-factor')->assertForbidden();
        $this->actingAs($reader)->delete('/admin/users/'.$target->getKey().'/sessions')->assertForbidden();

        $this->assertNotNull($target->fresh()?->two_factor_confirmed_at);
        $this->assertDatabaseHas('sessions', ['id' => self::DEVICE]);
    }

    public function test_the_security_log_holds_both_actions_with_the_count_and_no_address(): void
    {
        $log = storage_path('logs/security-'.Carbon::now()->format('Y-m-d').'.log');
        File::delete($log);
        $target = $this->targetWithSecondFactor();
        $administrator = User::factory()->administrator()->create();

        $this->actingAs($administrator)->delete('/admin/users/'.$target->getKey().'/second-factor');
        $this->storedSession(self::DEVICE, $target);
        $this->actingAs($administrator)->delete('/admin/users/'.$target->getKey().'/sessions');

        $written = (string) File::get($log);
        $this->assertStringContainsString('user_second_factor_reset', $written);
        $this->assertStringContainsString('"sessions_ended":2', $written);
        $this->assertStringContainsString('user_sessions_ended', $written);
        $this->assertStringContainsString('"sessions_ended":1', $written);
        $this->assertStringContainsString('"subject_id":'.$target->getKey(), $written);
        $this->assertStringNotContainsString(self::EMAIL, $written);
    }

    private function targetWithSecondFactor(bool $administrator = false): User
    {
        $factory = User::factory();
        $user = ($administrator ? $factory->administrator() : $factory->state(['email' => self::EMAIL]))->create([
            'two_factor_secret' => self::SECRET,
            'two_factor_confirmed_at' => Carbon::now(),
            'two_factor_last_time_step' => 1,
        ]);

        app(RecoveryCodes::class)->regenerate($user);
        $this->storedSession(self::DEVICE, $user);
        $this->storedSession(self::SECOND_DEVICE, $user);

        return $user;
    }
}
