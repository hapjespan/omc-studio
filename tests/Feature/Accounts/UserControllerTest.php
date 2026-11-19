<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_list_holds_every_user_by_name_with_the_own_row_and_the_second_factor(): void
    {
        $administrator = User::factory()->administrator()->create([
            'name' => 'Bea', 'email' => 'bea@example.test', 'two_factor_confirmed_at' => Carbon::now(),
        ]);
        User::factory()->create(['name' => 'Ann', 'email' => 'ann@example.test']);

        $this->actingAs($administrator)->get('/admin/users')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Users', false)
                ->has('users', 2)
                ->where('users.0.name', 'Ann')
                ->where('users.0.email', 'ann@example.test')
                ->where('users.0.role', Role::Reader->value)
                ->where('users.0.isSelf', false)
                ->where('users.0.hasSecondFactor', false)
                ->where('users.1.name', 'Bea')
                ->where('users.1.role', Role::Administrator->value)
                ->where('users.1.isSelf', true)
                ->where('users.1.hasSecondFactor', true)
                ->where('oneTimePassword', null));
    }

    public function test_a_new_reader_gets_a_one_time_password_shown_once(): void
    {
        $this->actingAs(User::factory()->administrator()->create());

        $this->post('/admin/users', ['name' => 'Ann', 'email' => 'Ann@Example.org', 'role' => 'reader'])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status', __('users.created'));

        $password = (string) session('one_time_password');
        $this->assertSame(16, strlen($password));
        $this->assertDatabaseHas('users', ['email' => 'ann@example.org', 'role' => 'reader']);

        $this->get('/admin/users')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('oneTimePassword', $password));

        $this->get('/admin/users')
            ->assertInertia(fn (AssertableInertia $page) => $page->where('oneTimePassword', null));
    }

    public function test_the_administrator_changes_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->from('/admin/users')
            ->patch('/admin/users/'.$user->getKey(), ['name' => 'Ann', 'email' => 'ann@example.org', 'role' => 'administrator'])
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status', __('users.updated'));

        $user->refresh();
        $this->assertSame('Ann', $user->name);
        $this->assertSame('ann@example.org', $user->email);
        $this->assertSame(Role::Administrator, $user->role);
    }

    public function test_the_administrator_deletes_a_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs(User::factory()->administrator()->create())
            ->delete('/admin/users/'.$user->getKey())
            ->assertRedirect('/admin/users')
            ->assertSessionHas('status', __('users.deleted'));

        $this->assertDatabaseMissing('users', ['id' => $user->getKey()]);
    }

    public function test_a_reader_is_refused_on_every_route_and_nothing_changes(): void
    {
        $reader = User::factory()->create();
        $this->actingAs($reader);

        $this->get('/admin/users')->assertForbidden();
        $this->post('/admin/users', ['name' => 'Ann', 'email' => 'ann@example.org', 'role' => 'reader'])->assertForbidden();
        $this->patch('/admin/users/'.$reader->getKey(), ['name' => 'Ann', 'email' => 'ann@example.org', 'role' => 'administrator'])->assertForbidden();
        $this->delete('/admin/users/'.$reader->getKey())->assertForbidden();

        $this->assertDatabaseCount('users', 1);
        $this->assertSame(Role::Reader, $reader->fresh()?->role);
    }

    public function test_a_direct_request_on_the_own_account_is_refused(): void
    {
        $administrator = User::factory()->administrator()->create();
        User::factory()->administrator()->create();
        $message = (string) __('users.own_account');

        $this->actingAs($administrator)->from('/admin/users')
            ->patch('/admin/users/'.$administrator->getKey(), [
                'name' => $administrator->name, 'email' => $administrator->email, 'role' => 'reader',
            ])
            ->assertSessionHasErrors(['rule' => $message]);

        $this->from('/admin/users')->delete('/admin/users/'.$administrator->getKey())
            ->assertSessionHasErrors(['rule' => $message]);

        $this->assertSame(Role::Administrator, $administrator->fresh()?->role);
        $this->assertDatabaseCount('users', 2);
    }
}
