<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Admin\FamiliesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Admin Routes
|--------------------------------------------------------------------------
|
| Routes for the Vuestic admin panel. All routes require authentication
| and admin/editor permissions.
|
*/

Route::prefix('admin')
    ->name('admin.')
    ->middleware(['auth', 'verified'])
    ->group(function () {
        // Dashboard
        Route::get('/', [AdminController::class, 'dashboard'])
            ->name('dashboard');

        // Template Families
        Route::resource('families', FamiliesController::class);

        // Templates
        Route::get('/templates', function () {
            return inertia('Admin/pages/Templates/Index');
        })->name('templates.index');
        
        Route::get('/templates/create', function () {
            return inertia('Admin/pages/Templates/Edit');
        })->name('templates.create');
        
        Route::get('/templates/{id}/edit', function (string $id) {
            return inertia('Admin/pages/Templates/Edit', [
                'templateId' => $id,
            ]);
        })->name('templates.edit');

        // Template Data
        Route::get('/data', function () {
            return inertia('Admin/pages/Data/Index');
        })->name('data.index');
        
        Route::get('/data/create', function () {
            return inertia('Admin/pages/Data/Edit');
        })->name('data.create');
        
        Route::get('/data/{id}/edit', function (string $id) {
            return inertia('Admin/pages/Data/Edit', [
                'id' => (int)$id,
            ]);
        })->name('data.edit');

        // Rendering Jobs (placeholder for future implementation)
        Route::get('/jobs', function () {
            return inertia('Admin/pages/Jobs/Index');
        })->name('jobs.index');

        // Assets (placeholder for future implementation)
        Route::get('/assets', function () {
            return inertia('Admin/pages/Assets/Index');
        })->name('assets.index');

        // Settings (placeholder for future implementation)
        Route::get('/settings', function () {
            return inertia('Admin/pages/Settings/Index');
        })->name('settings.index');
    });
