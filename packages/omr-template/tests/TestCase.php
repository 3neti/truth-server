<?php

namespace LBHurtado\OMRTemplate\Tests;

use LBHurtado\OMRTemplate\OMRTemplateServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            OMRTemplateServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        // Set up test environment
        $app['config']->set('omr-template.default_template_path', __DIR__.'/fixtures/templates');
        $app['config']->set('omr-template.output_path', __DIR__.'/fixtures/output');
        $app['config']->set('omr-template.default_layout', 'A4');
        $app['config']->set('omr-template.dpi', 300);
    }
}
