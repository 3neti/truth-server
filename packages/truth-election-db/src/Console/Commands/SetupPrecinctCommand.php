<?php

namespace TruthElectionDb\Console\Commands;

use TruthElection\Actions\InitializeSystem;
use Illuminate\Database\QueryException;
use Illuminate\Console\Command;

class SetupPrecinctCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Usage:
     *  php artisan election:setup-precinct --election=/path/to/election.json --precinct=/path/to/precinct.yaml [--migrate]
     */
    protected $signature = 'election:setup-precinct
        {--election= : Path to the election.json file}
        {--precinct= : Path to the precinct.yaml file}
        {--fresh     : Wipe database before setting up}
        {--migrate   : Run migrations before setting up}';

    /**
     * The console command description.
     */
    protected $description = 'Set up election data using files and persist to database via truth-election-db';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->info('🔄 Fresh start: wiping database...');
            $this->call('db:wipe', ['--force' => true]);

            // Automatically migrate after wipe
            $this->info('📦 Running migrations after wipe...');
            $this->call('migrate', ['--force' => true]);
        } elseif ($this->option('migrate')) {
            // If only --migrate is passed (no wipe)
            $this->info('📦 Running migrations...');
            $this->call('migrate', ['--force' => true]);
        }

        try {
            $result = InitializeSystem::run(
                electionPath: $this->option('election'),
                precinctPath: $this->option('precinct'),
            );
        } catch (QueryException $e) {
            $this->error("❌ Database error: {$e->getMessage()}");
            $this->line('');
            $this->warn("💡 Have you run `php artisan migrate`?");
            $this->line("👉 You can also run:");
            $this->line("   php artisan election:setup --election=... --precinct=... --migrate");
            return self::FAILURE;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('');
            $this->line('💡 You may also provide file paths explicitly:');
            $this->line('   php artisan election:setup --election=... --precinct=...');
            return self::FAILURE;
        }

        $this->info('✅ Election setup complete.');

        $this->table(
            ['Precinct Code', 'Positions Created', 'Candidates Created'],
            [[
                $result['summary']['precinct_code'] ?? '—',
                $result['summary']['positions']['created'] ?? 0,
                $result['summary']['candidates']['created'] ?? 0,
            ]]
        );

        return self::SUCCESS;
    }
}
