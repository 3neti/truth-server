<?php

use TruthElection\Data\{CandidateData, FinalizeErContext, PositionData, PrecinctData, SignPayloadData, VoteData};
use TruthElection\Actions\{GenerateElectionReturn, SignElectionReturn, SubmitBallot};
use TruthElection\Enums\{ElectoralInspectorRole, Level};
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Pipes\CloseBalloting;
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

    $action = app(SignElectionReturn::class);

    $action->handle(SignPayloadData::fromQrString('BEI:A1:sig1'), $return->code);
    $action->handle(SignPayloadData::fromQrString('BEI:B2:sig2'), $return->code);

    $this->return = $this->store->getElectionReturn($return->code);
});

test('close balloting sets closed_at on precinct via InputPrecinctStatistics', function () {
    $context = new FinalizeErContext(
        precinct: test()->precinct,
        er: test()->return,
        disk: 'local',
        folder: 'er/finalize',
        payload: '{}',
        maxChars: 8000,
        force: false,
    );

    $pipe = new CloseBalloting();

    $result = $pipe->handle($context, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);

    $updated = test()->store->precincts[$context->precinct->code];

    expect($updated->code)->toEqual($context->precinct->code)
        ->and($updated->closed_at)->not->toBeNull()
        ->and(strtotime($updated->closed_at))->toBeGreaterThan(0);
});

test('close balloting sets closed_at if not set', function () {
    $context = new FinalizeErContext(
        precinct: test()->precinct,
        er: test()->return,
        disk: 'local',
        folder: 'er/finalize',
        payload: '{}',
        maxChars: 8000,
        force: false,
    );

    $pipe = new CloseBalloting();
    $result = $pipe->handle($context, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);

    $updated = test()->store->precincts[$context->precinct->code];
    expect($updated->closed_at)->not->toBeNull()
        ->and(strtotime($updated->closed_at))->toBeGreaterThan(0);
});

test('close balloting does not overwrite closed_at if already set', function () {
    $firstClosedAt = now()->toISOString();

//    $precinct = test()->precinct;
    \TruthElection\Actions\InputPrecinctStatistics::run(
        ['closed_at' => $firstClosedAt]
    );

    $context = new FinalizeErContext(
        precinct: test()->precinct,
        er: test()->return,
        disk: 'local',
        folder: 'er/finalize',
        payload: '{}',
        maxChars: 8000,
        force: false,
    );

    $pipe = new CloseBalloting();
    $pipe->handle($context, fn ($ctx) => $ctx);

    $actual = test()->store->precincts[$context->precinct->code]->closed_at;
    expect($actual)->toBe($firstClosedAt);
});
