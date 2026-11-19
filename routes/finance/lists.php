<?php

use App\Http\Controllers\ApplicationController;
use App\Http\Controllers\OwnerController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'can:administer'])->prefix('admin/organisations/{organisation}')->scopeBindings()->group(function () {
    Route::get('/owners', [OwnerController::class, 'index']);
    Route::post('/owners', [OwnerController::class, 'store']);
    Route::patch('/owners/{owner}', [OwnerController::class, 'update']);
    Route::delete('/owners/{owner}', [OwnerController::class, 'destroy']);
    Route::get('/applications', [ApplicationController::class, 'index']);
    Route::post('/applications', [ApplicationController::class, 'store']);
    Route::patch('/applications/{application}', [ApplicationController::class, 'update']);
    Route::delete('/applications/{application}', [ApplicationController::class, 'destroy']);
});
