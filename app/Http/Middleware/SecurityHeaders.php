<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', self::POLICY);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
