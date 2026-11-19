<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn () => Inertia::render('Home'));

Route::middleware('can:administer')->scopeBindings()->group(function () {});
