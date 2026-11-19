<?php

namespace App\Http\Middleware;

use App\Models\SystemNotice;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SharePendingNotices
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('pendingNotices', fn () => $request->user()?->can('administer')
            ? SystemNotice::query()->whereNull('seen_at')->count()
            : null);

        return $next($request);
    }
}
