<?php

use TruthElection\Data\{CandidateData, PositionData, PrecinctData, SignPayloadData, VoteData};
use TruthElection\Actions\{SignElectionReturn, SubmitBallot, GenerateElectionReturn};
use TruthElection\Enums\{ElectoralInspectorRole, Level};
use TruthElection\Pipes\EncodeElectionReturnLines;
use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Data\FinalizeErContext;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\DataCollection;

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->store = app(ElectionStoreInterface::class);
    $this->store->reset();

    $this->precinct = PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [
            ['id' => 'A1', 'name' => 'Alice', 'role' => ElectoralInspectorRole::CHAIRPERSON],
            ['id' => 'B2', 'name' => 'Bob', 'role' => ElectoralInspectorRole::MEMBER],
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
    SubmitBallot::run('BAL-002', $votes2);

    $return = GenerateElectionReturn::run();

    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:A1:sig1'), $return->code);
    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:B2:sig2'), $return->code);

    $this->return = $this->store->getElectionReturn($return->code);
});

test('encodes election return into lines and stores as encoded_lines.txt', function () {
    Storage::fake('local');

    $ctx = new FinalizeErContext(
        precinct: test()->precinct,
        er: test()->return,
        disk: 'local',
        folder: 'ER-' . test()->return->code . '/final',
        payload: '{}',
        maxChars: 1200,
        force: false,
    );

    $pipe = new EncodeElectionReturnLines();

    $result = $pipe->handle($ctx, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);

    Storage::disk('local')->assertExists("ER-{$ctx->er->code}/final/encoded_lines.txt");

    $contents = Storage::disk('local')->get("ER-{$ctx->er->code}/final/encoded_lines.txt");

    expect($contents)->toContain('ER|v1|')->not->toBeEmpty();
});
