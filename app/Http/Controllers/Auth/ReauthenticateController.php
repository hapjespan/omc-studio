<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\Throttle;
use App\Services\Accounts\TotpVerifier;
use App\Services\Audit\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReauthenticateController extends Controller
{
    private const NAMESPACE = 'reauthenticate';

    public function __invoke(Request $request, TotpVerifier $verifier, RecoveryCodes $codes): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => [Rule::requiredIf($user->two_factor_confirmed_at !== null), 'string'],
        ], [
            'password.required' => __('auth.password_required'),
            'code.required' => __('sign_in.second_factor.code_required'),
        ]);

        $throttle = new Throttle(self::NAMESPACE);
        $identifier = (string) $user->getKey();

        if ($throttle->tooManyAttempts($identifier)) {
            SecurityLog::record('reauthentication_throttled');

            throw ValidationException::withMessages([
                'password' => __('auth.throttled', ['seconds' => $throttle->secondsLeft($identifier)]),
            ]);
        }

        if (! Hash::check($validated['password'], (string) $user->password)) {
            $this->refuse($throttle, $identifier, ValidationException::withMessages([
                'password' => __('reauth.password_wrong'),
            ]));
        }

        if ($user->two_factor_confirmed_at !== null) {
            $this->requireCode($user, trim((string) ($validated['code'] ?? '')), $verifier, $codes, $throttle, $identifier);
        }

        $throttle->clear($identifier);
        $request->session()->put('signed_in_at', Carbon::now()->getTimestamp());
        SecurityLog::record('session_reauthenticated');

        return back();
    }

    private function requireCode(User $user, string $code, TotpVerifier $verifier, RecoveryCodes $codes, Throttle $throttle, string $identifier): void
    {
        try {
            $passed = $verifier->verify($user, $code);
        } catch (ValidationException $reused) {
            $this->refuse($throttle, $identifier, $reused);
        }

        if ($passed || $codes->consume($user, $code)) {
            return;
        }

        $this->refuse($throttle, $identifier, ValidationException::withMessages([
            'code' => __('sign_in.second_factor.failed'),
        ]));
    }

    private function refuse(Throttle $throttle, string $identifier, ValidationException $refusal): never
    {
        $throttle->hit($identifier);
        SecurityLog::record('reauthentication_failed');

        throw $refusal;
    }
}
