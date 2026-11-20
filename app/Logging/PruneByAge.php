<?php

namespace App\Logging;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;

class PruneByAge
{
    public function __invoke(object $logger, string $name = 'laravel', string $days = '14'): void
    {
        $cutoff = Carbon::today()->subDays((int) $days)->format('Y-m-d');

        foreach (File::glob(storage_path('logs/'.$name.'-*.log')) ?: [] as $path) {
            $date = substr(basename($path, '.log'), strlen($name) + 1);

            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) === 1 && $date < $cutoff) {
                File::delete($path);
            }
        }
    }
}
