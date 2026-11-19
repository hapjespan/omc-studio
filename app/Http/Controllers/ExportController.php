<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Services\Exchange\CanvasExport;
use Illuminate\Http\JsonResponse;

class ExportController extends Controller
{
    public function canvasJson(Canvas $canvas, CanvasExport $export): JsonResponse
    {
        abort_if($canvas->kind === CanvasKind::Template, 404);

        return response()->json($export->build($canvas), 200, ['Content-Disposition' => 'attachment; filename='.$export->fileName($canvas)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
