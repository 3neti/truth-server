<?php

namespace TruthQrUi\Console;

use Illuminate\Console\Command;

final class InstallCommand extends Command
{
    protected $signature = 'truth-qr-ui:install {--force : Overwrite existing files}';
    protected $description = 'Publish TRUTH QR UI stubs and config, with guidance';

    public function handle(): int
    {
        $this->info('Publishing TRUTH QR UI config...');
        $this->call('vendor:publish', [
            '--tag'   => 'truth-qr-ui-config',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->info('Publishing TRUTH QR UI Inertia/Vue stubs...');
        $this->call('vendor:publish', [
            '--tag'   => 'truth-qr-ui-stubs',
            '--force' => (bool) $this->option('force'),
        ]);

        $this->line('');
        $this->info('Next steps:');
        $this->line('  1) Ensure Inertia/Vue are installed in the host app:');
        $this->line('     composer require inertiajs/inertia-laravel');
        $this->line('     php artisan inertia:middleware');
        $this->line('     npm i -D vite && npm i vue @inertiajs/vue3');
        $this->line('  2) Add routes to encode/decode + playground.');
        $this->line('  3) Run Vite (npm run dev) and visit /playground.');
        $this->line('');

        return self::SUCCESS;
    }
}
