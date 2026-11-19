<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\SecondFactorChallengeController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\SecondFactorController;
use App\Http\Controllers\UndoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('/sign-in', [SignInController::class, 'create'])->name('login');
    Route::post('/sign-in', [SignInController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => Inertia::render('Home'));
    Route::post('/sign-out', SignOutController::class);
    Route::get('/password/new', [NewPasswordController::class, 'create']);
    Route::put('/password/new', [NewPasswordController::class, 'update']);
    Route::get('/account', [AccountController::class, 'show']);
    Route::put('/account/password', [AccountController::class, 'update']);
});

Route::middleware('can:administer')->scopeBindings()->group(function () {
    Route::post('/undo/{token}', UndoController::class)->name('undo');
});

Route::middleware('guest')->group(function () {
    Route::get('/sign-in/second-factor', [SecondFactorChallengeController::class, 'create']);
    Route::post('/sign-in/second-factor', [SecondFactorChallengeController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/account/second-factor', [SecondFactorController::class, 'store']);
    Route::post('/account/second-factor/confirm', [SecondFactorController::class, 'confirm']);
    Route::post('/account/second-factor/recovery-codes', [SecondFactorController::class, 'recoveryCodes']);
    Route::delete('/account/second-factor', [SecondFactorController::class, 'destroy']);
});
