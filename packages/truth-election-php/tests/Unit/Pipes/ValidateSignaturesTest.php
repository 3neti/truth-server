<?php

use TruthElection\Data\{CandidateData, PositionData, PrecinctData, SignPayloadData, VoteData};
use TruthElection\Actions\{GenerateElectionReturn, SignElectionReturn, SubmitBallot};
use TruthElection\Data\{ElectoralInspectorData, FinalizeErContext};
use TruthElection\Policies\Signatures\ChairPlusMemberPolicy;
use TruthElection\Enums\{ElectoralInspectorRole, Level};
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Pipes\ValidateSignatures;
use Spatie\LaravelData\DataCollection;

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
//            position: $position = new PositionData(
//                code: 'PRESIDENT',
//                name: 'President of the Philippines',
//                level: Level::NATIONAL,
//                count: 1
//            ),
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

    $return = GenerateElectionReturn::run('PRECINCT-01');

    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:A1:sig1'), $return->code);
    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:B2:sig2'), $return->code);

    $this->return = $this->store->getElectionReturn($return->code);
});

test('validate signatures passes when chair and one member signed', function () {
    $context = new FinalizeErContext(
        precinct: test()->precinct,
        er: test()->return,
        disk: 'local',
        folder: 'er/finalize',
        payload: '{}',
        maxChars: 8000,
        force: false,
    );

    $pipe = new ValidateSignatures(new ChairPlusMemberPolicy());

    $result = $pipe->handle($context, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);
});

test('validate signatures fails when only member signed and force is false', function () {
    $return = test()->return;

    // Remove chair signature manually
    $signatures = $return->signatures->filter(
        fn ($sig) => $sig->role === ElectoralInspectorRole::MEMBER
    );

    // 💡 Fix: Use withUpdatedSignatures instead of withInspectorSignature
    $return = $return->withUpdatedSignatures(new DataCollection(
        ElectoralInspectorData::class,
        $signatures->toArray()
    ));

    $context = new FinalizeErContext(
        precinct: test()->precinct,
        er: $return,
        disk: 'local',
        folder: 'er/finalize',
        payload: '{}',
        maxChars: 8000,
        force: false,
    );

    $pipe = new ValidateSignatures(new ChairPlusMemberPolicy());

    $pipe->handle($context, fn ($ctx) => $ctx);
})->throws(RuntimeException::class, "Missing required signatures (need chair + at least one member).");
