<?php

namespace TruthRenderer\Tests;

use Orchestra\Testbench\TestCase as BaseTestCase;
use TruthRenderer\TruthRendererServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function getPackageProviders($app)
    {
        return [
            TruthRendererServiceProvider::class,
        ];
    }
}
