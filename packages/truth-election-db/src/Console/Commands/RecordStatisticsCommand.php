<?php

namespace TruthElectionDb\Console\Commands;

use Illuminate\Validation\ValidationException;
use TruthElectionDb\Actions\RecordStatistics;
use TruthElectionDb\Traits\HandlesStdinInput;
use Illuminate\Support\Facades\Validator;
use Illuminate\Console\Command;

class RecordStatisticsCommand extends Command
{
    use HandlesStdinInput;

    protected $signature = 'election:record-statistics
                            {payload? : JSON payload of statistics to update}';

    protected $description = 'Record statistics (watchers, voters, ballots, etc.) for a given precinct';

    public function handle(): int
    {
        $payloadRaw = $this->argument('payload') ?? $this->readLineFromStdin();

        if (! $payloadRaw) {
            $this->error('❌ Please provide a JSON payload as an argument or via STDIN.');
            $this->line('Usage:');
            $this->line('  php artisan election:record-statistics \'{"precinct":"X",...}\'');
            $this->line('  echo \'{"precinct":"X",...}\' | php artisan election:record-statistics');
            return self::FAILURE;
        }

        $payload = json_decode($payloadRaw, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('❌ Invalid JSON payload: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $rules = app(RecordStatistics::class)->rules();

        try {
            $validated = Validator::make($payload, $rules)->validate();
        } catch (ValidationException $e) {
            $this->error('❌ Validation failed:');
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $msg) {
                    $this->line(" - $field: $msg");
                }
            }
            return self::FAILURE;
        }

        try {
            $updated = RecordStatistics::run($validated);

            $this->info("✅ Statistics successfully recorded for precinct: {$updated->code}");
            foreach ($validated as $key => $val) {
                $this->line(" - $key: " . ($val ?? 'null'));
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Failed to record statistics: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
