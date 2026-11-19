<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Audit\SecurityLog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SignInService
{
    private const NAMESPACE = 'sign-in';

    private const ABSENT = '$2y$12$bTyp9.0Py5RIM5Fet/8fmuFi2PWzVcSlPm9UFVKzp5VgK0lkWUaFe';

    private readonly Throttle $throttle;

    public function __construct(private readonly OneTimePassword $passwords)
    {
        $this->throttle = new Throttle(self::NAMESPACE);
    }

    public function attempt(string $email, string $password): User
    {
        $email = mb_strtolower(trim($email));

        if ($this->throttle->tooManyAttempts($email)) {
            SecurityLog::record('sign_in_throttled');

            throw ValidationException::withMessages([
                'email' => __('auth.throttled', ['seconds' => $this->throttle->secondsLeft($email)]),
            ]);
        }

        $user = User::query()->where('email', $email)->first();

        if ($user === null) {
            Hash::check($password, self::ABSENT);

            $this->refuse($email, 'unknown_address');
        }

        if (! Hash::check($password, $user->password)) {
            $this->refuse($email, 'wrong_password');
        }

        if ($this->passwords->isExpired($user)) {
            $this->refuse($email, 'one_time_password_expired');
        }

        $this->throttle->clear($email);
        Auth::login($user);
        session()->put('signed_in_at', Carbon::now()->getTimestamp());
        SecurityLog::record('sign_in_succeeded');

        return $user;
    }

    private function refuse(string $email, string $reason): never
    {
        $this->throttle->hit($email);
        SecurityLog::record('sign_in_failed', ['reason' => $reason]);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }
}
