<?php

use App\Http\Controllers\CostRecordController;
use App\Http\Controllers\FiscalYearController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/organisations/{organisation}/fiscal-years', [FiscalYearController::class, 'store']);
    Route::delete('/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'destroy']);
    Route::post('/organisations/{organisation}/cost-records', [CostRecordController::class, 'store']);
    Route::patch('/cost-records/{costRecord}', [CostRecordController::class, 'update']);
});
