<?php

namespace TruthRenderer;

use Dompdf\Options;
use Illuminate\Support\ServiceProvider;
use TruthRenderer\Contracts\RendererInterface;
use TruthRenderer\Contracts\TemplateRegistryInterface;
use TruthRenderer\Engine\HandlebarsEngine;
use TruthRenderer\Template\TemplateAssetsLoader;
use TruthRenderer\Template\TemplateRegistry;
use TruthRenderer\Validation\Validator;

/**
 * Service provider for the TruthRenderer package.
 *
 * Responsibilities:
 * - Registers singletons and interface bindings (Renderer, TemplateRegistry, Dompdf Options).
 * - Merges package config with the host application's config.
 * - Publishes config, template stubs, and Vue components (when running in console).
 * - Loads package routes (if present).
 */
class TruthRendererServiceProvider extends ServiceProvider
{
    /**
     * Register container bindings and merge configuration.
     */
    public function register(): void
    {
        // 1) Merge package config (if present) so app config can override defaults.
        $configPath = __DIR__ . '/../config/truth-renderer.php';
        if (file_exists($configPath)) {
            $this->mergeConfigFrom($configPath, 'truth-renderer');
        }

        // 2) TemplateRegistry singleton (reads namespace=>path map from config with a safe default).
        $this->app->singleton(\TruthRenderer\Template\TemplateRegistry::class, function ($app) {
            // App-level dir (published / user-added)
            $appDir   = base_path('resources/truth-templates');

            // Package stubs dir (shipped with the package)
            $stubDir  = __DIR__ . '/../stubs/templates';

            // Merge with config override (if provided)
            $configPaths = (array) config('truth-renderer.paths', []);

            // Final search paths (namespace => directory)
            $paths = array_merge(
                ['core' => $appDir],
                ['pkg'  => $stubDir],
                $configPaths,
            );

            return new TemplateRegistry($paths);
        });

        // Interface alias → concrete singleton
        $this->app->alias(TemplateRegistry::class, TemplateRegistryInterface::class);

        // 3) Dompdf Options singleton (sane defaults; callers may override via config if needed).
        $this->app->singleton(Options::class, function () {
            return (new Options())
                ->set('isRemoteEnabled', true)
                ->set('isHtml5ParserEnabled', true)
                ->set('defaultFont', 'DejaVu Sans');
        });

        // 4) Renderer singleton (engine + validator + dompdf options).
        $this->app->singleton(Renderer::class, function ($app) {
            return new Renderer(
                engine: new HandlebarsEngine(),
                validator: new Validator(),
                dompdfOptions: $app->make(Options::class),
            );
        });
        // Interface alias → concrete singleton
        $this->app->alias(Renderer::class, RendererInterface::class);

        $this->app->singleton(TemplateAssetsLoader::class, function ($app) {
            return new TemplateAssetsLoader(
                $app->make(TemplateRegistryInterface::class)
            );
        });
    }

    /**
     * Bootstrap package services: publish assets and load routes.
     */
    public function boot(): void
    {
        // Publish only when running artisan commands.
        if ($this->app->runningInConsole()) {
            // Publish config (if the package ships one).
            $configPath = __DIR__ . '/../config/truth-renderer.php';
            if (file_exists($configPath)) {
                $this->publishes([
                    $configPath => config_path('truth-renderer.php'),
                ], 'truth-renderer-config');
            }

            // Publish a default templates folder (optional).
            $stubTemplates = __DIR__ . '/../stubs/templates';
            if (is_dir($stubTemplates)) {
                $this->publishes([
                    $stubTemplates => base_path('resources/truth-templates'),
                ], 'truth-renderer-templates');
            }

            // Publish Vue/Inertia component stubs to a conventional components folder.
            $stubVue = __DIR__ . '/../stubs/inertia';
            if (is_dir($stubVue)) {
                $this->publishes([
                    $stubVue => resource_path('js/Pages/TruthRenderer/'),
                ], 'truth-renderer-vue');
            }
        }

        // Load package routes if present (kept outside runningInConsole so routes are active at runtime).
        foreach (['api.php', 'web.php'] as $file) {
            $path = __DIR__ . "/../routes/{$file}";
            if (file_exists($path)) {
                $this->loadRoutesFrom($path);
            }
        }
    }
}
