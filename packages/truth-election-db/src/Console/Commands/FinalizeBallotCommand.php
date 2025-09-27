<?php

namespace TruthElectionDb\Console\Commands;

use TruthElection\Actions\FinalizeBallot;
use Illuminate\Support\Facades\Log;
use Illuminate\Console\Command;

class FinalizeBallotCommand extends Command
{
    protected $signature = 'election:finalize-ballot
                            {ballot_code : The unique ballot code to finalize}';

    protected $description = 'Finalize a single ballot and submit its votes.';

    public function handle(): int
    {
        $ballotCode = $this->argument('ballot_code');

        try {
            $ballot = FinalizeBallot::run($ballotCode);

            $this->info('✅ Ballot successfully finalized.');
            $this->line("🆔 Ballot Code: {$ballot->code}");
            $this->line("🗳 Votes: {$ballot->votes->count()}");

            foreach ($ballot->votes as $vote) {
                $position = $vote->candidates->toCollection()->first()?->position?->name ?? 'UNKNOWN';
                $candidates = $vote->candidates->toCollection()->map(fn ($c) => $c->name)->implode(', ');
                $this->line("🔹 {$position}: {$candidates}");
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error("❌ Error finalizing ballot: {$e->getMessage()}");
            Log::error('[FinalizeBallotCommand] ' . $e->getMessage(), [
                'ballot_code' => $ballotCode,
                'trace' => $e->getTraceAsString(),
            ]);
            return self::FAILURE;
        }
    }
}
