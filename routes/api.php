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
| Truth Templates API routes are in routes/truth-templates_api.php
| All core template processing now uses Laravel Actions directly.
|
*/

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

// ===========================================================================
// Truth Templates API Routes
// ===========================================================================
require __DIR__.'/truth-templates_api.php';
