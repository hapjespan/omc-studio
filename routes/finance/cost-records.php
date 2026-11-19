<?php

use App\Http\Controllers\FiscalYearController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/organisations/{organisation}/fiscal-years', [FiscalYearController::class, 'store']);
    Route::delete('/fiscal-years/{fiscalYear}', [FiscalYearController::class, 'destroy']);
});
