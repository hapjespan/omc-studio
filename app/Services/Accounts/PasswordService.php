<?php

namespace App\Services\Accounts;

use App\Models\User;
use App\Services\Audit\SecurityLog;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PasswordService
{
    public const CHANGE = 'password-change';

    public const REAUTHENTICATE = 'reauthenticate';

    public function __construct(
        private readonly SessionTerminator $terminator,
        private readonly TotpVerifier $verifier,
        private readonly RecoveryCodes $codes,
    ) {}

    public function change(User $user, string $current, string $password, Session $session): void
    {
        $throttle = new Throttle(self::CHANGE);
        $identifier = (string) $user->getKey();

        if (! $throttle->claim($identifier)) {
            SecurityLog::record('password_change_throttled');

            throw ValidationException::withMessages([
                'current_password' => $throttle->message($identifier),
            ]);
        }

        if (! Hash::check($current, (string) $user->password)) {
            $throttle->fail($identifier);
            SecurityLog::record('password_change_failed');

            throw ValidationException::withMessages(['current_password' => __('account.current_password_wrong')]);
        }

        if ($user->reusesPassword($password)) {
            $throttle->refund($identifier);

            throw ValidationException::withMessages(['password' => __('account.password_reused')]);
        }

        $throttle->clear($identifier);
        $user->fill(['password' => $password])->save();
        $session->regenerate(true);
        $ended = $this->terminator->endOthers($user, $session->getId());
        SecurityLog::record('password_changed', ['sessions_ended' => $ended]);
    }

    public function setInitial(User $user, string $password, Session $session): void
    {
        if ($user->reusesPassword($password)) {
            throw ValidationException::withMessages(['password' => __('auth.password_reused')]);
        }

        $user->fill([
            'password' => $password,
            'must_change_password' => false,
            'one_time_password_expires_at' => null,
        ])->save();

        $session->regenerate(true);
        $ended = $this->terminator->endOthers($user, $session->getId());
        SecurityLog::record('password_changed', ['sessions_ended' => $ended]);
    }

    public function confirm(User $user, string $password, ?string $code, Session $session): void
    {
        $throttle = new Throttle(self::REAUTHENTICATE);
        $identifier = (string) $user->getKey();

        if (! $throttle->claim($identifier)) {
            SecurityLog::record('reauthentication_throttled');

            throw ValidationException::withMessages([
                'password' => $throttle->message($identifier),
            ]);
        }

        if (! Hash::check($password, (string) $user->password)) {
            $this->refuse($throttle, $identifier, ValidationException::withMessages(['password' => __('reauth.password_wrong')]));
        }

        if ($user->two_factor_confirmed_at !== null) {
            $this->requireCode($user, trim((string) $code), $throttle, $identifier);
        }

        $throttle->clear($identifier);
        $session->regenerate(true);
        $session->put('signed_in_at', Carbon::now()->getTimestamp());
        SecurityLog::record('session_reauthenticated');
    }

    private function requireCode(User $user, string $code, Throttle $throttle, string $identifier): void
    {
        try {
            $passed = $this->verifier->verify($user, $code);
        } catch (ValidationException $reused) {
            $this->refuse($throttle, $identifier, $reused);
        }

        if ($passed || $this->codes->consume($user, $code)) {
            return;
        }

        $this->refuse($throttle, $identifier, ValidationException::withMessages(['code' => __('sign_in.second_factor.failed')]));
    }

    private function refuse(Throttle $throttle, string $identifier, ValidationException $refusal): never
    {
        $throttle->fail($identifier);
        SecurityLog::record('reauthentication_failed');

        throw $refusal;
    }
}
