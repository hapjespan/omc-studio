<?php

namespace App\Services\Accounts;

use App\Enums\Role;
use App\Models\User;
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
    ) {}

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
        $actor ??= Auth::user();

        return $this->writes->run($this->guard->aggregates($user), function () use ($user, $data, $actor): User {
            $this->guard->assertCanChangeRole($actor instanceof User ? $actor : null, $user, Role::from($data['role']));

            $user->fill($data)->save();

            return $user;
        });
    }
}
