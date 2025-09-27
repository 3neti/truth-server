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

interface ElectionStoreInterface
{
    public static function instance(): self;

    public function getBallotsForPrecinct(string $precinctCode): array;

    public function getBallots(string $precinctCode): DataCollection;

    public function putBallot(BallotData $ballot, string $precinctCode): void;

    public function getPrecinct(?string $code = null): ?PrecinctData;

    public function putPrecinct(PrecinctData $precinct): void;

    public function putElectionReturn(ElectionReturnData $er): void;

    public function getElectionReturn(?string $code = null): ?ElectionReturnData;

    public function getElectionReturnByPrecinct(string $precinctCode): ?ElectionReturnData;

    public function replaceElectionReturn(ElectionReturnData $er): void;

    public function load(array $positions, PrecinctData $precinct): void;

    public function setPositions(array $positionMap): void;

    public function getPosition(string $code): ?PositionData;

    public function setCandidates(array $candidateMap): void;

    public function getCandidate(string $code): ?CandidateData;

    public function allPositions(): array;

    public function allCandidates(): array;

    public function findInspector(ElectionReturnData $er, string $id): ?ElectoralInspectorData;

    public function findPrecinctInspector(ElectionReturnData $er, string $id): ?ElectoralInspectorData;

    public function getInspectorsForPrecinct(string $precinctCode): DataCollection;

    public function findSignatory(ElectionReturnData $er, string $id): ElectoralInspectorData;

    public function reset(): void;

    public function setMappings(array|MappingData $mappings): void;

    public function getMappings(): MappingData;

    public function addBallotMark(string $ballotCode, string $key): void;

    public function getBallotMarkKeys(string $ballotCode): array;
}
