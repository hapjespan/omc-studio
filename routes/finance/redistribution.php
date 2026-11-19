<?php

use App\Http\Controllers\RedistributionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::get('/canvases/{canvas}/elements/{element}/redistribution', [RedistributionController::class, 'preview']);
    Route::post('/canvases/{canvas}/elements/{element}/redistribution', [RedistributionController::class, 'apply']);
});
