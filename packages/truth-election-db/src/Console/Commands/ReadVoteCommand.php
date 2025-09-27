<?php

namespace TruthElectionDb\Console\Commands;

use TruthElection\Actions\ReadVote;
use Illuminate\Console\Command;

class ReadVoteCommand extends Command
{
    protected $signature = 'election:read-vote
        {ballot_code : The ballot code (e.g., CURRIMAO-001-001)}
        {mark_key : The mark key to read (e.g., A1)}';

    protected $description = 'Read a single vote by ballot code and mark key using the ReadVote action.';

    public function handle(): int
    {
        $ballotCode = $this->argument('ballot_code');
        $markKey = $this->argument('mark_key');

        try {
            $ballot = ReadVote::make()->run($ballotCode, $markKey);

            $this->info('✅ Vote successfully read:');
            $this->line("Ballot Code: {$ballot->code}");
            $this->line("Total Positions with Valid Votes: " . $ballot->votes->count());

            foreach ($ballot->votes as $vote) {
                $position = $vote->candidates->toCollection()->first()?->position?->name ?? 'UNKNOWN';
                $candidates = $vote->candidates->toCollection()->map(fn ($c) => $c->name)->implode(', ');
                $this->line("🔹 {$position}: {$candidates}");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error reading vote: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
