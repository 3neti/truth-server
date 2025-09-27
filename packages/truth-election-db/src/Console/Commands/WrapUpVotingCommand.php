<?php

namespace TruthElectionDb\Console\Commands;

use TruthElectionDb\Actions\WrapUpVoting;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class WrapUpVotingCommand extends Command
{
    protected $signature = 'election:wrapup-voting
                            {--disk=local : The storage disk to use}
                            {--payload=minimal : The payload format (e.g. minimal, full)}
                            {--max_chars=1200 : Max characters per payload chunk}
                            {--dir=final : Directory to store final ER files}
                            {--force : Force even if already closed}';

    protected $description = 'Finalize an Election Return for a given precinct and mark voting as closed.';

    public function handle(): int
    {
        try {
            $result = WrapUpVoting::run(
                disk: $this->option('disk'),
                payload: $this->option('payload'),
                maxChars: (int) $this->option('max_chars'),
                dir: $this->option('dir'),
                force: (bool) $this->option('force'),
            );

            $this->info('✅ Election Return successfully finalized.');
            $this->line("🗳 Precinct: {$result->precinct->code}");
            $this->line("📦 Saved to: ER-{$result->code}/{$this->option('dir')} ({$this->option('disk')})");

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('[WrapUpVotingCommand] ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return self::FAILURE;
        }
    }
}
