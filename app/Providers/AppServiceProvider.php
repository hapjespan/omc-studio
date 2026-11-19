<?php

namespace App\Providers;

use App\Services\Audit\AuditContext;
use App\Services\Operations\InstallSteps;
use App\Services\Operations\Steps\ReferenceDataStep;
use App\Support\Session\DatabaseSessionHandler;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(InstallSteps::class, function () {
            $steps = new InstallSteps;
            $steps->add(new ReferenceDataStep);

            return $steps;
        });
    }

    public function boot(): void
    {
        Session::extend('database', fn (Container $app) => new DatabaseSessionHandler(
            $app->make('db')->connection(config('session.connection')),
            (string) config('session.table'),
            (int) config('session.lifetime'),
            $app,
        ));
    }
}
