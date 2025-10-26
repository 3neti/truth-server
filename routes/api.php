<?php

use App\Http\Controllers\Api\TemplateDataController;
use App\Http\Controllers\Api\TemplateFamilyController;
use App\Http\Controllers\TemplateController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Note: Truth Templates API routes are now unified under /api/truth-templates
| See routes/truth-templates.php for the canonical definitions.
| The routes below are legacy aliases for backwards compatibility.
|
*/

// ===========================================================================
// LEGACY API ROUTES (Deprecated)
// These routes redirect to the new unified /api/truth-templates endpoints
// ===========================================================================

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
    Route::post('/compile-standalone', [TemplateController::class, 'compileStandalone'])->name('compileStandalone');

    // New: Template library CRUD
    Route::get('/library', [TemplateController::class, 'listTemplates'])->name('library.index');
    Route::get('/library/{id}', [TemplateController::class, 'getTemplate'])->name('library.show');
    Route::post('/library', [TemplateController::class, 'saveTemplate'])->name('library.store');
    Route::put('/library/{id}', [TemplateController::class, 'updateTemplate'])->name('library.update');
    Route::delete('/library/{id}', [TemplateController::class, 'deleteTemplate'])->name('library.delete');
    
    // Version history
    Route::get('/library/{id}/versions', [TemplateController::class, 'getVersionHistory'])->name('library.versions');
    Route::post('/library/{templateId}/rollback/{versionId}', [TemplateController::class, 'rollbackToVersion'])->name('library.rollback');
    
    // Validation and signing
    Route::post('/library/{id}/validate-data', [TemplateController::class, 'validateData'])->name('library.validateData');
    Route::post('/library/{id}/sign', [TemplateController::class, 'signTemplate'])->name('library.sign');
    Route::get('/library/{id}/verify', [TemplateController::class, 'verifyTemplate'])->name('library.verify');
});

// Template Families API
Route::prefix('template-families')->name('template-families.')->group(function () {
    Route::get('/', [TemplateFamilyController::class, 'index'])->name('index');
    Route::post('/', [TemplateFamilyController::class, 'store'])->name('store');
    Route::get('/{id}', [TemplateFamilyController::class, 'show'])->name('show');
    Route::put('/{id}', [TemplateFamilyController::class, 'update'])->name('update');
    Route::delete('/{id}', [TemplateFamilyController::class, 'destroy'])->name('destroy');
    Route::get('/{id}/variants', [TemplateFamilyController::class, 'variants'])->name('variants');
    Route::get('/{id}/export', [TemplateFamilyController::class, 'export'])->name('export');
    Route::post('/import', [TemplateFamilyController::class, 'import'])->name('import');
});

// Template Data API
Route::prefix('template-data')->name('template-data.')->group(function () {
    Route::get('/', [TemplateDataController::class, 'index'])->name('index');
    Route::post('/', [TemplateDataController::class, 'store'])->name('store');
    Route::get('/{dataFile}', [TemplateDataController::class, 'show'])->name('show');
    Route::put('/{dataFile}', [TemplateDataController::class, 'update'])->name('update');
    Route::delete('/{dataFile}', [TemplateDataController::class, 'destroy'])->name('destroy');
    
    // Validation
    Route::post('/{dataFile}/validate', [\App\Http\Controllers\Api\DataValidationController::class, 'validateDataFile'])->name('validate');
});

// Data Validation API
Route::post('/data/validate', [\App\Http\Controllers\Api\DataValidationController::class, 'validateData'])->name('data.validate');
