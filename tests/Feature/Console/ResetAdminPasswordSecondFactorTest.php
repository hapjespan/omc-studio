<?php

namespace Tests\Feature\Console;

use App\Models\RecoveryCode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ResetAdminPasswordSecondFactorTest extends TestCase
{
    use RefreshDatabase;

    private function administratorWithFactor(): User
    {
        $administrator = User::factory()->administrator()->create(['email' => 'boss@example.test']);
        $administrator->forceFill(['two_factor_secret' => 'secret', 'two_factor_confirmed_at' => now(), 'two_factor_last_time_step' => 7])->save();
        RecoveryCode::query()->create(['user_id' => $administrator->id, 'code_hash' => 'abcde-fghjk']);

        return $administrator;
    }

    public function test_the_option_removes_the_factor_and_the_recovery_codes(): void
    {
        $administrator = $this->administratorWithFactor();

        $this->assertSame(0, Artisan::call('app:reset-admin-password', ['email' => 'boss@example.test', '--second-factor' => true]));

        $administrator->refresh();
        $this->assertNull($administrator->two_factor_secret);
        $this->assertNull($administrator->two_factor_confirmed_at);
        $this->assertNull($administrator->two_factor_last_time_step);
        $this->assertSame(0, RecoveryCode::query()->where('user_id', $administrator->id)->count());
    }

    public function test_without_the_option_the_factor_stays(): void
    {
        $administrator = $this->administratorWithFactor();

        Artisan::call('app:reset-admin-password', ['email' => 'boss@example.test']);

        $administrator->refresh();
        $this->assertNotNull($administrator->two_factor_secret);
        $this->assertNotNull($administrator->two_factor_confirmed_at);
        $this->assertSame(1, RecoveryCode::query()->where('user_id', $administrator->id)->count());
    }
}
