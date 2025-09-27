<?php

namespace TruthElection;

use TruthElection\Policies\Signatures\ChairPlusMemberPolicy;
use TruthElection\Policies\Signatures\SignaturePolicy;
use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Support\PrecinctContext;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

final class TruthElectionServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/truth-election.php', 'truth-election');

        $this->app->bind(SignaturePolicy::class, ChairPlusMemberPolicy::class);

        $this->app->singleton(ElectionStoreInterface::class, function () {
            /** @var class-string<\TruthElection\Support\ElectionStoreInterface> $store */
            $store = config('truth-election.store', InMemoryElectionStore::class);

            return $store::instance();
        });
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/truth-election.php' => config_path('truth-election.php'),
        ], 'truth-election-config');

        $this->publishes([
            __DIR__ . '/../config/election.json' => config_path('election.json'),
            __DIR__ . '/../config/precinct.yaml' => config_path('precinct.yaml'),
        ], 'truth-election-config');

        $this->app->bind(PrecinctContext::class, function ($app) {
            $store = $app->make(ElectionStoreInterface::class);
            $precinctCode = request()->input('precinct_code') ?? null;

            return new PrecinctContext($store, $precinctCode);
        });
    }

    protected function registerRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix('api/truth-election')
            ->middleware(config('truth-election.middleware', ['api']))
            ->group(__DIR__.'/../routes/api.php');
    }
}
