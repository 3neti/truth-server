<?php

namespace TruthElectionUi\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use TruthElectionDb\TruthElectionDbServiceProvider;
use TruthElectionUi\TruthElectionUiServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;
use TruthElection\TruthElectionServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadConfig();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'TruthElectionUi\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            TruthElectionServiceProvider::class,
            TruthElectionUiServiceProvider::class
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('data.validation_strategy', 'always');
        config()->set('data.max_transformation_depth', 6);
        config()->set('data.throw_when_max_transformation_depth_reached', 6);
        config()->set('data.normalizers', [
            \Spatie\LaravelData\Normalizers\ModelNormalizer::class,
            // Spatie\LaravelData\Normalizers\FormRequestNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayableNormalizer::class,
            \Spatie\LaravelData\Normalizers\ObjectNormalizer::class,
            \Spatie\LaravelData\Normalizers\ArrayNormalizer::class,
            \Spatie\LaravelData\Normalizers\JsonNormalizer::class,
        ]);
        config()->set('data.date_format', "Y-m-d\TH:i:sP");
    }

    protected function loadConfig()
    {
        $this->app['config']->set(
            'truth-election',
            require __DIR__ . '/../config/truth-election.php'
        );
        $this->app['config']->set(
            'truth-election-db',
            require __DIR__ . '/../config/truth-election-db.php'
        );
        $this->app['config']->set(
            'truth-election-ui',
            require __DIR__ . '/../config/truth-election-ui.php'
        );
    }
}
