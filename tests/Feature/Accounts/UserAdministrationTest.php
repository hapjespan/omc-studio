<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\AuditEntry;
use App\Models\User;
use App\Services\Accounts\UserAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    private function validate(array $data, ?User $editing = null): array
    {
        $request = UserRequest::create('/admin/users', 'POST', $data);
        $request->setContainer($this->app)->setRedirector($this->app['redirect']);
        $request->setRouteResolver(fn () => new class($editing)
        {
            public function __construct(private ?User $user) {}

            public function parameter(string $key): ?User
            {
                return $this->user;
            }
        });

        $method = new \ReflectionMethod($request, 'prepareForValidation');
        $method->invoke($request);

        return Validator::make($request->all(), $request->rules(), $request->messages())->errors()->toArray();
    }

    public function test_create_stores_a_lowercase_user_with_a_one_time_password_shown_once(): void
    {
        [$user, $password] = $this->app->make(UserAdministration::class)->create([
            'name' => 'Ann', 'email' => 'Ann@Example.org', 'role' => 'reader',
        ]);

        $this->assertSame('ann@example.org', $user->email);
        $this->assertSame(Role::Reader, $user->role);
        $this->assertSame(16, strlen($password));
        $this->assertTrue(Hash::check($password, (string) $user->password));
        $this->assertTrue($user->must_change_password);
        $this->assertNotNull($user->one_time_password_expires_at);
        $this->assertSame(1, AuditEntry::query()->where('subject_id', $user->getKey())->where('action', 'created')->count());
    }

    public function test_update_changes_the_fields_and_the_role_applies_at_the_next_request(): void
    {
        $user = User::factory()->create();

        $this->app->make(UserAdministration::class)->update($user, ['name' => 'New', 'email' => 'new@example.org', 'role' => 'administrator']);

        $this->actingAs($user->fresh());
        $this->assertTrue($this->app['auth']->user()->can('administer'));
        $this->assertSame('New', $user->fresh()?->name);
        $this->assertSame(1, AuditEntry::query()->where('subject_id', $user->getKey())->where('action', 'changed')->count());
    }

    public function test_an_email_differing_only_in_case_conflicts(): void
    {
        User::factory()->create(['email' => 'a@example.org']);

        $errors = $this->validate(['name' => 'B', 'email' => 'A@Example.org', 'role' => 'reader']);

        $this->assertSame(['E-mailadres: dit adres is al in gebruik'], $errors['email']);
    }

    public function test_saving_a_user_under_its_own_email_is_no_conflict(): void
    {
        $user = User::factory()->create(['email' => 'a@example.org']);

        $this->assertSame([], $this->validate(['name' => 'B', 'email' => 'A@Example.org', 'role' => 'reader'], $user));
    }

    public function test_name_email_and_role_are_required_and_the_role_must_exist(): void
    {
        $errors = $this->validate(['name' => '', 'email' => '', 'role' => 'owner']);

        $this->assertSame(['name', 'email', 'role'], array_keys($errors));
    }
}
