<?php

namespace TruthElection\Support;

use TruthElection\Data\{MappingData, MarkData, VoteData};
use TruthElection\Data\{BallotData, CandidateData};
use Spatie\LaravelData\DataCollection;

class MappingContext
{
    protected MappingData $mappings;

    public function __construct(
        protected ElectionStoreInterface $store
    ) {
        $this->mappings = $this->store->getMappings();
    }

    /**
     * Get all marks defined in the mapping file.
     */
    public function getMarks(): DataCollection
    {
        return $this->mappings->marks;
    }

    /**
     * Resolve a candidate from a mark key.
     */
    public function resolveCandidate(string $key): CandidateData
    {
        $mark = $this->getMark($key);

        $candidate = $this->store->getCandidate($mark->value);

        if (!$candidate) {
            throw new \RuntimeException("Candidate with code '{$mark->value}' not found.");
        }

        return $candidate;
    }

    /**
     * Optional: Retrieve the raw MarkData for a given key.
     */
    public function getMark(string $key): MarkData
    {
        /** @var MarkData|null $mark */
        $mark = $this->getMarks()->toCollection()->firstWhere('key', $key);

        if (!$mark) {
            throw new \RuntimeException("Mark '{$key}' not found.");
        }

        return $mark;
    }

    /**
     * Optional: Get the full MappingData
     */
    public function getMapping(): MappingData
    {
        return $this->mappings;
    }

    public function code(): string
    {
        return $this->mappings->code;
    }

    public function location(): string
    {
        return $this->mappings->location_name;
    }

    public function district(): string
    {
        return $this->mappings->district;
    }

    public function resolveBallot(string $ballotCode): BallotData
    {
        $markKeys = $this->store->getBallotMarkKeys($ballotCode);

        /** @var array<string, CandidateData[]> $votesMap */
        $votesMap = [];

        foreach ($markKeys as $key) {
            try {
                $candidate = $this->resolveCandidate($key);
                $positionCode = $candidate->position->code;

                $votesMap[$positionCode][] = $candidate;
            } catch (\Throwable $e) {
                logger()->warning("[resolveBallot] Skipping mark '{$key}': " . $e->getMessage());
            }
        }

        $voteData = [];

        foreach ($votesMap as $positionCode => $candidates) {
            // All candidates share the same position object
            $position = $candidates[0]->position;

            if (count($candidates) > $position->count) {
                logger()->info("[resolveBallot] Voiding vote for '{$positionCode}' due to overvote.", [
                    'expected_max' => $position->count,
                    'actual' => count($candidates),
                    'ballot' => $ballotCode,
                ]);
                continue; // 🚫 Skip this position
            }

            $voteData[] = new VoteData(
                candidates: new DataCollection(CandidateData::class, $candidates)
            );
        }

        return new BallotData(
            code: $ballotCode,
            votes: new DataCollection(VoteData::class, $voteData)
        );
    }
}
