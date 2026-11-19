<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Rules\ElementName;
use App\Rules\TrimmedText;
use App\Services\Audit\SecurityLog;
use App\Services\Exchange\CanvasImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CanvasImportController extends Controller
{
    public function __construct(private readonly CanvasImport $import) {}

    public function preview(Request $request): JsonResponse
    {
        [$content, $target, $canvasName, $organisationName] = $this->input($request);

        return response()->json($this->import->preview($content, $target, $canvasName, $organisationName));
    }

    public function commit(Request $request): JsonResponse
    {
        [$content, $target, $canvasName, $organisationName] = $this->input($request);
        $result = $this->import->commit($content, $target, $canvasName, $organisationName);

        SecurityLog::record('canvas_imported', ['organisation_id' => $result['organisation_id'], 'canvas_id' => $result['canvas_id']]);

        return response()->json($result);
    }

    /** @return array{string, ?Organisation, string, ?string} */
    private function input(Request $request): array
    {
        ini_set('memory_limit', '1G');

        $data = $request->validate([
            'file' => ['required', 'file'],
            'organisation_id' => ['nullable', 'integer', 'exists:organisations,id'],
            'organisation_name' => ['required_without:organisation_id', 'nullable', 'string', new TrimmedText(__('fields.name'), 150)],
            'canvas_name' => ['required', 'string', new ElementName],
        ]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return [
            (string) file_get_contents((string) $file->getRealPath()),
            isset($data['organisation_id']) ? Organisation::findOrFail($data['organisation_id']) : null,
            (string) $data['canvas_name'],
            $data['organisation_name'] ?? null,
        ];
    }
}
