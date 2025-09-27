<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Support\DatabaseElectionStore;
use TruthElection\Actions\ReadVote;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Data\PrecinctData;
use TruthElection\Enums\Level;

uses(RefreshDatabase::class)->beforeEach(function () {
    $this->store = DatabaseElectionStore::instance();

    $this->precinct = PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [],
    ]);

    $this->store->putPrecinct($this->precinct);

    $this->president = new PositionData(
        code: 'PRESIDENT',
        name: 'President of the Philippines',
        level: Level::NATIONAL,
        count: 1
    );

    $this->senator = new PositionData(
        code: 'SENATOR',
        name: 'Senator',
        level: Level::NATIONAL,
        count: 2
    );

    $this->candidates = [
        new CandidateData(
            code: 'CANDIDATE-001',
            name: 'Juan Dela Cruz',
            alias: 'JUAN',
            position: $this->president
        ),
        new CandidateData(
            code: 'CANDIDATE-002',
            name: 'Maria Santos',
            alias: 'MARIA',
            position: $this->senator
        ),
        new CandidateData(
            code: 'CANDIDATE-003',
            name: 'Pedro Reyes',
            alias: 'PEDRO',
            position: $this->senator
        ),
    ];

    $this->store->setCandidates($this->candidates);

    $this->store->setMappings([
        'code' => $this->precinct->code,
        'location_name' => $this->precinct->location_name,
        'district' => 'District 1',
        'marks' => [
            ['key' => 'PRESIDENT-1', 'value' => 'CANDIDATE-001'],
            ['key' => 'SENATOR-1', 'value' => 'CANDIDATE-002'],
            ['key' => 'SENATOR-2', 'value' => 'CANDIDATE-003'],
        ],
    ]);
});

it('finalizes a ballot via controller endpoint', function () {
    $ballotCode = 'BALLOT-HTTP-001';

    ReadVote::run($ballotCode, 'PRESIDENT-1');
    ReadVote::run($ballotCode, 'SENATOR-1');
    ReadVote::run($ballotCode, 'SENATOR-2');

    $response = $this->postJson(route('finalize.ballot'), [
        'code' => $ballotCode,
    ]);

    $response->assertSuccessful();

    $response->assertJsonStructure([
        'precinct_code',
        'code',
        'votes' => [
            '*' => [
                'position' => ['code', 'name', 'level', 'count'],
                'candidates' => [
                    '*' => [
                        'code', 'name', 'alias', 'position' => ['code', 'name', 'level', 'count'],
                    ],
                ],
            ],
        ],
    ]);

    $data = $response->json();

    expect($data['code'])->toBe($ballotCode);
    expect($data['precinct_code'])->toBe($this->precinct->code);
    expect(count($data['votes']))->toBe(2);
});
