<?php

namespace TruthElection\Data;

use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Data;

/**
 * @property-read string $code
 * @property-read DataCollection<VoteData> $votes
 */
class BallotData extends Data
{
    public string $precinct_code;

    public function getPrecinctCode(): string
    {
        return $this->precinct_code;
    }

    public function setPrecinctCode(string $precinct_code): static
    {
        $this->precinct_code = $precinct_code;

        return $this;
    }

    /**
     * @param DataCollection<VoteData> $votes
     */
    public function __construct(
        public string $code,
        public DataCollection $votes,
    ) {}

    public function mergeWith(BallotData $other): BallotData
    {
        $votesByPosition = $this->votes->toCollection()
            ->merge($other->votes->all())
            ->groupBy(fn (VoteData $vote) => $vote->position->code);

        $mergedVotes = $votesByPosition->map(function ($votes, $positionCode) {
            /** @var DataCollection<VoteData> $votes */
            $position = $votes->first()->position;

            // 🧠 If count is 1, overwrite with the latest vote
            if ($position->count === 1) {
                return new VoteData(
                    candidates: $votes->last()->candidates,
                );
            }

            // 🤝 If count > 1, merge and deduplicate candidates
            $allCandidates = $votes
                ->flatMap(fn (VoteData $vote) => $vote->candidates->all())
                ->unique(fn (CandidateData $c) => $c->code)
                ->take($position->count); // 🧠 enforce max allowed

            return new VoteData(
                candidates: new DataCollection(CandidateData::class, $allCandidates->values()->all()),
            );
        });

        return new BallotData(
            code: $this->code,
            votes: new DataCollection(VoteData::class, $mergedVotes->values()->all())
        );
    }
//    public function mergeWith(BallotData $other): BallotData
//    {
//        $votesByPosition = collect($this->votes->all())
//            ->merge($other->votes->all())
//            ->groupBy(fn(VoteData $vote) => $vote->position->code);
//
//        $mergedVotes = $votesByPosition->map(function ($votes, $positionCode) {
//            /** @var DataCollection<VoteData> $votes */
//
//            // Get the position info (same across grouped VoteData)
//            /** @var PositionData $position */
//            $position = $votes->first()->position;
//
//            // Flatten candidates from incoming ballot first (later merged)
//            $allCandidates = collect($votes)
//                ->reverse() // Prioritize later votes (from $other)
//                ->flatMap(fn (VoteData $vote) => $vote->candidates->all())
//                ->unique(fn (CandidateData $c) => $c->code)
//                ->take($position->count); // 🧠 enforce max count
//
//            return new VoteData(new DataCollection(CandidateData::class, $allCandidates->values()->all()));
//        });
//
//        return new BallotData(
//            code: $this->code,
//            votes: new DataCollection(VoteData::class, $mergedVotes->values()->all())
//        );
//    }
}
