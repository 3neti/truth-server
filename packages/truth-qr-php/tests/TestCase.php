<?php

declare(strict_types=1);

namespace TruthQr\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TruthQr\TruthQrServiceProvider;
use TruthCodec\TruthCodecServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        // truth-qr-php depends on truth-codec-php (for Envelope classes)
        return [
            TruthCodecServiceProvider::class,
            TruthQrServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        // Optional: override any config defaults here during tests
        $app['config']->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
    }

    protected function defineRoutes($router): void
    {
        // Option A: just include your package route file
        require __DIR__ . '/../routes/truth-qr.php';

        // Option B (if you want to mount under /api with middleware during tests):
        // $router->middleware('throttle:60,1')->group(function () use ($router) {
        //     require __DIR__ . '/../routes/truth-qr.php';
        // });
    }
}
