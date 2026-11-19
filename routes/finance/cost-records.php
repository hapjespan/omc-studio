<?php

use App\Http\Controllers\CostRecordController;
use App\Http\Controllers\FiscalYearController;
use App\Http\Controllers\YearlyFiguresController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/organisations/{organisation}/fiscal-years', [FiscalYearController::class, 'store']);
    Route::delete('/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'destroy']);
    Route::post('/organisations/{organisation}/cost-records', [CostRecordController::class, 'store']);
    Route::patch('/cost-records/{costRecord}', [CostRecordController::class, 'update']);
    Route::put('/cost-records/{costRecord}/years/{year}', [YearlyFiguresController::class, 'update'])->where('year', '[0-9]{4}');
    Route::delete('/cost-records/{costRecord}/years/{year}', [YearlyFiguresController::class, 'destroy'])->where('year', '[0-9]{4}');
});
