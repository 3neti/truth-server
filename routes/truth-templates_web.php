<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Truth Templates Web Routes
|--------------------------------------------------------------------------
|
| Web routes for Truth Templates UI pages.
|
*/

Route::prefix('truth-templates')
    ->name('truth-templates.')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        // Main template index/dashboard
        Route::get('/', function () {
            return Inertia::render('TruthTemplatesUi/pages/Index');
        })->name('index');

        // Simple template editor
        Route::get('/editor', function () {
            return Inertia::render('TruthTemplatesUi/pages/Editor');
        })->name('editor');

        // Advanced template editor
        Route::get('/advanced', function () {
            return Inertia::render('TruthTemplatesUi/pages/AdvancedEditor');
        })->name('advanced');

        // Data editor
        Route::get('/data/editor', function () {
            return Inertia::render('TruthTemplatesUi/pages/DataEditor');
        })->name('data.editor');

        // Data editor demo (possibly public)
        Route::get('/data/demo', function () {
            return Inertia::render('DataEditorDemo');
        })->name('data.demo');
    });
