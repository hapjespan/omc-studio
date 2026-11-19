<?php

namespace App\Http\Middleware;

use App\Models\Canvas;
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
            'canvas' => $this->canvas($request),
            'flash' => ['status' => $request->session()->get('status')],
        ];
    }

    /** @return array<string, mixed>|null */
    private function canvas(Request $request): ?array
    {
        $canvas = $request->route('canvas');

        return $canvas instanceof Canvas && $canvas->organisation !== null
            ? ['id' => $canvas->id, 'name' => $canvas->name, 'organisation' => ['id' => $canvas->organisation->id, 'name' => $canvas->organisation->name]]
            : null;
    }
}
