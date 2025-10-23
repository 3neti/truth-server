<?php

use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('templates')->name('templates.')->group(function () {
    Route::post('/render', [TemplateController::class, 'render'])->name('render');
    Route::post('/validate', [TemplateController::class, 'validate'])->name('validate');
    Route::get('/layouts', [TemplateController::class, 'layouts'])->name('layouts');
    Route::get('/samples', [TemplateController::class, 'samples'])->name('samples');
    Route::get('/download/{documentId}', [TemplateController::class, 'download'])->name('download');
    Route::get('/coords/{documentId}', [TemplateController::class, 'coords'])->name('coords');
});
