<?php

namespace App\Console\Commands;

use App\Services\Operations\InstallSteps;
use Illuminate\Console\Command;
use Illuminate\Encryption\Encrypter;

class InstallCommand extends Command
{
    protected $signature = 'app:install';

    protected $description = 'Prepare the application: application key, database, migrations and install steps';

    public function handle(InstallSteps $steps): int
    {
        $this->useApplicationKey();
        $this->createSqliteDatabase();
        $this->call('migrate', ['--force' => true]);
        $steps->run();

        return self::SUCCESS;
    }

    private function useApplicationKey(): void
    {
        $path = storage_path('app.key');

        if (! file_exists($path) || filesize($path) === 0) {
            touch($path);
            chmod($path, 0600);
            file_put_contents($path, 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher'))));
            $this->info('Application key written to '.$path);
        }

        config(['app.key' => trim((string) file_get_contents($path))]);
        $this->laravel->forgetInstance('encrypter');
    }

    private function createSqliteDatabase(): void
    {
        $connection = (string) config('database.default');

        if ((string) config('database.connections.'.$connection.'.driver') !== 'sqlite') {
            return;
        }

        $database = (string) config('database.connections.'.$connection.'.database');

        if ($database !== ':memory:' && ! file_exists($database)) {
            touch($database);
            $this->info('Database file created at '.$database);
        }
    }
}
