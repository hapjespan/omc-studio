<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Rules\Password;
use App\Services\Accounts\PasswordService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NewPasswordController extends Controller
{
    public function create(Request $request): Response
    {
        $this->pending($request);

        return Inertia::render('Auth/NewPassword');
    }

    public function update(Request $request, PasswordService $passwords): RedirectResponse
    {
        $user = $this->pending($request);

        $validated = $request->validate(
            ['password' => ['required', 'string', 'confirmed', new Password]],
            ['password.required' => __('auth.password_required'), 'password.confirmed' => __('validation.password_confirmed')],
        );

        $passwords->setInitial($user, $validated['password'], $request->session());
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
