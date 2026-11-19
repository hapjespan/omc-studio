<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Rules\CsvUploadRule;
use App\Services\Audit\SecurityLog;
use App\Services\Exchange\StructureCsv;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class StructureImportController extends Controller
{
    public function __construct(private readonly StructureCsv $import) {}

    public function preview(Request $request, Canvas $canvas): JsonResponse
    {
        return response()->json($this->import->preview($canvas, $this->path($request)));
    }

    public function commit(Request $request, Canvas $canvas): JsonResponse
    {
        $result = $this->import->commit($canvas, $this->path($request));

        SecurityLog::record('structure_imported', ['canvas_id' => $canvas->getKey(), ...$result['totals']]);

        return response()->json($result);
    }

    private function path(Request $request): string
    {
        $request->validate(['file' => ['required', new CsvUploadRule(StructureCsv::COLUMNS)]]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return (string) $file->getRealPath();
    }
}
