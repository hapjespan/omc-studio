<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Password;
use App\Services\Audit\SecurityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        $this->pending($request);

        return Inertia::render('Auth/NewPassword');
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $this->pending($request);

        $validated = $request->validate(
            ['password' => ['required', 'string', 'confirmed', new Password]],
            ['password.required' => __('auth.password_required'), 'password.confirmed' => __('validation.password_confirmed')],
        );

        if (Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => __('auth.password_reused')]);
        }

        $user->fill([
            'password' => $validated['password'],
            'must_change_password' => false,
            'one_time_password_expires_at' => null,
        ])->save();

        $request->session()->regenerate(true);
        SecurityLog::record('password_changed');
        Inertia::clearHistory();

        return redirect()->intended('/')->with('status', __('auth.password_changed'));
    }

    private function pending(Request $request): User
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            abort(403);
        }

        return $user;
    }
}
