<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => ['role' => $request->user()?->role?->value],
            'appName' => __('app.title'),
            'flash' => ['status' => $request->session()->get('status')],
        ];
    }
}
