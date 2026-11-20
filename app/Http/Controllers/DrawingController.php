<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Services\Canvas\ViewData;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DrawingController extends Controller
{
    public function __invoke(Request $request, Canvas $canvas, ViewData $data): Response|RedirectResponse
    {
        if ($canvas->kind === CanvasKind::Template) {
            return redirect("/canvases/{$canvas->id}/entry");
        }

        if (! $canvas->acceptsEntryBy($request->user())) {
            return redirect("/canvases/{$canvas->id}/view");
        }

        return Inertia::render('Drawing/Index', $data->for($canvas));
    }
}
