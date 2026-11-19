<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SignInService;
use App\Services\Accounts\Throttle;
use App\Services\Accounts\TotpVerifier;
use App\Services\Audit\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SecondFactorChallengeController extends Controller
{
    private const NAMESPACE = 'second-factor';

    public function create(SignInService $signIn): Response|RedirectResponse
    {
        return $signIn->pendingUser() === null ? redirect('/sign-in') : Inertia::render('Auth/SecondFactor');
    }

    public function store(Request $request, SignInService $signIn, TotpVerifier $verifier, RecoveryCodes $codes): RedirectResponse
    {
        $user = $signIn->pendingUser();

        if ($user === null) {
            return redirect('/sign-in');
        }

        $validated = $request->validate(
            ['code' => ['required', 'string']],
            ['code.required' => __('sign_in.second_factor.code_required')],
        );

        $throttle = new Throttle(self::NAMESPACE);
        $identifier = (string) $user->getKey();

        if ($throttle->tooManyAttempts($identifier)) {
            SecurityLog::record('second_factor_throttled', ['user_id' => $user->getKey()]);

            throw ValidationException::withMessages([
                'code' => __('auth.throttled', ['seconds' => $throttle->secondsLeft($identifier)]),
            ]);
        }

        $code = trim($validated['code']);

        try {
            $passed = $verifier->verify($user, $code);
        } catch (ValidationException $reused) {
            $this->refuse($throttle, $identifier, $user, $reused);
        }

        $recovery = $passed ? false : $codes->consume($user, $code);

        if (! $passed && ! $recovery) {
            $this->refuse($throttle, $identifier, $user, ValidationException::withMessages([
                'code' => __('sign_in.second_factor.failed'),
            ]));
        }

        $throttle->clear($identifier);
        SecurityLog::record('second_factor_passed', ['user_id' => $user->getKey(), 'recovery_code' => $recovery]);
        $signIn->complete($user);

        return redirect()->intended('/');
    }

    private function refuse(Throttle $throttle, string $identifier, User $user, ValidationException $refusal): never
    {
        $throttle->hit($identifier);
        SecurityLog::record('second_factor_failed', ['user_id' => $user->getKey()]);

        throw $refusal;
    }
}
