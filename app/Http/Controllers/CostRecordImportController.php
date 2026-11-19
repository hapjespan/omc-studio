<?php

namespace App\Http\Controllers;

use App\Models\Organisation;
use App\Rules\CsvUploadRule;
use App\Services\Exchange\CostRecordImport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

class CostRecordImportController extends Controller
{
    public function __construct(private readonly CostRecordImport $import) {}

    public function preview(Request $request, Organisation $organisation): JsonResponse
    {
        $request->validate(['file' => ['required', new CsvUploadRule(CostRecordImport::COLUMNS)]]);

        /** @var UploadedFile $file */
        $file = $request->file('file');

        return response()->json($this->import->preview($organisation, (string) $file->getRealPath()));
    }
}
