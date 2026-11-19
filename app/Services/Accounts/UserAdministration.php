<?php

namespace App\Services\Accounts;

use App\Enums\Role;
use App\Models\User;
use App\Services\Audit\AuditTrail;
use App\Services\Audit\SecurityLog;
use App\Services\Support\SerialisedWrite;
use Illuminate\Support\Facades\Auth;

class UserAdministration
{
    public function __construct(
        private readonly OneTimePassword $passwords,
        private readonly SessionTerminator $sessions,
        private readonly AdministratorGuard $guard,
        private readonly SerialisedWrite $writes,
        private readonly RecoveryCodes $codes,
        private readonly AuditTrail $trail,
    ) {}

    public function resetSecondFactor(User $user, ?User $actor = null): void
    {
        $this->guard->assertAnotherUser($this->actor($actor), $user);

        $ended = $this->writes->run([$user], function () use ($user): int {
            $user->fill([
                'two_factor_secret' => null,
                'two_factor_confirmed_at' => null,
                'two_factor_last_time_step' => null,
            ])->save();

            $this->codes->clear($user);

            return $this->sessions->endAll($user);
        });

        SecurityLog::record('user_second_factor_reset', ['subject_id' => $user->getKey(), 'sessions_ended' => $ended]);
    }

    public function endSessions(User $user, ?User $actor = null): int
    {
        $this->guard->assertAnotherUser($this->actor($actor), $user);

        $ended = $this->sessions->endAll($user);

        $this->trail->record($user, 'sessions_ended', ['sessions' => [$ended, 0]]);
        SecurityLog::record('user_sessions_ended', ['subject_id' => $user->getKey(), 'sessions_ended' => $ended]);

        return $ended;
    }

    public function resetPassword(User $user): string
    {
        $password = $this->passwords->issueTo($user);
        $ended = $this->sessions->endAll($user);

        SecurityLog::record('user_password_reset', ['subject_id' => $user->getKey(), 'sessions_ended' => $ended]);

        return $password;
    }

    /**
     * @param  array{name: string, email: string, role: string}  $data
     * @return array{User, string}
     */
    public function create(array $data): array
    {
        $user = new User($data);
        $user->password = $this->passwords->generate();
        $user->save();

        return [$user, $this->passwords->issueTo($user)];
    }

    /** @param array{name: string, email: string, role: string} $data */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        $actor = $this->actor($actor);

        return $this->writes->run($this->guard->aggregates($user), function () use ($user, $data, $actor): User {
            $this->guard->assertCanChangeRole($actor, $user, Role::from($data['role']));

            $user->fill($data)->save();

            return $user;
        });
    }

    private function actor(?User $actor): ?User
    {
        $actor ??= Auth::user();

        return $actor instanceof User ? $actor : null;
    }
}
