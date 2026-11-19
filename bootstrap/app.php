<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LogRefusedAccess;
use App\Http\Middleware\MustChangePassword;
use App\Http\Middleware\ResolveFiscalYear;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SessionLifetime;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\PostTooLargeException;
use Illuminate\Http\Request;
use Inertia\EncryptHistoryMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SecurityHeaders::class);
        $middleware->web(append: [LogRefusedAccess::class, HandleInertiaRequests::class, ResolveFiscalYear::class, EncryptHistoryMiddleware::class, SessionLifetime::class, MustChangePassword::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(fn () => app()->bound('request') ? ['route' => request()->route()?->getName()] : []);
        $exceptions->map(fn (QueryException $e) => new RuntimeException($e->getSql(), (int) $e->getCode()));
        $exceptions->render(fn (PostTooLargeException $e, Request $request) => $request->is('imports/canvas', 'imports/canvas/preview')
            ? response()->json(['message' => __('jsonx.refused_file'), 'errors' => ['file' => [__('jsonx.refused_file')]]], 413)
            : null);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
