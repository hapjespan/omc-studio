<?php

namespace Tests\Feature\Setup;

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Tests\TestCase;

class SharedPropsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function shared(?User $user = null, ?string $status = null): array
    {
        $request = Request::create('/');
        $request->setLaravelSession($this->app->make('session.store'));

        if ($status !== null) {
            $request->session()->flash('status', $status);
        }

        $request->setUserResolver(fn () => $user);

        return (new HandleInertiaRequests)->share($request);
    }

    public function test_the_shared_props_hold_role_application_name_and_flash(): void
    {
        $user = User::factory()->create(['role' => 'reader']);

        $props = $this->shared($user, 'Opgeslagen');

        $this->assertSame('reader', $props['auth']['role']);
        $this->assertSame('OMC Studio', $props['appName']);
        $this->assertSame('Opgeslagen', $props['flash']['status']);
    }

    public function test_a_guest_has_no_role_and_no_flash(): void
    {
        $props = $this->shared();

        $this->assertNull($props['auth']['role']);
        $this->assertNull($props['flash']['status']);
    }

    public function test_no_secret_or_personal_value_is_shared(): void
    {
        $user = User::factory()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP']);

        $json = (string) json_encode($this->shared($user));

        foreach ([$user->password, 'JBSWY3DPEHPK3PXP', $user->email, 'remember_token'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }
    }
}
