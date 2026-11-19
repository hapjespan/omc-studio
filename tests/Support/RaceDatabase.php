<?php

namespace Tests\Support;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class RaceDatabase
{
    private ?string $file = null;

    /** @return array<string, string> */
    public function prepare(): array
    {
        $connection = (string) config('database.default');

        if (config('database.connections.'.$connection.'.driver') !== 'sqlite') {
            config(['database.connections.race' => config('database.connections.'.$connection)]);

            return ['DB_CONNECTION' => $connection];
        }

        $this->file = base_path('.scratch/concurrency/race-'.getmypid().'.sqlite');

        if (! is_dir(dirname($this->file))) {
            mkdir(dirname($this->file), 0755, true);
        }

        touch($this->file);

        config(['database.connections.race' => ['database' => $this->file] + (array) config('database.connections.sqlite')]);

        Artisan::call('migrate', ['--database' => 'race', '--force' => true]);

        return ['DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $this->file];
    }

    /** @param  list<string>  $tables */
    public function clean(array $tables): void
    {
        if (config('database.connections.race') !== null) {
            foreach ($tables as $table) {
                DB::connection('race')->table($table)->delete();
            }

            DB::purge('race');
        }

        foreach (['', '-wal', '-shm'] as $suffix) {
            if ($this->file !== null && file_exists($this->file.$suffix)) {
                unlink($this->file.$suffix);
            }
        }
    }
}
