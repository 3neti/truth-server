<?php

namespace TruthElectionDb\Console\Commands;

use TruthElectionDb\Traits\HandlesStdinInput;
use TruthElectionDb\Actions\AttestReturn;
use TruthElection\Data\SignPayloadData;
use Illuminate\Console\Command;

class AttestReturnCommand extends Command
{
    use HandlesStdinInput;

    /**
     * The name and signature of the console command.
     *
     * Run with:
     *   php artisan election:attest ER-CODE 'json-encoded-or-base64 payload'
     */
    protected $signature = 'election:attest-return
        {payload? : The QR string or base64-encoded JSON payload from the inspector}';

    /**
     * The console command description.
     */
    protected $description = 'Attest an election return using inspector signature payload.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $rawPayload = $this->argument('payload') ?? $this->readLineFromStdin();
        if (empty($rawPayload)) {
            $this->warn('⚠️  No payload provided via argument or STDIN.');
            $this->line('Usage:');
            $this->line('  php artisan election:attest [payload]');
            $this->line('  echo "base64-payload" | php artisan election:attest');
            return self::FAILURE;
        }

        try {
            $payload = SignPayloadData::fromQrString($rawPayload);

            $result = AttestReturn::make()->run($payload);
            $code = $result['er']->code;

            $this->info('✅ Signature saved successfully:');
            $this->line("🧑 Inspector: {$result['name']} ({$result['role']})");
            $this->line("🗓 Signed At: {$result['signed_at']}");
            $this->line("🗳 Election Return: $code");

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Failed to attest election return: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
