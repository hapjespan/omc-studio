<?php

use App\Http\Controllers\CarryOverController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/canvases/{canvas}/years/{year}/carry-over', [CarryOverController::class, 'run'])->where('year', '[0-9]{4}');
});
