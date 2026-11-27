<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Audit\SecurityLog;
use App\Services\Undo\UndoStore;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SignInService
{
    public const PENDING = 'second_factor_pending';

    private const NAMESPACE = 'sign-in';

    private const ABSENT_BCRYPT = '$2y$12$ENUQFUrC/yof4OJTiIdBEeRDD/iN57SpBNTBrYXCIk7KtiTgZJaKO';

    private const ABSENT = '$argon2id$v=19$m=65536,t=4,p=1$VUJBcnNVZWpRRU90c2pCVw$yrtSutPTdTT243diw+9h7ZkfACEAV8mbxxqkEWBdLWU';

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
            $this->padCost($password, true, true);

            $this->refuse($email, $user, 'unknown_address');
        }

        $legacy = $user->hasLegacyPassword();
        $matches = Hash::check($password, $user->password);
        $this->padCost($password, ! $legacy, $legacy);

        if (! $matches) {
            $this->refuse($email, $user, 'wrong_password');
        }

        if ($this->passwords->isExpired($user)) {
            $this->refuse($email, $user, 'one_time_password_expired');
        }

        $this->throttle->clear($email);

        if ($legacy && ! $user->must_change_password) {
            $user->forceFill(['must_change_password' => true])->save();
            SecurityLog::record('legacy_password_flagged', ['user_id' => $user->getKey()]);
        }

        app(UndoStore::class)->prune();
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

    private function padCost(string $password, bool $bcrypt, bool $argon): void
    {
        if ($bcrypt) {
            Hash::check($password, self::ABSENT_BCRYPT);
        }

        if ($argon) {
            Hash::check($password, self::ABSENT);
        }
    }

    private function refuse(string $email, ?User $user, string $reason): never
    {
        $this->throttle->fail($email);
        SecurityLog::record('sign_in_failed', ['reason' => $reason, 'user_id' => $user?->getKey()]);

        throw ValidationException::withMessages(['email' => __('auth.failed')]);
    }
}
