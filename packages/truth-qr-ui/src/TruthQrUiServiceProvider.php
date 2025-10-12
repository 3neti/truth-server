<?php

namespace TruthQrUi;

use Illuminate\Support\ServiceProvider;

final class TruthQrUiServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Config publish
        $this->publishes([
            __DIR__ . '/../config/truth-qr-ui.php' => config_path('truth-qr-ui.php'),
        ], 'truth-qr-ui-config');

        // Inertia/Vue stubs publish
        $this->publishes([
            __DIR__ . '/../stubs/inertia/Pages/TruthQrUi/Playground.vue'
            => resource_path('js/Pages/TruthQrUi/Playground.vue'),
            __DIR__ . '/../stubs/inertia/Pages/TruthQrUi/TruthSimple.vue'
            => resource_path('js/Pages/TruthQrUi/TruthSimple.vue'),
            __DIR__ . '/../stubs/inertia/components/TruthQrForm.vue'
            => resource_path('js/Pages/TruthQrUi/components/TruthQrForm.vue'),
            __DIR__ . '/../stubs/inertia/components/ScannerPanel.vue'
            => resource_path('js/Pages/TruthQrUi/components/ScannerPanel.vue'),
            __DIR__ . '/../stubs/inertia/composables/useTruthQr.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useTruthQr.ts'),
            __DIR__ . '/../stubs/inertia/composables/download.ts'
            => resource_path('js/Pages/TruthQrUi/composables/download.ts'),
            __DIR__ . '/../stubs/inertia/composables/MultiPartTools.ts'
            => resource_path('js/Pages/TruthQrUi/composables/MultiPartTools.ts'),

            __DIR__ . '/../stubs/inertia/composables/usePartsList.ts'
            => resource_path('js/Pages/TruthQrUi/composables/usePartsList.ts'),
            __DIR__ . '/../stubs/inertia/composables/useDownloads.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useDownloads.ts'),
            __DIR__ . '/../stubs/inertia/composables/useEncodeDecode.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useEncodeDecode.ts'),
            __DIR__ . '/../stubs/inertia/composables/usePayloadJson.ts'
            => resource_path('js/Pages/TruthQrUi/composables/usePayloadJson.ts'),
            __DIR__ . '/../stubs/inertia/composables/useQrGallery.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useQrGallery.ts'),
            __DIR__ . '/../stubs/inertia/composables/useScannerSession.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useScannerSession.ts'),
            __DIR__ . '/../stubs/inertia/composables/useWriterSpec.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useWriterSpec.ts'),
            __DIR__ . '/../stubs/inertia/composables/useZxingVideo.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useZxingVideo.ts'),
            __DIR__ . '/../stubs/inertia/composables/useRenderer.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useRenderer.ts'),
            __DIR__ . '/../stubs/inertia/composables/useTemplateRegistry.ts'
            => resource_path('js/Pages/TruthQrUi/composables/useTemplateRegistry.ts'),
        ], 'truth-qr-ui-stubs');

        // Optional: load routes if you decide to ship default routes
         $this->loadRoutesFrom(__DIR__.'/../routes/web.php');

        // Optional: only register command in console
        if ($this->app->runningInConsole()) {
            $this->commands([
                \TruthQrUi\Console\InstallCommand::class,
            ]);
        }
    }
}
