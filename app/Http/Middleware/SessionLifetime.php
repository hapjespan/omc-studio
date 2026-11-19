<?php

namespace App\Http\Middleware;

use App\Services\Audit\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SessionLifetime
{
    public const LIMIT = 12 * 3600;

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() === null || $this->within($request)) {
            return $next($request);
        }

        SecurityLog::record('session_expired_absolute');
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        Inertia::clearHistory();

        if ($request->isMethod('GET')) {
            $request->session()->put('url.intended', $request->fullUrl());
        }

        return redirect('/sign-in')->with('status', __('auth.session_ended_absolute'));
    }

    private function within(Request $request): bool
    {
        $signedInAt = $request->session()->get('signed_in_at');

        return $signedInAt !== null && Carbon::now()->getTimestamp() - (int) $signedInAt <= self::LIMIT;
    }
}
