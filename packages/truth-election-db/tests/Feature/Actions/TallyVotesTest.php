<?php

use TruthElectionDb\Actions\{CastBallot, SetupElection, TallyVotes};
use TruthElectionDb\Models\{ElectionReturn, Precinct};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\File;
use TruthElection\Enums\Level;
use TruthElection\Data\{
    ElectionReturnData,
    CandidateData,
    PositionData,
    VoteData
};

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
                // 12+1 = 13 senators (overvote), should be rejected
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
    ])); // should be rejected due to overvote
});

dataset('action', function () {
    return [
        fn() => app(TallyVotes::class)
    ];
});

dataset('precinct', function () {
    return [
        fn() => Precinct::where('code', 'CURRIMAO-001')->first()
    ];
});

it('persists election return via handle()', function (TallyVotes $action, Precinct $precinct) {
    expect($precinct)->not->toBeNull();

    // Act
    $er = $action->run();

    // Assert the returned object
    expect($er)->toBeInstanceOf(ElectionReturnData::class)
        ->and($er->precinct->code)->toBe('CURRIMAO-001')
        ->and($er->tallies)->toHaveCount(5) // 2 presidents + 3 valid senators
        ->and($er->ballots)->toHaveCount(3);

    // Assert the return was persisted in DB
    $persisted = ElectionReturn::where('code', $er->code)->first();

    expect($persisted)->not->toBeNull()
        ->and($persisted->precinct_code)->toBe($precinct->code);
})->with('action', 'precinct');

it('returns a valid election return via controller', function () {
    $response = $this->postJson(route('tally.votes', []));

    $response->assertOk();

    $data = $response->json();

    expect($data)->toHaveKeys([
        'code',
        'precinct',
        'ballots',
        'tallies',
    ])
        ->and($data['precinct']['code'])->toBe('CURRIMAO-001')
        ->and($data['ballots'])->toHaveCount(3)
        ->and($data['tallies'])->toBeArray();

    // One should be invalid

    $tallyCollection = collect($data['tallies']);

    $presidents = $tallyCollection->where('position_code', 'PRESIDENT');
    expect($presidents)->toHaveCount(2); // 2 president votes

    $senators = $tallyCollection->where('position_code', 'SENATOR');
    expect($senators)->toHaveCount(3); // only valid senator votes from BAL-001 and BAL-002
});

//it('validates missing precinct_code field', function () {
//    $response = $this->postJson(route('votes.tally', []));
//
//    $response->assertStatus(422)
//        ->assertJsonValidationErrors(['precinct_code']);
//});

it('accepts optional election_return_code via controller', function () {
    $response = $this->postJson(route('tally.votes', [
        'election_return_code' => 'ER-CURRIMAO-TEST-001',
    ]));

    $response->assertOk();

    $data = $response->json();

    expect($data)->toHaveKeys([
        'code',
        'precinct',
        'ballots',
        'tallies',
    ])
        ->and($data['code'])->toBe('ER-CURRIMAO-TEST-001')
        ->and($data['precinct']['code'])->toBe('CURRIMAO-001')
        ->and($data['ballots'])->toHaveCount(3);

    // Confirm persisted
    $persisted = ElectionReturn::where('code', 'ER-CURRIMAO-TEST-001')->first();
    expect($persisted)->not->toBeNull()
        ->and($persisted->precinct_code)->toBe('CURRIMAO-001');
});
