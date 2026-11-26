<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\Password;
use App\Services\Accounts\PasswordService;
use App\Services\Accounts\RecoveryCodes;
use App\Services\Accounts\SecondFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
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

    public function update(Request $request, PasswordService $passwords): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'confirmed', new Password],
        ], [
            'current_password.required' => __('account.current_password_required'),
            'password.required' => __('auth.password_required'),
            'password.confirmed' => __('validation.password_confirmed'),
        ]);

        $passwords->change($user, $validated['current_password'], $validated['password'], $request->session());

        return redirect('/account')->with('status', __('account.password_changed'));
    }
}
