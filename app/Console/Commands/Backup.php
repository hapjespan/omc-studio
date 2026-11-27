<?php

namespace App\Console\Commands;

use App\Services\Operations\BackupService;
use Illuminate\Console\Command;
use RuntimeException;
use Throwable;

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
        } catch (Throwable $failure) {
            $this->error(__('console.backup_failed', ['reason' => $failure->getMessage()]));

            return self::FAILURE;
        }

        if ($backup->warning !== null) {
            $this->warn($backup->warning);
        }

        return self::SUCCESS;
    }
}
