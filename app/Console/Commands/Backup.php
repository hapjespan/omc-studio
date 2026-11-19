<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'app:backup';

    protected $description = 'Copy the database, uploads and application key into a folder named with date and time';

    public function handle(BackupService $backup): int
    {
        $this->line($backup->run());

        return self::SUCCESS;
    }
}
