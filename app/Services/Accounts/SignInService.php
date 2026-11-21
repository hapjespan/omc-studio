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
    public const PENDING = 'second_factor_pending';

    private const NAMESPACE = 'sign-in';

    private const ABSENT = '$2y$12$bTyp9.0Py5RIM5Fet/8fmuFi2PWzVcSlPm9UFVKzp5VgK0lkWUaFe';

    private readonly Throttle $throttle;

    public function __construct(private readonly OneTimePassword $passwords)
    {
        $this->throttle = new Throttle(self::NAMESPACE, true);
    }

    public function attempt(string $email, string $password): User
    {
        $email = mb_strtolower(trim($email));

        $user = User::query()->where('email', $email)->first();

        if (! $this->throttle->claim($email)) {
            SecurityLog::record('sign_in_throttled', ['user_id' => $user?->getKey()]);

            throw ValidationException::withMessages([
                'email' => $this->throttle->message($email),
            ]);
        }

        if ($user === null) {
            Hash::check($password, self::ABSENT);

            $this->refuse($email, $user, 'unknown_address');
        }

        if (! Hash::check($password, $user->password)) {
            $this->refuse($email, $user, 'wrong_password');
        }

        if ($this->passwords->isExpired($user)) {
            $this->refuse($email, $user, 'one_time_password_expired');
        }

        $this->throttle->clear($email);
        app(SecondFactorService::class)->discardUnconfirmed($user);

        if ($user->two_factor_confirmed_at !== null) {
            session()->regenerate();
            session()->put(self::PENDING, ['id' => $user->getKey(), 'password' => $this->fingerprint($user)]);
            SecurityLog::record('second_factor_required', ['user_id' => $user->getKey()]);

            return $user;
        }

        $this->complete($user);

        return $user;
    }

    public function pendingUser(): ?User
    {
        $pending = session(self::PENDING);

        if (! is_array($pending)) {
            return null;
        }

        $user = User::query()->whereKey($pending['id'] ?? 0)->whereNotNull('two_factor_confirmed_at')->first();

        if ($user === null || ! hash_equals($this->fingerprint($user), (string) ($pending['password'] ?? ''))) {
            return null;
        }

        return $user;
    }

    public function complete(User $user): void
    {
        session()->forget(self::PENDING);
        Auth::login($user);
        session()->put('signed_in_at', Carbon::now()->getTimestamp());
        SecurityLog::record('sign_in_succeeded');
    }

    private function fingerprint(User $user): string
    {
        return hash_hmac('sha256', (string) $user->password, (string) config('app.key'));
    }

    private function refuse(string $email, ?User $user, string $reason): never
    {
        $this->throttle->fail($email);
        SecurityLog::record('sign_in_failed', ['reason' => $reason, 'user_id' => $user?->getKey()]);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }
}
