<?php

namespace App\Http\Middleware;

use App\Services\Audit\SecurityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogRefusedAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($response->getStatusCode() === 403 && $request->isMethodSafe()) {
            SecurityLog::record('refused_screen', ['route' => $request->route()?->uri()]);
        }

        return $response;
    }
}
