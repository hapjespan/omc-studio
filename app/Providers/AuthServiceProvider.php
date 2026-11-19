<?php

namespace App\Providers;

use App\Enums\Role;
use App\Models\User;
use App\Services\Audit\SecurityLog;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::define('administer', fn (User $user) => $user->role === Role::Administrator);

        Gate::after(function (?User $user, string $ability, ?bool $result): void {
            if ($result !== true) {
                SecurityLog::record('forbidden', ['ability' => $ability, 'route' => request()->route()?->uri()]);
            }
        });
    }
}
