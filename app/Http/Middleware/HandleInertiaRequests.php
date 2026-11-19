<?php

namespace App\Http\Middleware;

use App\Models\Canvas;
use Closure;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Symfony\Component\HttpFoundation\Response;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function handle(Request $request, Closure $next): Response
    {
        $response = parent::handle($request, $next);

        if ($request->isMethod('GET') && $request->header('X-Inertia') && $response->isSuccessful()) {
            $request->session()->setPreviousUrl($request->fullUrl());
        }

        return $response;
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'role' => $request->user()?->role?->value,
                'secondFactor' => $request->user()?->two_factor_confirmed_at !== null,
                'sessionEndsAt' => $this->sessionEndsAt($request),
            ],
            'appName' => config('app.name'),
            'canvas' => $this->canvas($request),
            'flash' => ['status' => $request->session()->get('status'), 'undo' => $request->session()->get('undo'), 'bulk' => $request->session()->get('bulk'), 'carry_over' => $request->session()->get('carry_over'), 'undoToken' => $request->session()->get('undo_token')],
        ];
    }

    private function sessionEndsAt(Request $request): ?int
    {
        $signedInAt = $request->session()->get('signed_in_at');

        return $request->user() === null || $signedInAt === null ? null : (int) $signedInAt + SessionLifetime::LIMIT;
    }

    /** @return array<string, mixed>|null */
    private function canvas(Request $request): ?array
    {
        $canvas = $request->route('canvas') ?? $request->route('snapshot')?->canvas;

        return $canvas instanceof Canvas
            ? [
                'id' => $canvas->id,
                'name' => $canvas->name,
                'kind' => $canvas->kind->value,
                'archived' => $canvas->archived_at !== null,
                'organisation' => $canvas->organisation === null ? null : ['id' => $canvas->organisation->id, 'name' => $canvas->organisation->name, 'is_sample' => $canvas->organisation->is_sample],
            ]
            : null;
    }
}
