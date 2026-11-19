<?php

namespace App\Http\Middleware;

use App\Services\Accounts\OneTimePassword;
use App\Services\Audit\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class MustChangePassword
{
    private const ALLOWED = ['password/new', 'sign-out'];

    public function __construct(private readonly OneTimePassword $passwords) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->passwords->isExpired($user)) {
            SecurityLog::record('one_time_password_expired');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/sign-in')->withErrors(['email' => __('auth.one_time_password_expired')]);
        }

        return $request->is(self::ALLOWED) ? $next($request) : redirect('/password/new');
    }
}
