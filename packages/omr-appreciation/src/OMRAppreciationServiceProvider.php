<?php

namespace LBHurtado\OMRAppreciation;

use Illuminate\Support\ServiceProvider;
use LBHurtado\OMRAppreciation\Commands\AppreciateCommand;
use LBHurtado\OMRAppreciation\Commands\AppreciatePythonCommand;
use LBHurtado\OMRAppreciation\Commands\GenerateCalibrationCommand;
use LBHurtado\OMRAppreciation\Commands\VerifyCalibrationCommand;
use LBHurtado\OMRAppreciation\Services\AppreciationService;
use LBHurtado\OMRAppreciation\Services\FiducialDetector;
use LBHurtado\OMRAppreciation\Services\ImageAligner;
use LBHurtado\OMRAppreciation\Services\MarkDetector;
use LBHurtado\OMRAppreciation\Services\OMRAppreciator;

class OMRAppreciationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register services
        $this->app->singleton(FiducialDetector::class);
        $this->app->singleton(ImageAligner::class);
        $this->app->singleton(MarkDetector::class);
        $this->app->singleton(AppreciationService::class);
        $this->app->singleton(OMRAppreciator::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([
                AppreciateCommand::class,
                AppreciatePythonCommand::class,
                GenerateCalibrationCommand::class,
                VerifyCalibrationCommand::class,
            ]);
        }
    }
}
