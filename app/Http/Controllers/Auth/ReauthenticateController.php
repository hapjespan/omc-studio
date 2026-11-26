<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Accounts\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ReauthenticateController extends Controller
{
    public function __invoke(Request $request, PasswordService $passwords): RedirectResponse
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        $validated = $request->validate([
            'password' => ['required', 'string'],
            'code' => [Rule::requiredIf($user->two_factor_confirmed_at !== null), 'nullable', 'string'],
        ], [
            'password.required' => __('auth.password_required'),
            'code.required' => __('sign_in.second_factor.code_required'),
        ]);

        $passwords->confirm($user, $validated['password'], $validated['code'] ?? null, $request->session());

        return back();
    }
}
