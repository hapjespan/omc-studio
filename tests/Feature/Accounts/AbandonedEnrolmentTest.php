<?php

namespace Tests\Feature\Accounts;

use App\Models\AuditEntry;
use App\Models\User;
use App\Services\Accounts\SecondFactorService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbandonedEnrolmentTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    private function started(): User
    {
        $user = User::factory()->create(['password' => self::PASSWORD]);
        AuditEntry::query()->delete();
        app(SecondFactorService::class)->begin($user, self::PASSWORD);

        return $user;
    }

    public function test_beginning_an_enrolment_writes_no_audit_entry_for_a_factor_that_is_not_on(): void
    {
        $user = $this->started();

        $this->assertNotNull($user->fresh()->two_factor_secret);
        $this->assertSame(0, AuditEntry::where('action', 'second_factor_changed')->count());
    }

    public function test_signing_out_discards_a_secret_that_was_never_confirmed(): void
    {
        $user = $this->started();

        $this->actingAs($user)->post('/sign-out');

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_signing_in_again_discards_a_secret_that_was_never_confirmed(): void
    {
        $user = $this->started();

        $this->post('/sign-in', ['email' => $user->email, 'password' => self::PASSWORD]);

        $this->assertNull($user->fresh()->two_factor_secret);
    }

    public function test_a_confirmed_factor_keeps_its_secret_at_sign_out(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);

        $this->actingAs($user)->post('/sign-out');

        $this->assertNotNull($user->fresh()->two_factor_secret);
    }
}
