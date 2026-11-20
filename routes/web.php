<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\AuditLogController;
use App\Http\Controllers\Admin\IndexController as AdminIndexController;
use App\Http\Controllers\Admin\OrganisationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UserPasswordResetController;
use App\Http\Controllers\Admin\UserSecondFactorController;
use App\Http\Controllers\Admin\UserSessionsController;
use App\Http\Controllers\Auth\KeepAliveController;
use App\Http\Controllers\Auth\NewPasswordController;
use App\Http\Controllers\Auth\ReauthenticateController;
use App\Http\Controllers\Auth\SecondFactorChallengeController;
use App\Http\Controllers\Auth\SignInController;
use App\Http\Controllers\Auth\SignOutController;
use App\Http\Controllers\CanvasController;
use App\Http\Controllers\CanvasEntryController;
use App\Http\Controllers\CanvasModeController;
use App\Http\Controllers\CanvasViewController;
use App\Http\Controllers\DrawingController;
use App\Http\Controllers\ElementController;
use App\Http\Controllers\OrganisationLogoController;
use App\Http\Controllers\OverviewController;
use App\Http\Controllers\QuickEntryController;
use App\Http\Controllers\SecondFactorController;
use App\Http\Controllers\StatementController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UndoController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/sign-in', [SignInController::class, 'create'])->name('login');
    Route::post('/sign-in', [SignInController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/', OverviewController::class);
    Route::get('/overview', OverviewController::class);
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
    Route::get('/admin/users', [UserController::class, 'index']);
    Route::post('/admin/users', [UserController::class, 'store']);
    Route::patch('/admin/users/{user}', [UserController::class, 'update']);
    Route::delete('/admin/users/{user}', [UserController::class, 'destroy']);
    Route::get('/admin/audit-log', AuditLogController::class);
});

Route::middleware('auth')->group(function () {
    Route::get('/organisations/{organisation}/logo', OrganisationLogoController::class);
});

Route::middleware(['auth', 'can:administer'])->prefix('admin/organisations')->group(function () {
    Route::get('/', [OrganisationController::class, 'index']);
    Route::post('/', [OrganisationController::class, 'store']);
    Route::patch('/{organisation}', [OrganisationController::class, 'update']);
    Route::delete('/{organisation}', [OrganisationController::class, 'destroy']);
    Route::post('/{organisation}/archive', [OrganisationController::class, 'archive']);
    Route::post('/{organisation}/restore', [OrganisationController::class, 'restore']);
});

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::post('/canvases', [CanvasController::class, 'store']);
    Route::patch('/canvases/{canvas}', [CanvasController::class, 'update']);
    Route::post('/canvases/{canvas}/archive', [CanvasController::class, 'archive']);
    Route::post('/canvases/{canvas}/restore', [CanvasController::class, 'restore']);
    Route::delete('/canvases/{canvas}', [CanvasController::class, 'destroy']);
});

Route::middleware('auth')->group(function () {
    Route::get('/canvases/{canvas}/view', CanvasViewController::class);
});

Route::middleware(['auth', 'can:administer'])->prefix('canvases/{canvas}/elements')->group(function () {
    Route::post('/', [ElementController::class, 'store']);
    Route::patch('/{element}', [ElementController::class, 'update']);
    Route::post('/{element}/reorder', [ElementController::class, 'reorder']);
    Route::post('/{element}/archive', [ElementController::class, 'archive']);
    Route::post('/{element}/restore', [ElementController::class, 'restore']);
    Route::delete('/{element}', [ElementController::class, 'destroy']);
});

Route::middleware('auth')->group(function () {
    Route::get('/canvases/{canvas}/entry', CanvasEntryController::class);
});

Route::middleware(['auth', 'can:administer'])->group(function () {
    Route::patch('/templates/{canvas}', [TemplateController::class, 'update']);
    Route::delete('/templates/{canvas}', [TemplateController::class, 'destroy']);
});

Route::middleware(['auth', 'can:administer'])->prefix('canvases/{canvas}')->group(function () {
    Route::put('/statements/{kind}', [StatementController::class, 'text'])->whereIn('kind', ['mission', 'vision']);
    Route::post('/goals', [StatementController::class, 'store']);
    Route::patch('/goals/{goal}', [StatementController::class, 'update']);
    Route::post('/goals/{goal}/reorder', [StatementController::class, 'reorder']);
    Route::delete('/goals/{goal}', [StatementController::class, 'destroy']);
});

Route::middleware(['auth', 'can:administer'])->prefix('canvases/{canvas}/quick-entry')->group(function () {
    Route::post('/preview', [QuickEntryController::class, 'preview']);
    Route::post('/', [QuickEntryController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::get('/canvases/{canvas}/drawing', DrawingController::class);
});
