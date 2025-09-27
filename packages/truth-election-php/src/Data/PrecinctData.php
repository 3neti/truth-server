<?php

namespace TruthElection\Data;

use Spatie\LaravelData\{Data, DataCollection};

class PrecinctData extends Data
{
    /**
     * @param string $code
     * @param string $location_name
     * @param float $latitude
     * @param float $longitude
     * @param DataCollection<ElectoralInspectorData> $electoral_inspectors
     * @param int|null $watchers_count
     * @param int|null $precincts_count
     * @param int|null $registered_voters_count
     * @param int|null $actual_voters_count
     * @param int|null $ballots_in_box_count
     * @param int|null $unused_ballots_count
     * @param int|null $spoiled_ballots_count
     * @param int|null $void_ballots_count
     * @param string|null $closed_at
     * @param DataCollection<BallotData>|null $ballots
     */
    public function __construct(
        public string $code,
        public string $location_name,
        public float $latitude,
        public float $longitude,
        public DataCollection $electoral_inspectors,
        public ?int $watchers_count = null,
        public ?int $precincts_count = null,
        public ?int $registered_voters_count = null,
        public ?int $actual_voters_count = null,
        public ?int $ballots_in_box_count = null,
        public ?int $unused_ballots_count = null,
        public ?int $spoiled_ballots_count = null,
        public ?int $void_ballots_count = null,
        public ?string $closed_at = null,
        public DataCollection|null $ballots = null,
    ) {}

    public function copyWith(array $overrides): self
    {
        return new self(
            code: $overrides['code'] ?? $this->code,
            location_name: $overrides['location_name'] ?? $this->location_name,
            latitude: $overrides['latitude'] ?? $this->latitude,
            longitude: $overrides['longitude'] ?? $this->longitude,
            electoral_inspectors: $overrides['electoral_inspectors'] ?? $this->electoral_inspectors,
            ballots: $overrides['ballots'] ?? $this->ballots,
        );
    }
}
