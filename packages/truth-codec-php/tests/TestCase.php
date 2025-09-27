<?php

namespace TruthCodec\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'TruthCodec\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );

        // Load configuration files
        $this->loadConfig();
    }

    protected function getPackageProviders($app)
    {
        return [
            \TruthCodec\TruthCodecServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {

    }

    /**
     * Load the package configuration files.
     */
    protected function loadConfig()
    {
        $this->app['config']->set(
            'truth-codec',
            require __DIR__ . '/../config/truth-codec.php'
        );
    }
}
