<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;


Route::get('/truth/templates/uploader/ui', function () {
    return Inertia::render('TruthRenderer/TemplateUploaderUi');
})->name('truth-template.uploader.ui');

//Route::get('/truth/templates/uploader/ui', fn () => Inertia::render('@truth-renderer::TemplateUploaderUi'))
//    ->name('truth-template.uploader.ui');
