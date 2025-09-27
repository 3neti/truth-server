<?php

namespace TruthElection\Support;

use TruthElection\Data\ElectoralInspectorData;
use TruthElection\Data\ElectionReturnData;
use Spatie\LaravelData\DataCollection;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Data\PrecinctData;
use TruthElection\Data\MappingData;
use TruthElection\Data\BallotData;
use TruthElection\Data\VoteData;
use TruthElection\Data\MarkData;

class InMemoryElectionStore implements ElectionStoreInterface
{
    /** @var array<string, PositionData> */ // key: position_code => PositionData
    public array $positions = [];

    /** @var array<string, CandidateData> */ // key: candidate_code => CandidateData
    public array $candidates = [];

    /** @deprecated Use $precinct->ballots instead */
    public array $ballots = [];

    /** @var array<string, PrecinctData> */
    public array $precincts = [];

    /** @var array<string, ElectionReturnData> */
    public array $electionReturns = [];

    protected ?MappingData $mappings = null;

    public array $ballotMarks = [];

    private static ?self $instance = null;

    private function __construct()
    {
        // optionally preload demo data here
    }

    /**
     * Access the singleton instance.
     */
    public static function instance(): self
    {
        return self::$instance ??= new self();
    }

    public function getBallots(string $precinctCode): DataCollection
    {
        $precinct = $this->getPrecinct($precinctCode);

        if (!$precinct || !$precinct->ballots) {
            return new DataCollection(BallotData::class, []);
        }

        return $precinct->ballots;
    }

    /**
     * Add or replace a ballot.
     */
    public function putBallot(BallotData $ballot, string $precinctCode): void
    {
        $precinct = $this->getPrecinct($precinctCode);

        if (!$precinct) {
            throw new \RuntimeException("Precinct [$precinctCode] not found.");
        }

        $existingBallots = $precinct->ballots ?? new DataCollection(BallotData::class, []);

        // 🛡️ Check if ballot already exists (by code)
        $alreadyExists = $existingBallots
            ->toCollection()
            ->contains(fn(BallotData $b) => $b->code === $ballot->code);

        if ($alreadyExists) {
            return; // Do nothing — avoid duplication
        }

        $updatedBallots = new DataCollection(
            BallotData::class,
            [...$existingBallots->toCollection(), $ballot],
        );

        $this->precincts[$precinctCode] = $precinct->copyWith([
            'ballots' => $updatedBallots,
        ]);
    }

    public function getPrecinct(?string $code = null): ?PrecinctData
    {
        if ($code === null) {
            return collect($this->precincts)->values()->first();
        }

        return $this->precincts[$code] ?? null;
    }

    /**
     * Add or replace a precinct.
     */
    public function putPrecinct(PrecinctData $precinct): void
    {
        $this->precincts[$precinct->code] = $precinct;
    }

    /**
     * Add or replace an election return.
     */
    public function putElectionReturn(ElectionReturnData $er): void
    {
        $this->electionReturns[$er->precinct->code] = $er;
    }

    public function getBallotsForPrecinct(string $precinctCode): array
    {
        $precinct = $this->getPrecinct($precinctCode);

        if (!$precinct || !$precinct->ballots) {
            return [];
        }

        return $precinct->ballots->toArray();
    }

    /**
     * Reset all data (useful for test teardown).
     */
    public function reset(): void
    {
        $this->ballots = [];
        $this->precincts = [];
        $this->electionReturns = [];
    }

    /**
     * Retrieve election return by its unique code.
     */
    public function getElectionReturn(?string $code = null): ?ElectionReturnData
    {
        if ($code === null) {
            return collect($this->electionReturns)->values()->first();
        }

        foreach ($this->electionReturns as $er) {
            if ($er->code === $code) {
                return $er;
            }
        }

        return null;
    }

    function findInspector(ElectionReturnData $er, string $id): ?ElectoralInspectorData
    {
        $raw = $er->precinct->electoral_inspectors->toCollection()->firstWhere('id', $id);

        return ElectoralInspectorData::from($raw);
    }

    function findPrecinctInspector(ElectionReturnData $er, string $id): ?ElectoralInspectorData
    {
        $raw = $er->precinct->electoral_inspectors->toCollection()->firstWhere('id', $id);

        return ElectoralInspectorData::from($raw);
    }

    public function getInspectorsForPrecinct(string $precinctCode): DataCollection
    {
        $precinct = $this->getPrecinct($precinctCode);

        return new DataCollection(
            ElectoralInspectorData::class,
            $precinct?->electoral_inspectors ?? []
        );
    }

    function findSignatory(ElectionReturnData $er, string $id): ElectoralInspectorData
    {
        $raw = collect($er->signatures)->firstWhere('id', $id);

        return ElectoralInspectorData::from($raw);
    }

    public function replaceElectionReturn(ElectionReturnData $er): void
    {
        foreach ($this->electionReturns as $i => $e) {
            if ($e->code === $er->code) {
                $this->electionReturns[$i] = $er;
                return;
            }
        }
    }

    public function load(array $positions, PrecinctData $precinct): void
    {
        $this->putPrecinct($precinct);

        foreach ($positions as $position) {
            $this->positions[$position['code']] = $position;

            // Optionally flatten candidates
            foreach ($position['candidates'] as $candidate) {
                $this->candidates[$candidate['id']] = $candidate;
            }

            // Preload empty ballot template per position
            $this->putBallot(new BallotData(
                code: $position['code'],
                votes: new DataCollection(VoteData::class, []),
            ), $precinct->code); // 🔁 new signature
        }
    }

    public function setPositions(array $positionMap): void
    {
        foreach ($positionMap as $code => $position) {
            $this->positions[$code] = $position;
        }
    }

    public function getPosition(string $code): ?PositionData
    {
        return $this->positions[$code] ?? null;
    }

    public function setCandidates(array $candidateMap): void
    {
        foreach ($candidateMap as $code => $candidate) {
            $this->candidates[$code] = $candidate;
        }
    }

    public function getCandidate(string $code): ?CandidateData
    {
        return collect($this->candidates)->firstWhere('code', $code);
//        return $this->candidates[$code] ?? null;
    }

    public function allPositions(): array
    {
        return $this->positions;
    }

    public function allCandidates(): array
    {
        return $this->candidates;
    }

    public function getElectionReturnByPrecinct(string $precinctCode): ?ElectionReturnData
    {
        return $this->electionReturns[$precinctCode] ?? null;
    }

    public function setMappings(array|MappingData $mappings): void
    {
        if ($mappings instanceof MappingData) {
            $this->mappings = $mappings;
            return;
        }

        if (!isset($mappings['code'], $mappings['location_name'], $mappings['district'], $mappings['marks'])) {
            throw new \InvalidArgumentException('Missing required keys in mapping array: code, location_name, district, marks');
        }

        $this->mappings = new MappingData(
            code: $mappings['code'],
            location_name: $mappings['location_name'],
            district: $mappings['district'],
            marks: new DataCollection(MarkData::class, $mappings['marks']),
        );
    }

    public function getMappings(): MappingData
    {
        if (! $this->mappings) {
            throw new \RuntimeException('Mappings have not been initialized.');
        }

        return $this->mappings;
    }

    public function addBallotMark(string $ballotCode, string $key): void
    {
        $this->ballotMarks[$ballotCode] ??= [];
        if (!in_array($key, $this->ballotMarks[$ballotCode])) {
            $this->ballotMarks[$ballotCode][] = $key;
        }
    }

    public function getBallotMarkKeys(string $ballotCode): array
    {
        return $this->ballotMarks[$ballotCode] ?? [];
    }
}
