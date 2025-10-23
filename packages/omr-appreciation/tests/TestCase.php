<?php

namespace LBHurtado\OMRAppreciation\Tests;

use LBHurtado\OMRAppreciation\OMRAppreciationServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OMRAppreciationServiceProvider::class,
        ];
    }
}
