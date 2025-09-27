<?php

namespace TruthElection\Data;

use Spatie\LaravelData\{Data, DataCollection};

class VoteData extends Data
{
    public ?PositionData $position = null;

    /**
     * @param DataCollection<CandidateData> $candidates
     */
    public function __construct(
        public DataCollection $candidates
    ) {
        $firstPosition = $candidates->first()->position;
        foreach ($candidates->toCollection() as $candidate) {
            if ($candidate->position->code !== $firstPosition->code) {
                throw new \InvalidArgumentException('All candidates in a vote must be for the same position.');
            }
        }
        $this->position = $firstPosition;
    }
}
