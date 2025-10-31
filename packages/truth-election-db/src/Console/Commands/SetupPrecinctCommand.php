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
     *  php artisan election:setup-precinct --config-path=/path/to/config/directory [--migrate]
     */
    protected $signature = 'election:setup-precinct
        {--election= : Path to the election.json file}
        {--precinct= : Path to the precinct.yaml file}
        {--config-path= : Path to directory containing all config files (election.json, precinct.yaml, mapping.yaml)}
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

        // Determine paths based on --config-path or individual options
        $configPath = $this->option('config-path');
        
        if ($configPath) {
            // New way: load from config directory
            // Convert to absolute path if relative
            $absoluteConfigPath = $this->isAbsolutePath($configPath) 
                ? $configPath 
                : base_path($configPath);
            
            $electionPath = rtrim($absoluteConfigPath, '/') . '/election.json';
            $precinctPath = rtrim($absoluteConfigPath, '/') . '/precinct.yaml';
            $mappingPath = rtrim($absoluteConfigPath, '/') . '/mapping.yaml';
            
            $this->info("📂 Loading configs from: {$configPath}");
        } else {
            // Old way: use individual file paths or defaults
            $electionPath = $this->option('election');
            $precinctPath = $this->option('precinct');
            $mappingPath = null; // Will use default from ConfigFileReader
        }

        try {
            $result = InitializeSystem::run(
                electionPath: $electionPath,
                precinctPath: $precinctPath,
                mappingPath: $mappingPath,
            );
        } catch (QueryException $e) {
            $this->error("❌ Database error: {$e->getMessage()}");
            $this->line('');
            $this->warn("💡 Have you run `php artisan migrate`?");
            $this->line("👉 You can also run:");
            $this->line("   php artisan election:setup-precinct --election=... --precinct=... --migrate");
            $this->line("   php artisan election:setup-precinct --config-path=... --migrate");
            return self::FAILURE;
        } catch (\RuntimeException $e) {
            $this->error($e->getMessage());
            $this->line('');
            $this->line('💡 You may also provide file paths explicitly:');
            $this->line('   php artisan election:setup-precinct --election=... --precinct=...');
            $this->line('   php artisan election:setup-precinct --config-path=path/to/config');
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

    /**
     * Check if a path is absolute
     */
    protected function isAbsolutePath(string $path): bool
    {
        // Unix/Linux/Mac: starts with /
        // Windows: starts with drive letter like C:\
        return $path[0] === '/' || (strlen($path) > 1 && $path[1] === ':');
    }
}
