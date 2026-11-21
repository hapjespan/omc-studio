<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Element;
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

        return Inertia::render('Drawing/Index', [...$data->for($canvas), 'versions' => ['structure' => (int) $canvas->structure_version, 'layout' => (int) $canvas->layout_version], 'newElements' => $this->newElements($canvas)]);
    }

    /** @return list<string> */
    private function newElements(Canvas $canvas): array
    {
        if ($canvas->layout_saved_at === null) {
            return [];
        }

        return Element::query()->where('canvas_id', $canvas->id)->whereNull('archived_at')->where('created_at', '>', $canvas->layout_saved_at)->pluck('id')->map(fn ($id) => (string) $id)->values()->all();
    }
}
