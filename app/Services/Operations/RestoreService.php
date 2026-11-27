<?php

namespace App\Services\Operations;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use PDO;
use PDOException;
use RuntimeException;
use Throwable;

class RestoreService
{
    public ?string $warning = null;

    public function __construct(private readonly Application $application) {}

    /** @return list<string> */
    public function run(string $folder): array
    {
        $this->warning = null;
        $folder = rtrim($folder, '/');
        $database = $folder.'/'.BackupService::DATABASE;
        $key = $folder.'/'.BackupService::KEY;

        if (! is_file($database) || ! is_file($key)) {
            throw new InvalidArgumentException(__('console.restore_incomplete'));
        }

        $this->validate($database, trim((string) file_get_contents($key)));

        $this->assertLogosPresent($folder);

        $backup = app(BackupService::class);
        $safety = $backup->run();
        $this->warning = $backup->warning;

        $this->application->maintenanceMode()->activate([]);

        try {
            $restored = $this->replace($folder, $database, $key);
        } finally {
            $this->application->maintenanceMode()->deactivate();
        }

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

    private function assertLogosPresent(string $folder): void
    {
        $missing = BackupService::unsafeLogo($folder) ?? BackupService::missingLogo($folder);

        if ($missing !== null) {
            throw new InvalidArgumentException(__('console.restore_logo_missing', ['path' => $missing]));
        }
    }

    /** @return list<string> */
    private function replace(string $folder, string $database, string $key): array
    {
        DB::purge();

        $live = BackupService::databasePath();
        $uploads = BackupService::uploadsPath();
        $keyPath = storage_path(BackupService::KEY);
        $recovery = storage_path('recovery/'.now()->format('Y-m-d_His').'-'.Str::random(6));
        $items = [
            ['name' => 'db-wal', 'live' => $live.'-wal', 'stage' => null],
            ['name' => 'db-shm', 'live' => $live.'-shm', 'stage' => null],
            ['name' => BackupService::DATABASE, 'live' => $live, 'stage' => $live.'.restore'],
            ['name' => BackupService::UPLOADS, 'live' => $uploads, 'stage' => $uploads.'.restore'],
            ['name' => BackupService::KEY, 'live' => $keyPath, 'stage' => $keyPath.'.restore'],
        ];

        $this->discardStage($items);

        try {
            File::copy($database, $items[2]['stage']);
            File::ensureDirectoryExists($items[3]['stage']);

            if (is_dir($folder.'/'.BackupService::UPLOADS)) {
                File::copyDirectory($folder.'/'.BackupService::UPLOADS, $items[3]['stage']);
            }

            File::copy($key, $items[4]['stage']);
            chmod($items[4]['stage'], 0600);
        } catch (Throwable $failure) {
            $this->discardStage($items);

            throw new RuntimeException(__('console.restore_staging_failed', ['reason' => $failure->getMessage()]));
        }

        File::ensureDirectoryExists($recovery);
        $placed = [];

        try {
            foreach ($items as $item) {
                if (file_exists($item['live'])) {
                    $this->move($item['live'], $recovery.'/'.$item['name']);
                }

                if ($item['stage'] !== null) {
                    $this->move($item['stage'], $item['live']);
                    $placed[] = $item['name'];
                }
            }
        } catch (Throwable $failure) {
            $complete = $this->rollBack($items, $recovery, $placed);
            $this->discardStage($items);

            if ($complete) {
                File::deleteDirectory($recovery);
            }

            throw new RuntimeException(__($complete ? 'console.restore_swap_failed' : 'console.restore_rollback_failed', ['reason' => $failure->getMessage(), 'folder' => $recovery]));
        }

        return [BackupService::DATABASE, BackupService::UPLOADS, BackupService::KEY, __('console.restore_recovery', ['folder' => $recovery])];
    }

    protected function move(string $from, string $to): void
    {
        if (! rename($from, $to)) {
            throw new RuntimeException(__('console.restore_move_failed', ['path' => $from]));
        }
    }

    /**
     * @param  list<array{name: string, live: string, stage: ?string}>  $items
     * @param  list<string>  $placed
     */
    private function rollBack(array $items, string $recovery, array $placed): bool
    {
        $complete = true;

        foreach (array_reverse($items) as $item) {
            $kept = $recovery.'/'.$item['name'];

            try {
                if (in_array($item['name'], $placed, true)) {
                    $this->remove($item['live']);
                }

                if (file_exists($kept)) {
                    $this->move($kept, $item['live']);
                }
            } catch (Throwable) {
                $complete = false;
            }
        }

        return $complete;
    }

    private function remove(string $path): void
    {
        is_dir($path) ? File::deleteDirectory($path) : File::delete($path);
    }

    /** @param list<array{name: string, live: string, stage: ?string}> $items */
    private function discardStage(array $items): void
    {
        foreach ($items as $item) {
            if ($item['stage'] !== null && file_exists($item['stage'])) {
                $this->remove($item['stage']);
            }
        }
    }
}
