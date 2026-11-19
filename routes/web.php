<?php

use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\UndoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'));

Route::middleware('guest')->group(function () {
    Route::get('/sign-in', [SignInController::class, 'create'])->name('login');
    Route::post('/sign-in', [SignInController::class, 'store']);
});

Route::middleware('can:administer')->scopeBindings()->group(function () {
    Route::post('/undo/{token}', UndoController::class)->name('undo');
});
