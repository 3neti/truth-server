<?php

use TruthRenderer\Http\Controllers\TruthTemplateUploadController;
use TruthRenderer\Http\Controllers\TruthRenderController;
use TruthRenderer\Http\Controllers\PdfRenderController;
use Illuminate\Support\Facades\Route;

Route::get('/truth/templates', [TruthRenderController::class, 'listTemplates'])->name('truth.templates');
Route::post('/truth/render',  [TruthRenderController::class, 'render'])->name('truth-render');


Route::post('/truth/templates/upload', [TruthTemplateUploadController::class, 'upload'])->name('truth-template.upload');

Route::post('/render-pdf', PdfRenderController::class)->name('api.render-pdf');
