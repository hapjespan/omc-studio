<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Audit\SecurityLog;
use Illuminate\Console\Command;

class ResetAdminPassword extends Command
{
    protected $signature = 'app:reset-admin-password {email}';

    protected $description = 'Print a new one-time password for an Administrator who lost theirs';

    public function handle(OneTimePassword $passwords): int
    {
        $administrator = User::query()
            ->where('email', mb_strtolower(trim((string) $this->argument('email'))))
            ->where('role', Role::Administrator)
            ->first();

        if ($administrator === null) {
            $this->error(__('console.no_administrator'));

            return self::FAILURE;
        }

        $password = $passwords->issueTo($administrator);
        SecurityLog::record('admin_password_reset', ['target_user_id' => $administrator->id]);

        $this->line('Administrator: '.$administrator->email);
        $this->line('One-time password: '.$password);

        return self::SUCCESS;
    }
}
