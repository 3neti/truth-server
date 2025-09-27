<?php

namespace TruthQr\Support;

use Illuminate\Support\Facades\Route;
use TruthQr\Http\Controllers\TruthIngestController;

final class RouteRegistrar
{
    /**
     * Register Truth QR routes inside a caller-provided Route::group().
     *
     * Example (host app):
     *   \TruthQr\Support\RouteRegistrar::register([
     *       'prefix' => 'truth',
     *       'middleware' => ['web', 'throttle:60,1'],
     *   ]);
     */
    public static function register(array $groupOptions = []): void
    {
        Route::group($groupOptions, function () {
            Route::post('/ingest',  [TruthIngestController::class, 'ingest'])->name('truth.ingest');
            Route::get('/status/{code}', [TruthIngestController::class, 'status'])->name('truth.status');
            Route::get('/artifact/{code}', [TruthIngestController::class, 'artifact'])->name('truth.artifact');
        });
    }
}
