<?php

use App\Http\Controllers\Api\TemplateController;
use App\Http\Controllers\Api\TemplateFamilyController;
use App\Http\Controllers\Api\TemplateDataController;
use App\Http\Controllers\Api\DataValidationController;
use Illuminate\Support\Facades\Route;

use App\Actions\TruthTemplates\Templates\{GetLayoutPresets, GetSampleTemplates};
use App\Actions\TruthTemplates\Rendering\{GetCoordinatesMap, DownloadRenderedPdf, ValidateTemplateSpec, RenderTemplateSpec};
use App\Actions\TruthTemplates\Compilation\{CompileHandlebarsTemplate, CompileStandaloneData};

/*
|--------------------------------------------------------------------------
| Truth Templates API Routes
|--------------------------------------------------------------------------
|
| API routes for Truth Templates system.
|
*/

Route::prefix('truth-templates')
    ->name('api.truth-templates.')
    ->group(function () {

        // -----------------------------------------------------------------------
        // Core Template Operations
        // Updated: 2025-01-27 Phase 3 - Migrated rendering action
        // -----------------------------------------------------------------------

        Route::post('/render', RenderTemplateSpec::class)
            ->name('render');

        Route::post('/validate', ValidateTemplateSpec::class)
            ->name('validate');

        Route::post('/compile', CompileHandlebarsTemplate::class)
            ->name('compile');

        Route::post('/compile-standalone', CompileStandaloneData::class)
            ->name('compile-standalone');

        // -----------------------------------------------------------------------
        // Template Library/Registry CRUD
        // -----------------------------------------------------------------------

        Route::apiResource('templates', TemplateController::class);

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
        // Updated: 2025-01-27 - Migrated to Laravel Actions for direct route binding
        Route::get('/layouts', GetLayoutPresets::class)
            ->name('layouts');

        Route::get('/samples', GetSampleTemplates::class)
            ->name('samples');

        Route::get('/download/{documentId}', DownloadRenderedPdf::class)
            ->name('download');

        Route::get('/coords/{documentId}', GetCoordinatesMap::class)
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

        Route::post('/data/{dataFile}/validate', [TemplateDataController::class, 'validate'])
            ->name('data.validate');

        Route::post('/data/{dataFile}/compile', [TemplateDataController::class, 'compile'])
            ->name('data.compile');

        Route::post('/data/{dataFile}/render', [TemplateDataController::class, 'render'])
            ->name('data.render');

        // -----------------------------------------------------------------------
        // Data Validation
        // -----------------------------------------------------------------------

        Route::post('/validate-data', [DataValidationController::class, 'validateData'])
            ->name('validate-data');

        // -----------------------------------------------------------------------
        // Rendering Jobs
        // -----------------------------------------------------------------------

        Route::get('/jobs', [\App\Http\Controllers\Api\RenderingJobsController::class, 'index'])
            ->name('jobs.index');

        Route::post('/jobs', [\App\Http\Controllers\Api\RenderingJobsController::class, 'store'])
            ->name('jobs.store');

        Route::get('/jobs/{job}', [\App\Http\Controllers\Api\RenderingJobsController::class, 'show'])
            ->name('jobs.show');

        Route::post('/jobs/{job}/retry', [\App\Http\Controllers\Api\RenderingJobsController::class, 'retry'])
            ->name('jobs.retry');

        Route::post('/jobs/{job}/cancel', [\App\Http\Controllers\Api\RenderingJobsController::class, 'cancel'])
            ->name('jobs.cancel');

        Route::delete('/jobs/{job}', [\App\Http\Controllers\Api\RenderingJobsController::class, 'destroy'])
            ->name('jobs.destroy');
    });
