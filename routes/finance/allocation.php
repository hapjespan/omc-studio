<?php

use App\Http\Controllers\AllocationController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::get('/canvases/{canvas}/allocation', [AllocationController::class, 'index']);
    Route::put('/canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation', [AllocationController::class, 'update'])->where('year', '[0-9]{4}');
    Route::delete('/canvases/{canvas}/years/{year}/cost-records/{costRecord}/allocation', [AllocationController::class, 'destroy'])->where('year', '[0-9]{4}');
});
