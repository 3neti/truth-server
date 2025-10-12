<?php

use TruthQrUi\Http\Controllers\PlaygroundController;
use TruthQrUi\Http\Controllers\EncodeController;
use TruthQrUi\Http\Controllers\DecodeController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/api/encode', EncodeController::class)->name('truth-qr.encode');
Route::post('/api/decode', DecodeController::class)->name('truth-qr.decode');
Route::get('/playground', PlaygroundController::class)->name('truth-qr.playground');

// Simple TRUTH Decoder Interface
Route::get('/truth-simple', function () {
    return Inertia::render('TruthQrUi/TruthSimple');
})->name('truth-qr.decoder');

// routes/web.php (host app or package)
use TruthQrUi\Http\Controllers\StubDownloadController;
Route::get('/truth-qr-ui/stubs.zip', StubDownloadController::class)
    ->name('truth-qr-ui.stubs.download');
