<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CanvasModeController extends Controller
{
    public function __invoke(Request $request, Canvas $canvas): RedirectResponse
    {
        return redirect($canvas->acceptsEntryBy($request->user()) ? "/canvases/{$canvas->id}/entry" : "/canvases/{$canvas->id}/view");
    }
}
