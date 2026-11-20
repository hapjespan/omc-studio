<?php

namespace App\Http\Controllers;

use App\Enums\CanvasKind;
use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Exchange\AllocationCsv;
use App\Services\Exchange\CanvasExport;
use App\Services\Exchange\CostRecordCsv;
use Closure;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    public function costRecords(Organisation $organisation, CostRecordCsv $csv): StreamedResponse
    {
        return $this->download('cost-records', $csv->omitted($organisation), fn ($handle) => $csv->write($organisation, $handle));
    }

    public function allocations(Canvas $canvas, AllocationCsv $csv): StreamedResponse
    {
        return $this->download('allocations', $csv->omitted($canvas), fn ($handle) => $csv->write($canvas, $handle));
    }

    public function canvasJson(Canvas $canvas, CanvasExport $export): JsonResponse
    {
        abort_if($canvas->kind === CanvasKind::Template, 404);

        return response()->json($export->build($canvas), 200, ['Content-Disposition' => 'attachment; filename='.$export->fileName($canvas)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function download(string $name, int $omitted, Closure $write): StreamedResponse
    {
        return response()->streamDownload(function () use ($write) {
            $handle = fopen('php://output', 'wb');
            $write($handle);
            fclose($handle);
        }, $name.'-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-Omitted-Cost-Records' => (string) $omitted,
        ]);
    }
}
