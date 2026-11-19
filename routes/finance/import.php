<?php

use App\Http\Controllers\CostRecordImportController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/organisations/{organisation}/cost-records/import/preview', [CostRecordImportController::class, 'preview']);
    Route::post('/organisations/{organisation}/cost-records/import', [CostRecordImportController::class, 'commit']);
});
