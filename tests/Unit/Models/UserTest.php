<?php

namespace Tests\Unit\Models;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_email_is_stored_lower_cased_and_trimmed(): void
    {
        $user = User::factory()->create(['email' => '  Jan.Jansen@Example.TEST ']);

        $this->assertSame('jan.jansen@example.test', $user->fresh()->email);
    }

    public function test_the_email_is_unique(): void
    {
        User::factory()->create(['email' => 'a@example.test']);

        $this->expectException(UniqueConstraintViolationException::class);

        User::factory()->create(['email' => 'A@example.test']);
    }

    public function test_the_role_is_limited_to_the_two_values(): void
    {
        $this->assertSame(Role::Reader, User::factory()->create()->fresh()->role);
        $this->assertSame(Role::Administrator, User::factory()->administrator()->create()->fresh()->role);

        $this->expectException(\ValueError::class);

        User::factory()->make(['role' => 'owner']);
    }

    public function test_the_two_factor_secret_is_encrypted_and_never_serialised(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);

        $raw = DB::table('users')->where('id', $user->id)->value('two_factor_secret');

        $this->assertNotSame('JBSWY3DPEHPK3PXP', $raw);
        $this->assertSame('JBSWY3DPEHPK3PXP', $user->fresh()->two_factor_secret);
        $this->assertArrayNotHasKey('two_factor_secret', $user->toArray());
        $this->assertArrayNotHasKey('password', $user->toArray());
    }

    public function test_the_password_is_hashed_and_the_flags_have_defaults(): void
    {
        $user = User::factory()->create(['password' => 'secret-value']);

        $this->assertNotSame('secret-value', $user->fresh()->password);
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertNull($user->fresh()->two_factor_confirmed_at);
    }
}
