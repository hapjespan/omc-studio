<?php

use App\Http\Controllers\FinaliseController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/canvases/{canvas}/years/{year}/finalise', [FinaliseController::class, 'store'])->where('year', '[0-9]{4}');
    Route::post('/canvases/{canvas}/years/{year}/reopen', [FinaliseController::class, 'reopen'])->where('year', '[0-9]{4}');
});
