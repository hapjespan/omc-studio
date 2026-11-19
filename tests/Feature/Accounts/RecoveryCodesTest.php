<?php

namespace Tests\Feature\Accounts;

use App\Models\RecoveryCode;
use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SecondFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class RecoveryCodesTest extends TestCase
{
    use RefreshDatabase;

    public function test_eight_codes_are_handed_out_once_and_stored_as_hashes_only(): void
    {
        $user = User::factory()->create();

        $codes = $this->codes()->regenerate($user);

        $this->assertCount(8, $codes);
        $this->assertSame($codes, array_unique($codes));
        $this->assertSame(8, RecoveryCode::query()->where('user_id', $user->getKey())->count());

        foreach (RecoveryCode::query()->where('user_id', $user->getKey())->pluck('code_hash') as $hash) {
            $this->assertNotContains((string) $hash, $codes);
        }

        $this->assertTrue(Hash::check($codes[0], (string) DB::table('two_factor_recovery_codes')->where('user_id', $user->getKey())->orderBy('id')->value('code_hash')));
    }

    public function test_a_used_code_never_works_again_and_seven_remain(): void
    {
        $user = User::factory()->create();
        $codes = $this->codes()->regenerate($user);

        $this->assertTrue($this->codes()->consume($user, $codes[0]));

        $this->assertFalse($this->codes()->consume($user, $codes[0]));
        $this->assertSame(7, $this->codes()->remaining($user));
        $this->assertSame('Nog 7 herstelcodes', trans_choice('second_factor.remaining_codes', $this->codes()->remaining($user)));
        $this->assertSame('Nog 1 herstelcode', trans_choice('second_factor.remaining_codes', 1));
    }

    public function test_the_other_codes_keep_working_after_one_is_used(): void
    {
        $user = User::factory()->create();
        $codes = $this->codes()->regenerate($user);

        $this->codes()->consume($user, $codes[0]);

        $this->assertTrue($this->codes()->consume($user, $codes[7]));
        $this->assertSame(6, $this->codes()->remaining($user));
    }

    public function test_a_code_is_accepted_whatever_the_case_and_surrounding_space(): void
    {
        $user = User::factory()->create();
        $codes = $this->codes()->regenerate($user);

        $this->assertTrue($this->codes()->consume($user, '  '.mb_strtoupper($codes[0]).' '));
    }

    public function test_regenerating_invalidates_every_earlier_code(): void
    {
        $user = User::factory()->create();
        $earlier = $this->codes()->regenerate($user);

        $later = $this->codes()->regenerate($user);

        $this->assertSame(8, $this->codes()->remaining($user));
        $this->assertFalse($this->codes()->consume($user, $earlier[0]));
        $this->assertTrue($this->codes()->consume($user, $later[0]));
    }

    public function test_a_code_of_another_user_is_refused(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $codes = $this->codes()->regenerate($other);

        $this->assertFalse($this->codes()->consume($user, $codes[0]));
        $this->assertSame(8, $this->codes()->remaining($other));
    }

    public function test_a_code_another_request_used_first_is_refused(): void
    {
        $user = User::factory()->create();
        $codes = $this->codes()->regenerate($user);
        RecoveryCode::retrieved(fn (RecoveryCode $stored) => DB::table('two_factor_recovery_codes')->where('id', $stored->getKey())->delete());

        $this->assertFalse($this->codes()->consume($user, $codes[0]));
        $this->assertSame(0, $this->codes()->remaining($user));
    }

    public function test_an_unknown_code_is_refused_and_removes_nothing(): void
    {
        $user = User::factory()->create();
        $this->codes()->regenerate($user);

        $this->assertFalse($this->codes()->consume($user, 'not-a-code'));
        $this->assertSame(8, $this->codes()->remaining($user));
    }

    public function test_confirming_the_factor_hands_out_the_eight_codes(): void
    {
        $user = User::factory()->create(['password' => 'a-long-enough-secret']);
        $service = app(SecondFactorService::class);
        $started = $service->begin($user, 'a-long-enough-secret');
        $google = new Google2FA;

        $codes = $service->confirm($user, $google->oathTotp($started['secret'], $google->getTimestamp()));

        $this->assertCount(8, $codes);
        $this->assertSame(8, $this->codes()->remaining($user));
    }

    private function codes(): RecoveryCodes
    {
        return app(RecoveryCodes::class);
    }
}
