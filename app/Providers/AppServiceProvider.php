<?php

namespace App\Providers;

use App\Services\Accounts\OneTimePassword;
use App\Services\Audit\AuditContext;
use App\Services\Operations\InstallSteps;
use App\Services\Operations\Steps\BlankTemplateStep;
use App\Services\Operations\Steps\FirstAdministratorStep;
use App\Services\Operations\Steps\ReferenceDataStep;
use App\Services\Operations\Steps\SampleDataStep;
use App\Support\Session\DatabaseSessionHandler;
use Illuminate\Contracts\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AuditContext::class);
        $this->app->singleton(InstallSteps::class, function (Container $app) {
            $steps = new InstallSteps;
            $steps->add(new ReferenceDataStep);
            $steps->add(new BlankTemplateStep);
            $steps->add($app->make(SampleDataStep::class));
            $steps->add(new FirstAdministratorStep(new OneTimePassword));

            return $steps;
        });
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Session::extend('database', fn (Container $app) => new DatabaseSessionHandler(
            $app->make('db')->connection(config('session.connection')),
            (string) config('session.table'),
            (int) config('session.lifetime'),
            $app,
        ));
    }
}
