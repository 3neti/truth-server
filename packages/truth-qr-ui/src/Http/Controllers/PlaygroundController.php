<?php

namespace TruthQrUi\Http\Controllers;

use Illuminate\Http\Request;

/**
 * PlaygroundController
 *
 * Purpose
 * -------
 * A thin, opt-in controller that renders a UI "playground" page so you can try
 * the encode/decode HTTP endpoints exposed by this package.
 *
 * Design
 * ------
 * • This package does NOT ship Inertia/Vue or any frontend toolchain.
 * • The *host application* owns Inertia + Vue 3 (or any UI) and the page file.
 *
 * How it works
 * ------------
 * • If the host app has Inertia installed, we render the page specified by
 *   config('truth-qr-ui.playground.inertia_page'), defaulting to 'TruthQrUi/Playground'.
 * • If Inertia is missing, we fail loudly with HTTP 501 and a helpful message.
 *
 * Host app setup (summary)
 * ------------------------
 * 1) Install Inertia + Vue in the host app (not in this package), e.g.:
 *      composer require inertiajs/inertia-laravel
 *      php artisan inertia:middleware
 *      npm i -D vite
 *      npm i vue @inertiajs/vue3
 *
 * 2) Create the page component in the host app, e.g.:
 *      resources/js/Pages/TruthQrUi/Playground.vue
 *
 * 3) Register routes (host app or package):
 *      use TruthQrUi\Http\Controllers\PlaygroundController;
 *      use TruthQrUi\Http\Controllers\EncodeController;
 *      use TruthQrUi\Http\Controllers\DecodeController;
 *      Route::post('/api/encode', EncodeController::class)->name('truth-qr.encode');
 *      Route::post('/api/decode', DecodeController::class)->name('truth-qr.decode');
 *      Route::get('/playground', PlaygroundController::class)->name('truth-qr.playground');
 *
 * 4) (Optional) Configure the page name via:
 *      // config/truth-qr-ui.php
 *      return [
 *          'playground' => [
 *              'inertia_page' => 'TruthQrUi/Playground',
 *          ],
 *      ];
 *
 * 5) (Optional) Disable/guard the playground route in production.
 */
final class PlaygroundController
{
    /**
     * Single-action controller entrypoint.
     *
     * Renders the Inertia page and passes:
     * • defaults: sensible default alias values (and envelope prefix/version knobs)
     * • routes:   absolute URLs for encode/decode endpoints (named routes required)
     */
    public function __invoke(Request $request)
    {
        // Fail fast with a helpful message if Inertia isn’t present in the host app.
        if (!class_exists(\Inertia\Inertia::class)) {
            abort(501,
                'Inertia is not installed in the host application. ' .
                'Install Inertia/Vue in the host app and add a page at ' .
                'resources/js/Pages/TruthQrUi/Playground.vue, or override the page name in ' .
                'config("truth-qr-ui.playground.inertia_page").'
            );
        }

        $page = config('truth-qr-ui.playground.inertia_page', 'TruthQrUi/Playground');

        return \Inertia\Inertia::render($page, [
            'defaults' => [
                // Encode/Decode controller alias defaults
                'envelope'         => 'v1url',
                'transport'        => 'base64url+deflate',
                'serializer'       => 'json',

                // New: envelope knobs plumbed through controllers
                'envelope_prefix'  => 'ER',  // logical family (e.g. ER, TRUTH, BAL)
                'envelope_version' => 'v1',  // semantic version token

                // Example payload the UI can show/edit
                'example'          => [
                    'type' => 'demo',
                    'code' => 'DEMO-001',
                    'data' => ['hello' => 'world'],
                ],
            ],
            'routes' => [
                'encode' => route('truth-qr.encode'),
                'decode' => route('truth-qr.decode'),
            ],
        ]);
    }
}
