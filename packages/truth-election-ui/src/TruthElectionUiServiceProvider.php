<?php

namespace TruthElectionUi;

use TruthElection\Support\ElectionStoreInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class TruthElectionUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->registerRoutes();
        $this->publishesConfig();
        $this->publishesViews();
        $this->sharePrecinctGlobally();
    }

    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/truth-election-ui.php', 'truth-election-ui');
    }

    protected function registerRoutes(): void
    {
        Route::prefix('api')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');
        Route::middleware('web')
            ->group(__DIR__.'/../routes/web.php');
    }

    protected function publishesConfig(): void
    {
        $this->publishes([
            __DIR__ . '/../config/truth-election-ui.php' => config_path('truth-election-ui.php'),
        ], 'truth-election-ui-config');
    }

    protected function publishesViews(): void
    {
        $this->publishes([
            __DIR__ . '/../stubs/resources/js/TruthElectionUi'
            => resource_path('js/TruthElectionUi'),
        ], 'truth-election-ui-stubs');
    }

    protected function sharePrecinctGlobally(): void
    {
        Inertia::share('precinct', function () {
            return app(ElectionStoreInterface::class)->getPrecinct();
        });
    }
}
