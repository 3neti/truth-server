<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Enums\Level;

uses(RefreshDatabase::class)->beforeEach(function () {
//    $this->resetElectionStore();

    $this->store = \TruthElectionDb\Support\DatabaseElectionStore::instance();

    $this->precinct = \TruthElection\Data\PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [],
    ]);

    $this->store->putPrecinct($this->precinct);

    // Define positions
    $this->presidentPosition = new PositionData(
        code: 'PRESIDENT',
        name: 'President of the Philippines',
        level: Level::NATIONAL,
        count: 1
    );

    $this->senatorPosition = new PositionData(
        code: 'SENATOR',
        name: 'Senator',
        level: Level::NATIONAL,
        count: 12
    );

    // Define candidates
    $this->presidentCandidate1 = new CandidateData(
        code: 'CANDIDATE-001',
        name: 'Juan Dela Cruz',
        alias: 'JUAN',
        position: $this->presidentPosition
    );

    $this->presidentCandidate2 = new CandidateData(
        code: 'CANDIDATE-004',
        name: 'John Die',
        alias: 'JOHN',
        position: $this->presidentPosition
    );

    $this->senatorCandidate1 = new CandidateData(
        code: 'CANDIDATE-002',
        name: 'Maria Santos',
        alias: 'MARIA',
        position: $this->senatorPosition
    );

    $this->senatorCandidate2 = new CandidateData(
        code: 'CANDIDATE-003',
        name: 'Pedro Reyes',
        alias: 'PEDRO',
        position: $this->senatorPosition
    );

    $this->store->setCandidates([
        $this->presidentCandidate1,
        $this->presidentCandidate2,
        $this->senatorCandidate1,
        $this->senatorCandidate2,
    ]);

    $this->store->setMappings([
        'code' => $this->precinct->code,
        'location_name' => $this->precinct->location_name,
        'district' => 'District 1',
        'marks' => [
            ['key' => 'PRESIDENT-1', 'value' => $this->presidentCandidate1->code],
            ['key' => 'PRESIDENT-2', 'value' => $this->presidentCandidate2->code],
            ['key' => 'SENATOR-1', 'value' => $this->senatorCandidate1->code],
            ['key' => 'SENATOR-2', 'value' => $this->senatorCandidate2->code],
        ],
    ]);
});

it('returns a valid BallotData response via HTTP', function () {
    $this->postJson(route('read.vote'), [
        'code' => 'BALLOT-2025-003',
        'key'  => 'PRESIDENT-1',
    ])
        ->assertSuccessful()
        ->assertJsonPath('code', 'BALLOT-2025-003')
        ->assertJsonStructure(['code', 'votes']);
});
