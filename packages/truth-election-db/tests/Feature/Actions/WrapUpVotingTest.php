<?php

use TruthElection\Data\{CandidateData, ElectionReturnData, PositionData, SignPayloadData, VoteData};
use TruthElectionDb\Actions\{AttestReturn, SetupElection, CastBallot, TallyVotes, WrapUpVoting};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Support\ElectionStoreInterface;
use TruthElectionDb\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;
use TruthElectionDb\Models\Precinct;
use Illuminate\Support\Facades\File;
use TruthElection\Enums\Level;
use Illuminate\Support\Carbon;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    $this->resetElectionStore();

    File::ensureDirectoryExists(base_path('config'));
    File::copy(realpath(__DIR__ . '/../../../config/election.json'), base_path('config/election.json'));
    File::copy(realpath(__DIR__ . '/../../../config/precinct.yaml'), base_path('config/precinct.yaml'));

    SetupElection::run();

    CastBallot::run('BAL-001', collect([
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
    ]));

    CastBallot::run('BAL-002', collect([
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
    ]));

    CastBallot::run('BAL-003', collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-006', name: 'Emilio Aguinaldo', alias: 'EMILIO', position: $position = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-007', name: 'Apolinario Mabini', alias: 'APO', position: $position),
                new CandidateData(code: 'CANDIDATE-008', name: 'Gregorio del Pilar', alias: 'GREG', position: $position),
                new CandidateData(code: 'CANDIDATE-009', name: 'Melchora Aquino', alias: 'TANDANG', position: $position),
                new CandidateData(code: 'CANDIDATE-010', name: 'Antonio Luna', alias: 'TONIO', position: $position),
                new CandidateData(code: 'CANDIDATE-011', name: 'Marcelo del Pilar', alias: 'CEL', position: $position),
                new CandidateData(code: 'CANDIDATE-012', name: 'Diego Silang', alias: 'DIEGO', position: $position),
                new CandidateData(code: 'CANDIDATE-013', name: 'Gabriela Silang', alias: 'GABRIELA', position: $position),
                new CandidateData(code: 'CANDIDATE-014', name: 'Francisco Baltazar', alias: 'BALTAZAR', position: $position),
                new CandidateData(code: 'CANDIDATE-015', name: 'Leona Florentino', alias: 'LEONA', position: $position),
                new CandidateData(code: 'CANDIDATE-016', name: 'Josefa Llanes Escoda', alias: 'JOSEFA', position: $position),
                new CandidateData(code: 'CANDIDATE-017', name: 'Manuel Quezon', alias: 'QUEZON', position: $position),
                new CandidateData(code: 'CANDIDATE-018', name: 'Sergio Osmeña', alias: 'OSMENA', position: $position),
            ])
        ),
    ]));

    $this->return = app(TallyVotes::class)->run();

});

test('wraps up voting and generates final return', function () {
    $result = app(WrapUpVoting::class)->run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1000,
        dir: 'final',
        force: true
    );

    expect($result)->not->toBeNull();
    expect($result)->toBeInstanceOf(ElectionReturnData::class);
    expect($result->code)->toBe($this->return->code);
    expect($result->ballots)->not->toBeEmpty();
    expect($result->tallies)->not->toBeEmpty();
});

test('fails if signatures are missing and force is false', function () {
    // Assume signatures were NOT added to the ElectionReturn
    expect(fn() => WrapUpVoting::run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1000,
        dir: 'final',
        force: false
    ))->toThrow(RuntimeException::class, 'Missing required signatures (need chair + at least one member).');
});

test('succeeds if signatures are present and valid', function () {
    $store = app(ElectionStoreInterface::class);
    $er = $store->getElectionReturnByPrecinct('CURRIMAO-001');

    AttestReturn::run(SignPayloadData::fromQrString('BEI:uuid-juan:signature123'), $er->code);
    AttestReturn::run(SignPayloadData::fromQrString('BEI:uuid-maria:signature456'), $er->code);

    $result = WrapUpVoting::run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1000,
        dir: 'final',
        force: false
    );

    expect($result)->toBeInstanceOf(ElectionReturnData::class);
    expect($result->signedInspectors())->toHaveCount(2);
});

test('bypasses signature validation when force is true', function () {
    $result = WrapUpVoting::run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1000,
        dir: 'final',
        force: true
    );

    expect($result)->toBeInstanceOf(ElectionReturnData::class);
});

test('sets closed_at if not previously set', function () {
    $precinct = Precinct::query()->where('code', 'CURRIMAO-001')->first();
    expect($precinct->closed_at)->toBeNull();

    WrapUpVoting::run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1000,
        dir: 'final',
        force: true, // skip signature validation
    );

    $precinct->refresh();

    expect($precinct->closed_at)->not->toBeNull();
    expect($precinct->closed_at)->toBeString();
});

test('does not overwrite closed_at if already set', function () {
    $originalClosedAt = now()->subHours(2);
    $precinct = Precinct::query()->where('code', 'CURRIMAO-001')->first();
    $precinct->closed_at = $originalClosedAt;
    $precinct->save();
    expect(Carbon::parse($precinct->closed_at)->toISOString())->toBe(
        $originalClosedAt->copy()->micro(0)->toISOString()
    );

    WrapUpVoting::run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1000,
        dir: 'final',
        force: true,
    );

    expect(Carbon::parse($precinct->closed_at)->toISOString())->toBe(
        $originalClosedAt->copy()->micro(0)->toISOString()
    );
});


test('wrapup-voting endpoint returns valid election return JSON', function () {
    // Attest signatures to pass validation
    $store = app(ElectionStoreInterface::class);
    $er = $store->getElectionReturnByPrecinct('CURRIMAO-001');
    AttestReturn::run(SignPayloadData::fromQrString('BEI:uuid-juan:signature123'), $er->code);
    AttestReturn::run(SignPayloadData::fromQrString('BEI:uuid-maria:signature456'), $er->code);

    // Send HTTP POST request to the endpoint
    $response = $this->postJson(route('wrapup.voting'), [
        'disk' => 'local',
        'payload' => 'minimal',
        'maxChars' => 1000,
        'dir' => 'final',
        'force' => false,
    ]);

    $response->assertSuccessful();

    $data = $response->json();

    expect($data)->toBeArray();
    expect($data)->toHaveKey('code');
    expect($data)->toHaveKey('ballots');
    expect($data)->toHaveKey('tallies');
});
