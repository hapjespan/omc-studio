<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class BackupService
{
    public const DATABASE = 'database.sqlite';

    public const UPLOADS = 'uploads';

    public const KEY = 'app.key';

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
            DB::connection()->statement('VACUUM INTO ?', [$folder.'/'.self::DATABASE]);

            if (is_dir(self::uploadsPath())) {
                File::copyDirectory(self::uploadsPath(), $folder.'/'.self::UPLOADS);
            }

            File::copy(storage_path(self::KEY), $folder.'/'.self::KEY);
            chmod($folder.'/'.self::KEY, 0600);
        } catch (Throwable $failure) {
            File::deleteDirectory($folder);

            throw $failure;
        }

        return $folder;
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
