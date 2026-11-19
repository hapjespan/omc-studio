<?php

namespace App\Services\Operations;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use InvalidArgumentException;
use PDO;
use PDOException;

class RestoreService
{
    public function __construct(private readonly Application $application) {}

    /** @return list<string> */
    public function run(string $folder): array
    {
        $folder = rtrim($folder, '/');
        $database = $folder.'/'.BackupService::DATABASE;
        $key = $folder.'/'.BackupService::KEY;

        if (! is_file($database) || ! is_file($key)) {
            throw new InvalidArgumentException(__('console.restore_incomplete'));
        }

        $this->readable($database);

        $this->application->maintenanceMode()->activate([]);

        try {
            return $this->replace($folder, $database, $key);
        } finally {
            $this->application->maintenanceMode()->deactivate();
        }
    }

    private function readable(string $database): void
    {
        try {
            $statement = (new PDO('sqlite:'.$database))->query('PRAGMA integrity_check');
            $outcome = $statement === false ? null : $statement->fetchColumn();
        } catch (PDOException) {
            $outcome = null;
        }

        if ($outcome !== 'ok') {
            throw new InvalidArgumentException(__('console.restore_damaged'));
        }
    }

    /** @return list<string> */
    private function replace(string $folder, string $database, string $key): array
    {
        DB::purge();

        $live = BackupService::databasePath();
        File::delete([$live, $live.'-wal', $live.'-shm']);
        File::copy($database, $live);

        $uploads = BackupService::uploadsPath();
        File::deleteDirectory($uploads);
        File::ensureDirectoryExists($uploads);

        if (is_dir($folder.'/'.BackupService::UPLOADS)) {
            File::copyDirectory($folder.'/'.BackupService::UPLOADS, $uploads);
        }

        File::copy($key, storage_path(BackupService::KEY));
        chmod(storage_path(BackupService::KEY), 0600);

        return [BackupService::DATABASE, BackupService::UPLOADS, BackupService::KEY];
    }
}
