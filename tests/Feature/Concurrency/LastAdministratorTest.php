<?php

namespace Tests\Feature\Concurrency;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\AdministratorGuard;
use App\Services\Accounts\UserAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\RaceDatabase;
use Tests\Support\TwoProcessRunner;
use Tests\TestCase;

class LastAdministratorTest extends TestCase
{
    use RefreshDatabase;

    private const LAST = 'Er moet minstens één beheerder blijven';

    private const OWN = 'Je kunt je eigen account niet verwijderen of je eigen rol wijzigen';

    private RaceDatabase $race;

    private string $claim;

    protected function setUp(): void
    {
        parent::setUp();

        $this->race = new RaceDatabase;
        $this->claim = sys_get_temp_dir().'/last-administrator-claim-'.getmypid();
    }

    protected function tearDown(): void
    {
        $this->race->clean(['audit_entries', 'users']);

        if (file_exists($this->claim)) {
            unlink($this->claim);
        }

        parent::tearDown();
    }

    public function test_two_administrators_demoting_each_other_end_with_one_success_and_one_refusal(): void
    {
        $environment = $this->race->prepare();

        $first = $this->raceAdministrator('first@example.test');
        $second = $this->raceAdministrator('second@example.test');

        $outcomes = (new TwoProcessRunner)->run(
            base_path('tests/Support/last-administrator-worker.php'),
            [(string) $first->getKey(), (string) $second->getKey(), $this->claim],
            $environment,
        );

        sort($outcomes);

        $this->assertSame([self::LAST, 'ok'], $outcomes);
        $this->assertSame(1, User::on('race')->where('role', Role::Administrator->value)->count());
    }

    public function test_demoting_the_last_administrator_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->assertRefused(self::LAST, fn () => $this->administration()->update($administrator, $this->fields($administrator, Role::Reader)));

        $this->assertSame(Role::Administrator, $administrator->fresh()?->role);
    }

    public function test_demoting_an_administrator_while_another_one_remains_is_allowed(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->administrator()->create();

        $this->administration()->update($administrator, $this->fields($administrator, Role::Reader));

        $this->assertSame(Role::Reader, $administrator->fresh()?->role);
    }

    public function test_changing_your_own_role_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->administrator()->create();

        $this->assertRefused(self::OWN, fn () => $this->administration()->update($administrator, $this->fields($administrator, Role::Reader), $administrator));
    }

    public function test_changing_your_own_name_without_your_role_is_allowed(): void
    {
        $administrator = User::factory()->administrator()->create();

        $this->administration()->update($administrator, ['name' => 'New', 'email' => $administrator->email, 'role' => Role::Administrator->value], $administrator);

        $this->assertSame('New', $administrator->fresh()?->name);
    }

    public function test_deleting_your_own_account_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->administrator()->create();

        $this->assertRefused(self::OWN, fn () => $this->guard()->assertCanDelete($administrator, $administrator));
    }

    public function test_deleting_the_last_administrator_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();
        $reader = User::factory()->create();

        $this->assertRefused(self::LAST, fn () => $this->guard()->assertCanDelete($reader, $administrator));
    }

    public function test_deleting_a_reader_is_allowed(): void
    {
        $this->expectNotToPerformAssertions();

        $administrator = User::factory()->administrator()->create();

        $this->guard()->assertCanDelete($administrator, User::factory()->create());
    }

    private function raceAdministrator(string $email): User
    {
        return User::on('race')->create([
            'name' => 'Race',
            'email' => $email,
            'password' => 'a-long-enough-secret',
            'role' => Role::Administrator,
        ]);
    }

    /** @return array{name: string, email: string, role: string} */
    private function fields(User $user, Role $role): array
    {
        return ['name' => (string) $user->name, 'email' => (string) $user->email, 'role' => $role->value];
    }

    private function assertRefused(string $message, callable $write): void
    {
        try {
            $write();
        } catch (ValidationException $refusal) {
            $this->assertSame([$message], $refusal->errors()['rule']);

            return;
        }

        $this->fail('The write was accepted.');
    }

    private function administration(): UserAdministration
    {
        return $this->app->make(UserAdministration::class);
    }

    private function guard(): AdministratorGuard
    {
        return $this->app->make(AdministratorGuard::class);
    }
}
