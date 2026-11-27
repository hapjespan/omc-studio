<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use PDO;
use RuntimeException;
use Throwable;

class BackupService
{
    public const DATABASE = 'database.sqlite';

    public const UPLOADS = 'uploads';

    public const KEY = 'app.key';

    private const ATTEMPTS = 3;

    public ?string $warning = null;

    public static function uploadsPath(): string
    {
        return storage_path('app/private');
    }

    public static function databasePath(): string
    {
        return (string) config('database.connections.'.config('database.default').'.database');
    }

    public function run(): string
    {
        $folder = $this->freshFolder();

        try {
            $this->copyConsistently($folder);

            File::copy(storage_path(self::KEY), $folder.'/'.self::KEY);
            chmod($folder.'/'.self::KEY, 0600);
        } catch (Throwable $failure) {
            File::deleteDirectory($folder);

            throw $failure;
        }

        return $folder;
    }

    public static function unsafeLogo(string $folder): ?string
    {
        foreach (self::logoPaths($folder) as $path) {
            if (str_contains($path, '..') || str_starts_with($path, '/')) {
                return $path;
            }
        }

        return null;
    }

    public static function missingLogo(string $folder): ?string
    {
        foreach (self::logoPaths($folder) as $path) {
            if (! str_contains($path, '..') && ! str_starts_with($path, '/') && ! is_file($folder.'/'.self::UPLOADS.'/'.$path)) {
                return $path;
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function logoPaths(string $folder): array
    {
        $pdo = new PDO('sqlite:'.$folder.'/'.self::DATABASE);

        return $pdo->query("SELECT logo_path FROM organisations WHERE logo_path IS NOT NULL AND logo_path != ''")->fetchAll(PDO::FETCH_COLUMN);
    }

    protected function copyUploads(string $from, string $to): void
    {
        File::copyDirectory($from, $to);
    }

    private function copyConsistently(string $folder): void
    {
        $missing = null;

        for ($attempt = 1; $attempt <= self::ATTEMPTS; $attempt++) {
            File::delete($folder.'/'.self::DATABASE);
            File::deleteDirectory($folder.'/'.self::UPLOADS);

            DB::connection()->statement('VACUUM INTO ?', [$folder.'/'.self::DATABASE]);

            if (is_dir(self::uploadsPath())) {
                $this->copyUploads(self::uploadsPath(), $folder.'/'.self::UPLOADS);
            }

            if (($missing = self::missingLogo($folder)) === null) {
                return;
            }

            if ($this->liveLogoDangles($missing)) {
                $this->warning = __('console.backup_dangling_logo', ['path' => $missing]);

                return;
            }
        }

        throw new RuntimeException(__('console.backup_inconsistent', ['path' => $missing]));
    }

    private function liveLogoDangles(string $path): bool
    {
        return DB::table('organisations')->where('logo_path', $path)->exists()
            && ! is_file(self::uploadsPath().'/'.$path);
    }

    private function freshFolder(): string
    {
        $base = storage_path('backups/'.now()->format('Y-m-d_His'));
        $folder = $base;

        for ($suffix = 2; ! @mkdir($folder, 0755, true) && is_dir($folder); $suffix++) {
            $folder = $base.'-'.$suffix;
        }

        return $folder;
    }
}
