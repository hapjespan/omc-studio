<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Rules\CsvUploadRule;
use App\Services\Exchange\CostRecordImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Inertia\Inertia;
use Inertia\Response;

class CostRecordImportController extends Controller
{
    public function __construct(private readonly CostRecordImport $import) {}

    public function show(Organisation $organisation): Response
    {
        return Inertia::render('Finance/CostRecordImport', [
            'organisation' => $organisation->only(['id', 'name']),
            'columns' => CostRecordImport::COLUMNS,
        ]);
    }

    public function preview(Request $request, Organisation $organisation): JsonResponse
    {
        return response()->json($this->import->preview($organisation, $this->path($request)));
    }

    public function commit(Request $request, Organisation $organisation): JsonResponse
    {
        return response()->json($this->import->commit($organisation, $this->path($request)));
    }

    private function path(Request $request): string
    {
        ini_set('memory_limit', '1G');

        $request->validate(['file' => ['required', new CsvUploadRule(CostRecordImport::COLUMNS)]]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return (string) $file->getRealPath();
    }
}
