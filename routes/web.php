<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('/truth', function () {
    return Inertia::render('Truth');
});

// ===========================================================================
// Truth Templates Routes (New Unified System)
// ===========================================================================
//require __DIR__.'/truth-templates.php';

// ===========================================================================
// Legacy Template Routes (Deprecated - Redirects to new routes)
// ===========================================================================
Route::get('/templates/editor', function () {
    return redirect()->route('truth-templates.editor');
})->name('templates.editor');

Route::get('/templates/advanced', function () {
    return redirect()->route('truth-templates.advanced');
})->name('templates.advanced');

Route::get('/data-editor-demo', function () {
    return redirect()->route('truth-templates.data.demo');
})->name('data-editor.demo');

Route::get('/data/editor', function () {
    return redirect()->route('truth-templates.data.editor');
})->name('data.editor');

require __DIR__.'/truth-templates_web.php';
require __DIR__.'/admin.php';
require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
