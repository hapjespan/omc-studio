<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\IndexController as AdminIndexController;
use App\Http\Controllers\Admin\UserPasswordResetController;
use App\Http\Controllers\Admin\UserSecondFactorController;
use App\Http\Controllers\Admin\UserSessionsController;
use App\Http\Controllers\Auth\KeepAliveController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\ReauthenticateController;
use App\Http\Controllers\Auth\SecondFactorChallengeController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\CanvasModeController;
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

Route::middleware('auth')->group(function () {
    Route::get('/canvases/{canvas}', CanvasModeController::class);
});

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::get('/admin', AdminIndexController::class);
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

Route::middleware('auth')->group(function () {
    Route::post('/session/reauthenticate', ReauthenticateController::class);
});

Route::middleware('auth')->group(function () {
    Route::post('/session/keep-alive', KeepAliveController::class);
});

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/admin/users/{user}/password-reset', UserPasswordResetController::class);
    Route::delete('/admin/users/{user}/second-factor', UserSecondFactorController::class);
    Route::delete('/admin/users/{user}/sessions', UserSessionsController::class);
});
