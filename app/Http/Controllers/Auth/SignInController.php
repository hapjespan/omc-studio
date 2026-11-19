<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Accounts\SignInService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class SignInController extends Controller
{
    public function create(): Response
    {
        return Inertia::render('Auth/SignIn');
    }

    public function store(Request $request, SignInService $service): RedirectResponse
    {
        $credentials = $request->validate(
            ['email' => ['required', 'string'], 'password' => ['required', 'string']],
            ['email.required' => __('auth.email_required'), 'password.required' => __('auth.password_required')],
        );

        $service->attempt($credentials['email'], $credentials['password']);

        return Auth::check() ? redirect()->intended('/') : redirect('/sign-in/second-factor');
    }
}
