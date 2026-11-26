<?php

namespace App\Console\Commands;

use App\Services\Operations\RestoreService;
use Illuminate\Console\Command;
use InvalidArgumentException;
use RuntimeException;

class Restore extends Command
{
    protected $signature = 'app:restore {folder}';

    protected $description = 'Replace the database, uploads and application key with the contents of a backup folder';

    public function handle(RestoreService $restore): int
    {
        try {
            $restored = $restore->run((string) $this->argument('folder'));
        } catch (InvalidArgumentException|RuntimeException $refusal) {
            $this->error($refusal->getMessage());

            return self::FAILURE;
        }

        foreach ($restored as $item) {
            $this->line($item);
        }

        $this->warn(__('console.restore_restart'));

        return self::SUCCESS;
    }
}
