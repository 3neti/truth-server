<?php

namespace TruthQrUi\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TruthRenderer\TruthRendererServiceProvider;
use TruthCodec\TruthCodecServiceProvider;
use TruthQr\TruthQrServiceProvider;
use TruthQrUi\TruthQrUiServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadConfig();
    }

    protected function getPackageProviders($app)
    {
        // truth-qr-ui depends on truth-qr-php depends on truth-codec-php (Envelope class)
        return [
            TruthCodecServiceProvider::class,
            TruthQrServiceProvider::class,
            TruthQrUiServiceProvider::class,
            TruthRendererServiceProvider::class,
        ];
    }

    protected function loadConfig()
    {
        $this->app['config']->set(
            'truth-renderer',
            require __DIR__ . '/../config/truth-renderer.php'
        );
    }
}
