<?php

use TruthElectionDb\Actions\{RecordStatistics, SetupElection, CastBallot, TallyVotes};
use TruthElection\Data\{CandidateData, PositionData, VoteData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;
use TruthElectionDb\Models\Precinct;
use Illuminate\Support\Facades\File;
use TruthElection\Enums\Level;

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

    app(TallyVotes::class)->run('CURRIMAO-001');

});

test('persists all statistics fields to database store', function () {
    $payload = [
        'watchers_count' => 5,
        'precincts_count' => 2,
        'registered_voters_count' => 1000,
        'actual_voters_count' => 950,
        'ballots_in_box_count' => 949,
        'unused_ballots_count' => 50,
        'spoiled_ballots_count' => 1,
        'void_ballots_count' => 0,
        'closed_at' => now()->toIso8601String(),
    ];

    $result = RecordStatistics::run($payload);

    foreach ($payload as $key => $expected) {
        expect($result->{$key})->toBe($expected);
    }

    $stored = Precinct::query()->where('code', 'CURRIMAO-001')->first();

    foreach ($payload as $key => $expected) {
        expect($stored->{$key})->toBe($expected);
    }
});

test('allows setting individual fields to null and persists it', function () {
    RecordStatistics::run([
        'unused_ballots_count' => 42,
    ]);

    $updated = RecordStatistics::run([
        'unused_ballots_count' => null,
    ]);

    expect($updated->unused_ballots_count)->toBeNull();

    $stored = Precinct::query()->where('code', 'CURRIMAO-001')->first();
    expect($stored->unused_ballots_count)->toBeNull();
});

test('updates only specified fields without overwriting others', function () {
    RecordStatistics::run([
        'registered_voters_count' => 1500,
        'actual_voters_count' => 1450,
    ]);

    $updated = RecordStatistics::run([
        'actual_voters_count' => 1400,
    ]);

    expect($updated->actual_voters_count)->toBe(1400);
    expect($updated->registered_voters_count)->toBe(1500);

    $stored = Precinct::query()->where('code', 'CURRIMAO-001')->first();
    expect($stored->actual_voters_count)->toBe(1400);
    expect($stored->registered_voters_count)->toBe(1500);
});

test('ignores unknown fields in payload', function () {
    $updated = RecordStatistics::run([
        'spoiled_ballots_count' => 3,
        'foobar' => 999, // should be ignored
    ]);

    expect($updated->spoiled_ballots_count)->toBe(3);
    expect(isset($updated->foobar))->toBeFalse();
});

test('PATCH /record-statistics updates statistics via controller', function () {
    $payload = [
        'watchers_count' => 8,
        'registered_voters_count' => 1234,
        'closed_at' => now()->toIso8601String(),
    ];

    $response = $this->patchJson(
        route('record.statistics'),
        $payload
    );

    $response->assertOk();

    $json = $response->json();

    expect($json['code'])->toBe('CURRIMAO-001')
        ->and($json['watchers_count'])->toBe(8)
        ->and($json['registered_voters_count'])->toBe(1234)
        ->and($json['closed_at'])->toBe($payload['closed_at']);

    $stored = Precinct::whereCode('CURRIMAO-001')->first();

    expect($stored->watchers_count)->toBe(8)
        ->and($stored->registered_voters_count)->toBe(1234);
});

test('validates input data via controller', function () {
    $response = $this->patchJson(
        route('record.statistics'),
        ['watchers_count' => -5] // invalid
    );

    $response->assertStatus(422);
    $response->assertJsonValidationErrors(['watchers_count']);
});
