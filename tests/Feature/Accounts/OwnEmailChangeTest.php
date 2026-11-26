<?php

namespace Tests\Feature\Accounts;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OwnEmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'a-long-enough-secret';

    private const OWN = 'bea@example.test';

    private const WANTED = 'other@example.test';

    public function test_a_changed_own_address_without_the_current_password_is_refused(): void
    {
        $this->assertRefused([]);
    }

    public function test_a_changed_own_address_with_an_empty_current_password_is_refused(): void
    {
        $this->assertRefused(['current_password' => '']);
    }

    public function test_a_changed_own_address_with_a_wrong_current_password_is_refused(): void
    {
        $this->assertRefused(['current_password' => 'wrong-but-long-enough']);
    }

    public function test_a_changed_own_address_with_the_current_password_is_saved(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->from('/admin/users')
            ->patch($this->path($administrator), $this->fields(['current_password' => self::PASSWORD]))
            ->assertSessionHasNoErrors()
            ->assertRedirect('/admin/users');

        $this->assertSame(self::WANTED, $administrator->fresh()?->email);
    }

    public function test_a_password_with_surrounding_spaces_is_accepted_as_the_current_password(): void
    {
        $administrator = User::factory()->administrator()->create(['name' => 'Bea', 'email' => self::OWN, 'password' => ' padded secret ']);

        $this->actingAs($administrator)->from('/admin/users')
            ->patch($this->path($administrator), $this->fields(['current_password' => ' padded secret ']))
            ->assertSessionHasNoErrors();

        $this->assertSame(self::WANTED, $administrator->fresh()?->email);
    }

    public function test_the_own_name_changes_without_the_current_password(): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->from('/admin/users')
            ->patch($this->path($administrator), $this->fields(['name' => 'Bea Janssen', 'email' => self::OWN]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Bea Janssen', $administrator->fresh()?->name);
    }

    public function test_the_address_of_another_user_changes_without_the_current_password(): void
    {
        $other = User::factory()->create();

        $this->actingAs($this->administrator())->from('/admin/users')
            ->patch($this->path($other), ['name' => 'Ann', 'email' => self::WANTED, 'role' => 'reader'])
            ->assertSessionHasNoErrors();

        $this->assertSame(self::WANTED, $other->fresh()?->email);
    }

    /** @param array<string, string> $overrides */
    private function assertRefused(array $overrides): void
    {
        $administrator = $this->administrator();

        $this->actingAs($administrator)->from('/admin/users')
            ->patch($this->path($administrator), $this->fields($overrides))
            ->assertSessionHasErrors(['current_password' => __('users.current_password_required')]);

        $this->assertSame(self::OWN, $administrator->fresh()?->email);
    }

    private function administrator(): User
    {
        return User::factory()->administrator()->create(['name' => 'Bea', 'email' => self::OWN, 'password' => self::PASSWORD]);
    }

    private function path(User $user): string
    {
        return '/admin/users/'.$user->getKey();
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function fields(array $overrides = []): array
    {
        return [...['name' => 'Bea', 'email' => self::WANTED, 'role' => 'administrator'], ...$overrides];
    }
}
