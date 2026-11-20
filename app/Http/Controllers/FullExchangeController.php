<?php

namespace App\Http\Controllers;

use App\Services\Audit\SecurityLog;
use App\Services\Exchange\FullExport;
use App\Services\Exchange\FullImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class FullExchangeController extends Controller
{
    public function page(): Response
    {
        return Inertia::render('Admin/Exchange');
    }

    public function export(FullExport $export): JsonResponse
    {
        ini_set('memory_limit', '1G');

        $data = $export->build();

        SecurityLog::record('full_exported');

        return response()->json($data, 200, ['Content-Disposition' => 'attachment; filename='.$export->fileName()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    public function preview(Request $request, FullImport $import): JsonResponse
    {
        return response()->json($import->preview($this->content($request)));
    }

    public function commit(Request $request, FullImport $import): JsonResponse
    {
        $result = $import->commit($this->content($request));

        SecurityLog::record('full_imported', $result['totals']);

        return response()->json($result);
    }

    private function content(Request $request): string
    {
        ini_set('memory_limit', '1G');

        try {
            $request->validate(['file' => ['required', 'file']]);
        } catch (ValidationException) {
            throw ValidationException::withMessages(['file' => __('fullx.refused_file')]);
        }

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return (string) file_get_contents((string) $file->getRealPath());
    }
}
