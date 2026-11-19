<?php

namespace Tests\Feature\Accounts;

use App\Enums\Role;
use App\Http\Requests\UserRequest;
use App\Models\AuditEntry;
use App\Models\User;
use App\Services\Accounts\UserAdministration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserAdministrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, string>  $data
     * @return array<string, array<int, string>>
     */
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

    private function securityLog(): string
    {
        return storage_path('logs/security-'.Carbon::now()->format('Y-m-d').'.log');
    }

    public function test_creating_a_user_is_written_to_the_security_log_without_the_address(): void
    {
        File::delete($this->securityLog());
        $this->actingAs($admin = User::factory()->administrator()->create());

        [$user] = $this->app->make(UserAdministration::class)->create(['name' => 'Ann', 'email' => 'ann.log@example.org', 'role' => 'reader']);

        $written = (string) File::get($this->securityLog());
        $this->assertStringContainsString('user_created', $written);
        $this->assertStringContainsString('"subject_id":'.$user->getKey(), $written);
        $this->assertStringContainsString('"user_id":'.$admin->getKey(), $written);
        $this->assertStringNotContainsString('ann.log@example.org', $written);
    }

    public function test_a_role_change_is_written_to_the_security_log_and_a_plain_edit_is_not(): void
    {
        File::delete($this->securityLog());
        $this->actingAs(User::factory()->administrator()->create());
        $user = User::factory()->create(['role' => Role::Reader]);
        $service = $this->app->make(UserAdministration::class);

        $service->update($user, ['name' => 'Renamed', 'email' => $user->email, 'role' => 'reader']);
        $this->assertFalse(File::exists($this->securityLog()) && str_contains((string) File::get($this->securityLog()), 'user_role_changed'));

        $service->update($user, ['name' => 'Renamed', 'email' => $user->email, 'role' => 'administrator']);
        $written = (string) File::get($this->securityLog());
        $this->assertStringContainsString('user_role_changed', $written);
        $this->assertStringContainsString('"subject_id":'.$user->getKey(), $written);
        $this->assertStringNotContainsString($user->email, $written);
    }
}
