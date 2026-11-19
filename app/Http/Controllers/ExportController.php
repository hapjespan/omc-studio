<?php

namespace App\Http\Controllers;

use App\Models\Canvas;
use App\Models\Organisation;
use App\Services\Exchange\AllocationCsv;
use App\Services\Exchange\CostRecordCsv;
use Closure;
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
