<?php

use TruthElection\Data\{CandidateData, PositionData, PrecinctData, SignPayloadData, VoteData};
use TruthElection\Actions\{GenerateElectionReturn, SignElectionReturn, SubmitBallot};
use Spatie\LaravelData\{DataCollection, Optional};
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Enums\ElectoralInspectorRole;
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Enums\Level;

uses(ResetsElectionStore::class)->beforeEach(function () {

    $this->store = InMemoryElectionStore::instance();
    $this->store->reset();

    $this->precinct = PrecinctData::from([
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
    ]);

    $this->store->putPrecinct($this->precinct);

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
});

test('successfully signs as chairperson', function () {
    // 📝 Prepare payload: Alice = A1, chairperson
    $payload = SignPayloadData::fromQrString('BEI:A1:base64signature');

    // 🚀 Perform signature
    $result = SignElectionReturn::run($payload);

    // 🔍 Assert result structure
    expect($result)
        ->message->toBe('Signature saved successfully.')
        ->id->toBe('A1')
        ->name->toBe('Alice')
        ->role->toBe('chairperson')
        ->signed_at->toBeString()
    ;

    // 🗃 Confirm election return has been updated in memory
    $updatedReturn = $this->store->getElectionReturn($this->return->code);

    expect($updatedReturn)->not->toBeNull();
    expect($updatedReturn->signedInspectors())->toHaveCount(1);

    // Find signatures by ID for clear assertions
    $bob = $this->store->findSignatory($updatedReturn, 'B2');
    $alice = $this->store->findSignatory($updatedReturn, 'A1');

    expect($bob)
        ->not->toBeNull()
        ->name->toBe('Bob')
        ->role->value->toBe('member')
        ->signature->toBeInstanceOf(Optional::class)
        ->and($alice)
        ->not->toBeNull()
        ->name->toBe('Alice')
        ->role->value->toBe('chairperson')
        ->signature->toBe('base64signature'); // signed in previous setup

    // just signed in this test
});

test('appends signature when second inspector signs', function () {
    // First: Alice (A1, chairperson)
    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:A1:sig1'));

    // Second: Bob (B2, member)
    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:B2:sig2'));

    $updated = $this->store->getElectionReturn($this->return->code);

    // Use signedInspectors() to confirm both signed
    $signed = $updated->signedInspectors();
    expect($signed)->toHaveCount(2);

    // Optional: assert individual signatures
    $ids = $signed->pluck('id');
    expect($ids)->toContain('A1')->toContain('B2');

    $bob = $this->store->findSignatory($updated, 'B2');
    expect($bob->signature)->toBe('sig2');
});

test('fails if inspector ID is not found in roster', function () {
    $payload = SignPayloadData::fromQrString('BEI:Z9:sig');
    SignElectionReturn::run($payload);
})->throws(Exception::class, "Could not create `TruthElection\Data\ElectoralInspectorData`: the constructor requires 5 parameters, 2 given. Parameters given: signature, signed_at. Parameters missing: id, name, role.");
