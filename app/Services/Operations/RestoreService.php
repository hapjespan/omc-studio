<?php

namespace App\Services\Operations;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Encryption\Encrypter;
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

        $this->validate($database, trim((string) file_get_contents($key)));

        $safety = app(BackupService::class)->run();

        $this->application->maintenanceMode()->activate([]);
        $restored = $this->replace($folder, $database, $key);
        $this->application->maintenanceMode()->deactivate();

        return [...$restored, $safety];
    }

    private function validate(string $database, string $key): void
    {
        try {
            $pdo = new PDO('sqlite:'.$database);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $intact = $pdo->query('PRAGMA integrity_check')->fetchColumn() === 'ok';
        } catch (PDOException) {
            $intact = false;
        }

        if (! $intact) {
            throw new InvalidArgumentException(__('console.restore_damaged'));
        }

        try {
            $migrations = (int) $pdo->query('SELECT COUNT(*) FROM migrations')->fetchColumn();
            $secrets = $pdo->query('SELECT two_factor_secret FROM users WHERE two_factor_secret IS NOT NULL')->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException) {
            $migrations = 0;
        }

        if ($migrations === 0) {
            throw new InvalidArgumentException(__('console.restore_foreign'));
        }

        $cipher = (string) config('app.cipher');
        $raw = str_starts_with($key, 'base64:') ? base64_decode(substr($key, 7), true) : false;

        if ($raw === false || ! Encrypter::supported($raw, $cipher)) {
            throw new InvalidArgumentException(__('console.restore_key_invalid'));
        }

        $encrypter = new Encrypter($raw, $cipher);

        foreach ($secrets as $secret) {
            try {
                $encrypter->decryptString($secret);
            } catch (DecryptException) {
                throw new InvalidArgumentException(__('console.restore_key_mismatch'));
            }
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
