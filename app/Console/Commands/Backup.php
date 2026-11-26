<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Command;
use RuntimeException;

class Backup extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Copy the database, uploads and application key into a folder named with date and time';

    public function handle(BackupService $backup): int
    {
        try {
            $this->line($backup->run());
        } catch (RuntimeException $failure) {
            $this->error($failure->getMessage());

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
