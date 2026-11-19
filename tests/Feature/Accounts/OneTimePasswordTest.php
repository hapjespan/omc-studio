<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\AuditEntry;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Operations\InstallSteps;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class OneTimePasswordTest extends TestCase
{
    use RefreshDatabase;

    private const PATTERN = '/^One-time password: ([2-9A-HJ-NP-Za-km-np-z]{16})$/m';

    private function install(): string
    {
        $output = new BufferedOutput;
        $this->app->make(InstallSteps::class)->run($output);

        return $output->fetch();
    }

    private function printedPassword(string $output): string
    {
        $this->assertSame(1, preg_match(self::PATTERN, $output, $matches), 'no one-time password in '.$output);

        return $matches[1];
    }

    public function test_a_generated_password_is_sixteen_unambiguous_characters_and_never_repeats(): void
    {
        $passwords = array_map(fn () => (new OneTimePassword)->generate(), range(1, 50));

        foreach ($passwords as $password) {
            $this->assertMatchesRegularExpression('/^[2-9A-HJ-NP-Za-km-np-z]{16}$/', $password);
        }

        $this->assertCount(50, array_unique($passwords));
    }

    public function test_issuing_stores_only_a_hash_forces_a_change_and_expires_in_twenty_four_hours(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
        $user = User::factory()->create(['must_change_password' => false]);

        $password = (new OneTimePassword)->issueTo($user);
        $user->refresh();

        $this->assertTrue(Hash::check($password, $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertSame('2026-01-02 12:00:00', $user->one_time_password_expires_at->toDateTimeString());
    }

    public function test_a_one_time_password_expires_after_twenty_four_hours_and_an_account_without_one_never_does(): void
    {
        $this->travelTo(Carbon::parse('2026-01-01 12:00:00'));
        $passwords = new OneTimePassword;
        $user = User::factory()->create();

        $this->assertFalse($passwords->isExpired($user));

        $passwords->issueTo($user);
        $this->travel(24)->hours();
        $this->assertFalse($passwords->isExpired($user));

        $this->travel(1)->second();
        $this->assertTrue($passwords->isExpired($user));
    }

    public function test_the_first_install_creates_one_administrator_and_prints_the_pair_once(): void
    {
        $output = $this->install();

        $user = User::query()->sole();
        $this->assertSame(Role::Administrator, $user->role);
        $this->assertTrue($user->must_change_password);
        $this->assertTrue(Hash::check($this->printedPassword($output), $user->password));
        $this->assertSame(1, substr_count($output, $user->email));
    }

    public function test_a_restart_keeps_the_administrator_and_prints_no_new_password(): void
    {
        $this->install();
        $user = User::query()->sole();

        $second = $this->install();

        $this->assertSame(1, User::query()->count());
        $this->assertSame($user->password, User::query()->sole()->password);
        $this->assertSame('', trim($second));
    }

    public function test_the_audit_trail_holds_no_password_material(): void
    {
        $output = $this->install();
        $user = User::query()->sole();

        $entry = AuditEntry::query()->where('subject_type', 'user')->sole();
        $entries = (string) AuditEntry::query()->get()->toJson();

        $this->assertArrayNotHasKey('password', $entry->changes);
        $this->assertStringNotContainsString($this->printedPassword($output), $entries);
        $this->assertStringNotContainsString($user->password, $entries);
    }
}
