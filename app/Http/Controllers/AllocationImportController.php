<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Rules\CsvUploadRule;
use App\Services\Audit\SecurityLog;
use App\Services\Exchange\AllocationCsv;
use App\Services\Exchange\AllocationCsvImport;
use App\Services\Exchange\Headers;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class AllocationImportController extends Controller
{
    public function __construct(private readonly AllocationCsvImport $import) {}

    public function preview(Request $request, Canvas $canvas): JsonResponse
    {
        return response()->json($this->import->preview($canvas, $this->path($request)));
    }

    public function commit(Request $request, Canvas $canvas): JsonResponse
    {
        $result = $this->import->commit($canvas, $this->path($request));

        SecurityLog::record('allocations_imported', ['canvas_id' => $canvas->getKey(), ...$result['totals']]);

        return response()->json($result);
    }

    private function path(Request $request): string
    {
        $request->validate(['file' => ['required', new CsvUploadRule(Headers::row('allocation', AllocationCsv::COLUMNS))]]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return (string) $file->getRealPath();
    }
}
