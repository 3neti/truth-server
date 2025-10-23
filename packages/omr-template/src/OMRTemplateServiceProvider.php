<?php

namespace LBHurtado\OMRTemplate;

use Illuminate\Support\ServiceProvider;
use LBHurtado\OMRTemplate\Commands\GenerateOMRCommand;
use LBHurtado\OMRTemplate\Services\BarcodeGenerator;
use LBHurtado\OMRTemplate\Services\DocumentIdGenerator;
use LBHurtado\OMRTemplate\Services\FiducialHelper;
use LBHurtado\OMRTemplate\Services\HandlebarsEngine;
use LBHurtado\OMRTemplate\Services\TemplateExporter;
use LBHurtado\OMRTemplate\Services\TemplateRenderer;
use LBHurtado\OMRTemplate\Services\ZoneGenerator;

class OMRTemplateServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../config/omr-template.php',
            'omr-template'
        );

        $this->app->singleton(HandlebarsEngine::class);
        $this->app->singleton(TemplateRenderer::class);
        $this->app->singleton(TemplateExporter::class);
        $this->app->singleton(FiducialHelper::class);
        $this->app->singleton(DocumentIdGenerator::class);
        $this->app->singleton(BarcodeGenerator::class);
        $this->app->singleton(ZoneGenerator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/omr-template.php' => config_path('omr-template.php'),
            ], 'omr-config');

            $this->publishes([
                __DIR__.'/../resources/templates' => resource_path('templates'),
            ], 'omr-templates');

            $this->commands([
                GenerateOMRCommand::class,
            ]);
        }
    }
}
