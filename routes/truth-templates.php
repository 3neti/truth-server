<?php

use App\Http\Controllers\TemplateController;
use App\Http\Controllers\Api\TemplateFamilyController;
use App\Http\Controllers\Api\TemplateDataController;
use App\Http\Controllers\Api\DataValidationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Truth Templates Routes
|--------------------------------------------------------------------------
|
| Unified routing for the Truth Templates system. Includes both web pages
| and API endpoints under the /truth-templates prefix for consistency.
|
*/

// ===========================================================================
// WEB ROUTES - User-facing template pages
// ===========================================================================

Route::prefix('truth-templates')
    ->name('truth-templates.')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        // Main template index/dashboard
        Route::get('/', function () {
            return Inertia::render('Templates/Index');
        })->name('index');

        // Simple template editor
        Route::get('/editor', function () {
            return Inertia::render('Templates/Editor');
        })->name('editor');

        // Advanced template editor
        Route::get('/advanced', function () {
            return Inertia::render('Templates/AdvancedEditor');
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

// ===========================================================================
// API ROUTES - Template system endpoints
// ===========================================================================

Route::prefix('api/truth-templates')
    ->name('api.truth-templates.')
    ->group(function () {
        
        // -----------------------------------------------------------------------
        // Core Template Operations
        // -----------------------------------------------------------------------
        
        Route::post('/render', [TemplateController::class, 'render'])
            ->name('render');
        
        Route::post('/validate', [TemplateController::class, 'validate'])
            ->name('validate');
        
        Route::post('/compile', [TemplateController::class, 'compile'])
            ->name('compile');
        
        Route::post('/compile-standalone', [TemplateController::class, 'compileStandalone'])
            ->name('compile-standalone');

        // -----------------------------------------------------------------------
        // Template Library/Registry CRUD
        // -----------------------------------------------------------------------
        
        Route::get('/templates', [TemplateController::class, 'listTemplates'])
            ->name('templates.index');
        
        Route::post('/templates', [TemplateController::class, 'saveTemplate'])
            ->name('templates.store');
        
        Route::get('/templates/{id}', [TemplateController::class, 'getTemplate'])
            ->name('templates.show');
        
        Route::put('/templates/{id}', [TemplateController::class, 'updateTemplate'])
            ->name('templates.update');
        
        Route::delete('/templates/{id}', [TemplateController::class, 'deleteTemplate'])
            ->name('templates.destroy');

        // Template versioning
        Route::get('/templates/{id}/versions', [TemplateController::class, 'getVersionHistory'])
            ->name('templates.versions');
        
        Route::post('/templates/{templateId}/rollback/{versionId}', [TemplateController::class, 'rollbackToVersion'])
            ->name('templates.rollback');

        // Template validation and signing
        Route::post('/templates/{id}/validate-data', [TemplateController::class, 'validateData'])
            ->name('templates.validate-data');
        
        Route::post('/templates/{id}/sign', [TemplateController::class, 'signTemplate'])
            ->name('templates.sign');
        
        Route::get('/templates/{id}/verify', [TemplateController::class, 'verifyTemplate'])
            ->name('templates.verify');

        // Template utilities
        Route::get('/layouts', [TemplateController::class, 'layouts'])
            ->name('layouts');
        
        Route::get('/samples', [TemplateController::class, 'samples'])
            ->name('samples');
        
        Route::get('/download/{documentId}', [TemplateController::class, 'download'])
            ->name('download');
        
        Route::get('/coords/{documentId}', [TemplateController::class, 'coords'])
            ->name('coords');

        // -----------------------------------------------------------------------
        // Template Families
        // -----------------------------------------------------------------------
        
        Route::get('/families', [TemplateFamilyController::class, 'index'])
            ->name('families.index');
        
        Route::post('/families', [TemplateFamilyController::class, 'store'])
            ->name('families.store');
        
        Route::get('/families/{id}', [TemplateFamilyController::class, 'show'])
            ->name('families.show');
        
        Route::put('/families/{id}', [TemplateFamilyController::class, 'update'])
            ->name('families.update');
        
        Route::delete('/families/{id}', [TemplateFamilyController::class, 'destroy'])
            ->name('families.destroy');
        
        Route::get('/families/{id}/templates', [TemplateFamilyController::class, 'variants'])
            ->name('families.templates');
        
        Route::get('/families/{id}/export', [TemplateFamilyController::class, 'export'])
            ->name('families.export');
        
        Route::post('/families/import', [TemplateFamilyController::class, 'import'])
            ->name('families.import');

        // -----------------------------------------------------------------------
        // Template Data
        // -----------------------------------------------------------------------
        
        Route::get('/data', [TemplateDataController::class, 'index'])
            ->name('data.index');
        
        Route::post('/data', [TemplateDataController::class, 'store'])
            ->name('data.store');
        
        Route::get('/data/{dataFile}', [TemplateDataController::class, 'show'])
            ->name('data.show');
        
        Route::put('/data/{dataFile}', [TemplateDataController::class, 'update'])
            ->name('data.update');
        
        Route::delete('/data/{dataFile}', [TemplateDataController::class, 'destroy'])
            ->name('data.destroy');
        
        Route::post('/data/{dataFile}/validate', [DataValidationController::class, 'validateDataFile'])
            ->name('data.validate-file');

        // -----------------------------------------------------------------------
        // Data Validation
        // -----------------------------------------------------------------------
        
        Route::post('/validate-data', [DataValidationController::class, 'validateData'])
            ->name('validate-data');
    });
