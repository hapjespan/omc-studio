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
            'appName' => config('app.name'),
            'canvas' => $this->canvas($request),
            'flash' => ['status' => $request->session()->get('status')],
        ];
    }

    /** @return array<string, mixed>|null */
    private function canvas(Request $request): ?array
    {
        $canvas = $request->route('canvas');

        return $canvas instanceof Canvas
            ? [
                'id' => $canvas->id,
                'name' => $canvas->name,
                'kind' => $canvas->kind->value,
                'archived' => $canvas->archived_at !== null,
                'organisation' => $canvas->organisation === null ? null : ['id' => $canvas->organisation->id, 'name' => $canvas->organisation->name],
            ]
            : null;
    }
}
