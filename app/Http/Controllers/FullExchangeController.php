<?php

namespace App\Http\Controllers;

use App\Services\Audit\SecurityLog;
use App\Services\Exchange\FullExport;
use Illuminate\Http\JsonResponse;
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
        SecurityLog::record('full_exported');

        return response()->json($export->build(), 200, ['Content-Disposition' => 'attachment; filename='.$export->fileName()], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
