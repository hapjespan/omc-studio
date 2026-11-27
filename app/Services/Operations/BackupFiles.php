<?php

namespace App\Services\Operations;

use Illuminate\Support\Facades\File;
use RuntimeException;

final class BackupFiles
{
    public static function copy(string $source, string $destination): void
    {
        if (! File::copy($source, $destination)) {
            throw new RuntimeException(__('console.copy_failed', ['path' => $source]));
        }
    }

    public static function copyDirectory(string $source, string $destination): void
    {
        if (! File::copyDirectory($source, $destination)) {
            throw new RuntimeException(__('console.copy_failed', ['path' => $source]));
        }
    }
}
