<?php

use Illuminate\Support\Facades\Route;
use TruthQr\Http\Controllers\TruthIngestController;

/*
|--------------------------------------------------------------------------
| TRUTH QR Routes
|--------------------------------------------------------------------------
|
| Endpoints for ingesting TRUTH envelope lines (deep-link/webhook flow),
| querying the current assembly status, and streaming completed artifacts.
|
*/

Route::prefix('truth')->group(function () {
    // Ingest a new envelope line (POST body param: line)
    Route::post('/ingest', [TruthIngestController::class, 'ingest']);

    // Status snapshot for a code (JSON: code, total, received, missing, complete)
    Route::get('/status/{code}', [TruthIngestController::class, 'status']);

    // Stream assembled artifact for a code (JSON/YAML, with correct MIME type)
    Route::get('/artifact/{code}', [TruthIngestController::class, 'artifact']);
});
