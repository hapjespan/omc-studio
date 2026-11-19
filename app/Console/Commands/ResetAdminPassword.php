<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\User;
use App\Services\Accounts\OneTimePassword;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SessionTerminator;
use App\Services\Audit\SecurityLog;
use Illuminate\Console\Command;

class ResetAdminPassword extends Command
{
    protected $signature = 'app:reset-admin-password {email} {--second-factor : Also remove the second factor and recovery codes}';

    protected $description = 'Print a new one-time password for an Administrator who lost theirs';

    public function handle(OneTimePassword $passwords, SessionTerminator $sessions, RecoveryCodes $codes): int
    {
        $administrator = User::query()
            ->where('email', mb_strtolower(trim((string) $this->argument('email'))))
            ->where('role', Role::Administrator)
            ->first();

        if ($administrator === null) {
            $this->error(__('console.no_administrator'));

            return self::FAILURE;
        }

        if ($this->option('second-factor')) {
            $administrator->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_time_step' => null])->save();
            $codes->clear($administrator);
        }

        $password = $passwords->issueTo($administrator);
        $ended = $sessions->endAll($administrator);
        SecurityLog::record('admin_password_reset', ['target_user_id' => $administrator->id, 'sessions_ended' => $ended, 'second_factor_removed' => (bool) $this->option('second-factor')]);

        $this->line('Administrator: '.$administrator->email);
        $this->line('One-time password: '.$password);

        return self::SUCCESS;
    }
}
