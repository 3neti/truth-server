<?php

use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

Route::prefix('templates')->name('templates.')->group(function () {
    // Existing routes
    Route::post('/render', [TemplateController::class, 'render'])->name('render');
    Route::post('/validate', [TemplateController::class, 'validate'])->name('validate');
    Route::get('/layouts', [TemplateController::class, 'layouts'])->name('layouts');
    Route::get('/samples', [TemplateController::class, 'samples'])->name('samples');
    Route::get('/download/{documentId}', [TemplateController::class, 'download'])->name('download');
    Route::get('/coords/{documentId}', [TemplateController::class, 'coords'])->name('coords');

    // New: Handlebars compilation
    Route::post('/compile', [TemplateController::class, 'compile'])->name('compile');

    // New: Template library CRUD
    Route::get('/library', [TemplateController::class, 'listTemplates'])->name('library.index');
    Route::get('/library/{id}', [TemplateController::class, 'getTemplate'])->name('library.show');
    Route::post('/library', [TemplateController::class, 'saveTemplate'])->name('library.store');
    Route::put('/library/{id}', [TemplateController::class, 'updateTemplate'])->name('library.update');
    Route::delete('/library/{id}', [TemplateController::class, 'deleteTemplate'])->name('library.delete');
});
