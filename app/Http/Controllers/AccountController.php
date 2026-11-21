<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\Password;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SecondFactorService;
use App\Services\Accounts\SessionTerminator;
use App\Services\Accounts\Throttle;
use App\Services\Audit\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    private const NAMESPACE = 'password-change';

    public function show(Request $request, RecoveryCodes $codes, SecondFactorService $factor): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $enrolment = $request->session()->get(SecondFactorService::ENROLLING) === true ? $factor->enrolment($user) : null;

        if ($enrolment !== null) {
            Inertia::clearHistory();
        }

        return Inertia::render('Account/Show', ['secondFactor' => [
            'active' => $user->two_factor_confirmed_at !== null,
            'remainingLabel' => trans_choice('second_factor.remaining_codes', $codes->remaining($user)),
            'enrolment' => $enrolment,
        ]]);
    }

    public function update(Request $request, SessionTerminator $terminator): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $throttle = new Throttle(self::NAMESPACE);
        $identifier = (string) $user->getKey();

        if ($throttle->tooManyAttempts($identifier)) {
            SecurityLog::record('password_change_throttled');

            throw ValidationException::withMessages([
                'current_password' => $throttle->message($identifier),
            ]);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', new Password],
        ], [
            'current_password.required' => __('account.current_password_required'),
            'password.required' => __('auth.password_required'),
            'password.confirmed' => __('validation.password_confirmed'),
        ]);

        if (! Hash::check($validated['current_password'], (string) $user->password)) {
            $throttle->hit($identifier);
            SecurityLog::record('password_change_failed');

            throw ValidationException::withMessages(['current_password' => __('account.current_password_wrong')]);
        }

        if (Hash::check($validated['password'], (string) $user->password)) {
            throw ValidationException::withMessages(['password' => __('account.password_reused')]);
        }

        $throttle->clear($identifier);
        $user->fill(['password' => $validated['password']])->save();
        $request->session()->regenerate(true);
        $ended = $terminator->endOthers($user, $request->session()->getId());
        SecurityLog::record('password_changed', ['sessions_ended' => $ended]);

        return redirect('/account')->with('status', __('account.password_changed'));
    }
}
