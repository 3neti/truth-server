<?php

use TruthElection\Actions\{GenerateElectionReturn, InputPrecinctStatistics, SignElectionReturn, SubmitBallot};
use TruthElection\Data\{CandidateData, PositionData, PrecinctData, SignPayloadData, VoteData};
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Enums\ElectoralInspectorRole;
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->store = InMemoryElectionStore::instance();
    $this->store->reset();

    $this->store->putPrecinct(PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [
            [
                'id' => 'A1',
                'name' => 'Alice',
                'role' => ElectoralInspectorRole::CHAIRPERSON,
            ],
            [
                'id' => 'B2',
                'name' => 'Bob',
                'role' => ElectoralInspectorRole::MEMBER,
            ],
        ]
    ]));

    $votes1 = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-001', name: 'Juan Dela Cruz', alias: 'JUAN', position: new PositionData(
                    code: 'PRESIDENT',
                    name: 'President of the Philippines',
                    level: Level::NATIONAL,
                    count: 1
                )),
            ])
        ),
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-002', name: 'Maria Santos', alias: 'MARIA', position: $position = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-003', name: 'Pedro Reyes', alias: 'PEDRO', position: $position),
            ])
        ),
    ]);

    $votes2 = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-004', name: 'Jose Rizal', alias: 'JOSE', position: new PositionData(
                    code: 'PRESIDENT',
                    name: 'President of the Philippines',
                    level: Level::NATIONAL,
                    count: 1
                )),
            ])
        ),
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-002', name: 'Maria Santos', alias: 'MARIA', position: $position = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-005', name: 'Andres Bonifacio', alias: 'ANDRES', position: $position),
            ])
        ),
    ]);

    SubmitBallot::run('BAL-001', $votes1);
    SubmitBallot::run('BAL-001', $votes2);

    $this->return = GenerateElectionReturn::run('PRECINCT-01');

    $action = app(SignElectionReturn::class);
    $action->handle(SignPayloadData::fromQrString('BEI:A1:sig1'), $this->return->code);
    $action->handle(SignPayloadData::fromQrString('BEI:B2:sig2'), $this->return->code);
});

test('updates all precinct statistics fields', function () {
    $payload = [
        'watchers_count' => 10,
        'precincts_count' => 2,
        'registered_voters_count' => 1500,
        'actual_voters_count' => 1450,
        'ballots_in_box_count' => 1448,
        'unused_ballots_count' => 50,
        'spoiled_ballots_count' => 2,
        'void_ballots_count' => 0,
    ];

    $updated = InputPrecinctStatistics::run($payload);

    foreach ($payload as $key => $expected) {
        expect($updated->{$key})->toBe($expected);
    }

    $stored = $this->store->getPrecinct();

    foreach ($payload as $key => $expected) {
        expect($stored->{$key})->toBe($expected);
    }
});

test('allows partial updates without affecting other fields', function () {
    // Step 1: set initial values
    InputPrecinctStatistics::run([
        'watchers_count' => 5,
        'registered_voters_count' => 1000,
    ]);

    // Step 2: update only watchers_count
    $updated = InputPrecinctStatistics::run([
        'watchers_count' => 8,
    ]);

    expect($updated)
        ->watchers_count->toBe(8)
        ->registered_voters_count->toBe(1000); // should remain

    $stored = $this->store->getPrecinct();

    expect($stored)
        ->watchers_count->toBe(8)
        ->registered_voters_count->toBe(1000);
});

test('allows setting fields to null', function () {
    InputPrecinctStatistics::run([
        'unused_ballots_count' => 30,
    ]);

    $updated = InputPrecinctStatistics::run([
        'unused_ballots_count' => null,
    ]);

    expect($updated->unused_ballots_count)->toBeNull();
});

test('ignores invalid keys in payload', function () {
    $updated = InputPrecinctStatistics::run([
        'watchers_count' => 7,
        'invalid_key' => 999,
    ]);

    expect($updated->watchers_count)->toBe(7);
    expect(isset($updated->invalid_key))->toBeFalse();
});

test('sets the closed_at timestamp properly', function () {
    $now = now()->toIso8601String();

    $updated = InputPrecinctStatistics::run([
        'closed_at' => $now,
    ]);

    expect($updated->closed_at)->toBe($now);

    $stored = $this->store->getPrecinct();
    expect($stored->closed_at)->toBe($now);
});

test('allows closed_at to be set to null', function () {
    // First set it to a value
    InputPrecinctStatistics::run([
        'closed_at' => now()->toIso8601String(),
    ]);

    // Then set it to null
    $updated = InputPrecinctStatistics::run([
        'closed_at' => null,
    ]);

    expect($updated->closed_at)->toBeNull();

    $stored = $this->store->getPrecinct();
    expect($stored->closed_at)->toBeNull();
});
