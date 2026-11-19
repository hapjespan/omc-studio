<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Enums\Role;
use App\Models\Canvas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CanvasModeController extends Controller
{
    public function __invoke(Request $request, Canvas $canvas): RedirectResponse
    {
        $writes = $request->user()?->role === Role::Administrator && ($canvas->kind === CanvasKind::Template || $canvas->archived_at === null);

        return redirect($writes ? "/canvases/{$canvas->id}/entry" : "/canvases/{$canvas->id}/view");
    }
}
