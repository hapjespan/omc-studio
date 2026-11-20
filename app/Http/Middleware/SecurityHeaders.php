<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    private const POLICY = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'";

    private const DRAWING_PATH = '#^canvases/[^/]+/drawing$#';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->set('Content-Security-Policy', $this->policyFor($request));
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'no-referrer');

        if ($request->user() !== null) {
            $response->headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }

    private function policyFor(Request $request): string
    {
        if (! $request->isMethod('GET') || preg_match(self::DRAWING_PATH, $request->path()) !== 1) {
            return self::POLICY;
        }

        return str_replace("script-src 'self'", "script-src 'self' 'wasm-unsafe-eval'", self::POLICY);
    }
}
