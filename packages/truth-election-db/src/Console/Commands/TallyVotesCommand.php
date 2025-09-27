<?php

namespace TruthElectionDb\Console\Commands;

use TruthElectionDb\Traits\HandlesStdinInput;
use TruthElectionDb\Actions\TallyVotes;
use Illuminate\Console\Command;

class TallyVotesCommand extends Command
{
    use HandlesStdinInput;

    /**
     * The name and signature of the console command.
     *
     * Run with:
     *   php artisan election:tally P-001 [ELECTION_RETURN_CODE]
     */
    protected $signature = 'election:tally-votes
        {election_return_code? : Optional code to assign to the election return}';

    /**
     * The console command description.
     */
    protected $description = 'Tally votes for a given precinct using the TallyVotes action.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $electionReturnCode = $this->argument('election_return_code') ?? $this->readLineFromStdin();

        try {
            $result = TallyVotes::make()->run($electionReturnCode);

            $this->info('✅ Tally complete:');

            $precinctCode = $result->precinct->code;
            $this->line("Precinct: $precinctCode");

            $lastBallot = $result->toArray()['last_ballot'] ?? [];

            if (!empty($lastBallot['code'])) {
                $this->line("🗳 Last Ballot Cast: {$lastBallot['code']}");
                $this->newLine();

                foreach ($lastBallot['votes'] ?? [] as $vote) {
                    $position = $vote['position']['name'] ?? 'Unknown Position';
                    $this->line("Position: $position");

                    foreach ($vote['candidates'] ?? [] as $candidate) {
                        $name = $candidate['name'] ?? 'Unknown';
                        $votes = $candidate['votes'] ?? 1;
                        $this->line("  - $name ({$votes} vote" . ($votes === 1 ? '' : 's') . ")");
                    }

                    $this->newLine();
                }
            } else {
                $this->warn("⚠️  No last ballot data available.");
            }

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Error generating tally: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
