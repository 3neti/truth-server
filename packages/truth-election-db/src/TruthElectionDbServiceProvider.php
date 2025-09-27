<?php

namespace TruthElectionDb;

use TruthElectionDb\Console\Commands\RecordStatisticsCommand;
use TruthElectionDb\Console\Commands\FinalizeBallotCommand;
use TruthElectionDb\Console\Commands\SetupPrecinctCommand;
use TruthElectionDb\Console\Commands\AttestReturnCommand;
use TruthElectionDb\Console\Commands\WrapUpVotingCommand;
use TruthElectionDb\Console\Commands\CastBallotCommand;
use TruthElectionDb\Console\Commands\TallyVotesCommand;
use TruthElectionDb\Console\Commands\ReadVoteCommand;
use TruthElectionDb\Support\DatabaseElectionStore;
use TruthElection\Support\ElectionStoreInterface;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Route;

class TruthElectionDbServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ElectionStoreInterface::class, DatabaseElectionStore::class);

        $this->mergeConfigFrom(__DIR__ . '/../config/truth-election-db.php', 'truth-election-db');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/truth-election-db.php' => config_path('truth-election-db.php'),
        ], 'truth-election-db-config');

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        $this->publishes([
            __DIR__ . '/../routes/web.php' => base_path('routes/web.php'),
            __DIR__ . '/../routes/api.php' => base_path('routes/api.php'),
        ], 'truth-election-db-routes');

        $this->loadRoutes();

        if ($this->app->runningInConsole()) {
            $this->commands([
                SetupPrecinctCommand::class,
                ReadVoteCommand::class,
                FinalizeBallotCommand::class,
                CastBallotCommand::class,
                TallyVotesCommand::class,
                AttestReturnCommand::class,
                RecordStatisticsCommand::class,
                WrapUpVotingCommand::class,
            ]);
        }
    }

    protected function loadRoutes(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        Route::prefix('api/election')
            ->middleware('api')
            ->group(__DIR__.'/../routes/api.php');
    }
}
