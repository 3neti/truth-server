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
            __DIR__ . '/../stubs/resources/js/pages/Tally.vue'
            => resource_path('js/pages/Tally.vue'),

            __DIR__ . '/../stubs/resources/js/types/election.ts'
            => resource_path('js/types/election.ts'),

            __DIR__ . '/../stubs/resources/js/composables/useElectionReturn.ts'
            => resource_path('js/composables/useElectionReturn.ts'),
            __DIR__ . '/../stubs/resources/js/composables/useBasicUtils.ts'
            => resource_path('js/composables/useBasicUtils.ts'),
            __DIR__ . '/../stubs/resources/js/composables/usePrecinctPeople.ts'
            => resource_path('js/composables/usePrecinctPeople.ts'),

            __DIR__ . '/../stubs/resources/js/components/ErTallyView.vue'
            => resource_path('js/components/ErTallyView.vue'),
            __DIR__ . '/../stubs/resources/js/components/ErOfficialsSignatures.vue'
            => resource_path('js/components/ErOfficialsSignatures.vue'),
            __DIR__ . '/../stubs/resources/js/components/ErPrecinctCard.vue'
            => resource_path('js/components/ErPrecinctCard.vue'),
            __DIR__ . '/../stubs/resources/js/components/ErTalliesTable.vue'
            => resource_path('js/components/ErTalliesTable.vue'),
            __DIR__ . '/../stubs/resources/js/components/TallyMarks.vue'
            => resource_path('js/components/TallyMarks.vue'),
        ], 'truth-election-ui-stubs');
    }

    protected function sharePrecinctGlobally(): void
    {
        Inertia::share('precinct', function () {
            return app(ElectionStoreInterface::class)->getPrecinct();
        });
    }
}
