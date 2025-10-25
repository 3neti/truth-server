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

Route::get('/templates/editor', function () {
    return Inertia::render('Templates/Editor');
})->name('templates.editor');

Route::get('/templates/advanced', function () {
    return Inertia::render('Templates/AdvancedEditor');
})->name('templates.advanced');

Route::get('/data-editor-demo', function () {
    return Inertia::render('DataEditorDemo');
})->name('data-editor.demo');

Route::get('/data/editor', function () {
    return Inertia::render('DataFileEditor');
})->name('data.editor');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
