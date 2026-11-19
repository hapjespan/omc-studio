<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Audit\SecurityLog;

class UserAdministration
{
    public function __construct(private readonly OneTimePassword $passwords, private readonly SessionTerminator $sessions) {}

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
    public function update(User $user, array $data): User
    {
        $user->fill($data)->save();

        return $user;
    }
}
