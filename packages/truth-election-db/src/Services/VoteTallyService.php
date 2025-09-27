<?php

namespace TruthElectionDb\Services;

use Spatie\LaravelData\DataCollection;
use TruthElection\Data\BallotData;
use TruthElection\Data\VoteCountData;
use TruthElectionDb\Models\Precinct;
use Illuminate\Support\Collection;

class VoteTallyService
{
    /**
     * Generate sorted tallies for a given precinct.
     */
    public function fromPrecinct(Precinct $precinct): DataCollection
    {
        $tally = [];

        foreach ($precinct->ballots as $ballotArray) {
            $ballot = BallotData::from($ballotArray);

            foreach ($ballot->votes as $vote) {
                $positionCode = $vote->position->code;

                foreach ($vote->candidates as $candidate) {
                    $key = "{$positionCode}_{$candidate->code}";

                    if (!isset($tally[$key])) {
                        $tally[$key] = [
                            'position_code'   => $positionCode,
                            'candidate_code'  => $candidate->code,
                            'candidate_name'  => $candidate->name,
                            'count'           => 0,
                        ];
                    }

                    $tally[$key]['count']++;
                }
            }
        }

        // Group by position and sort descending
        $grouped = collect($tally)
            ->values()
            ->groupBy('position_code')
            ->map(fn (Collection $group) => $group->sortByDesc('count')->values())
            ->flatten(1);

        return new DataCollection(
            VoteCountData::class,
            $grouped->map(fn ($row) => new VoteCountData(...$row))
        );
    }
}
