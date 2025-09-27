<?php

use TruthElectionDb\Models\{Candidate, Position, Precinct};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use Illuminate\Testing\Fluent\AssertableJson;
use TruthElectionDb\Actions\SetupElection;
use Illuminate\Support\Facades\File;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    File::ensureDirectoryExists(base_path('config'));
    File::copy(realpath(__DIR__ . '/../../../config/election.json'), base_path('config/election.json'));
    File::copy(realpath(__DIR__ . '/../../../config/precinct.yaml'), base_path('config/precinct.yaml'));
    File::copy(realpath(__DIR__ . '/../../../config/mapping.yaml'), base_path('config/mapping.yaml'));
});

test('SetupElectionFromFiles::handle() loads config and persists to database', function () {
    $result = SetupElection::run();

    expect($result['ok'])->toBeTrue()
        ->and($result['summary']['precinct_code'])->toBe('CURRIMAO-001')
        ->and($result['summary']['positions']['created'])->toBeGreaterThan(0)
        ->and($result['summary']['candidates']['created'])->toBeGreaterThan(0);

    // 🧪 Check database has persisted data
    expect(Precinct::count())->toBe(1);
    expect(Position::count())->toBeGreaterThan(0);
    expect(Candidate::count())->toBeGreaterThan(0);

    $precinct = Precinct::first();
    expect($precinct->code)->toBe('CURRIMAO-001');
});

test('SetupElectionFromFiles::asController works via HTTP POST', function () {
    $response = $this->postJson(route('election.setup'), [
        'election_path' => null,
        'precinct_path' => null,
    ]);

    $response
        ->assertStatus(200)
        ->assertJson(fn (AssertableJson $json) =>
        $json->where('ok', true)
            ->has('summary.precinct_code')
            ->has('summary.positions.created')
            ->has('summary.candidates.created')
            ->has('files.election')
            ->has('files.precinct')
        );

    expect(Precinct::count())->toBe(1);
});

test('SetupElectionFromFiles::handle() gracefully fails with missing config files', function () {
    File::delete(base_path('config/election.json'));
    File::delete(base_path('config/precinct.yaml'));

    $result = SetupElection::run();

    expect($result['ok'])->toBeFalse()
        ->and($result['error'])->toBeString();
})->skip(); //do not unskip

test('SetupElectionFromFiles::handle() respects custom file paths', function () {
    $electionPath = base_path('config/election.json');
    $precinctPath = base_path('config/precinct.yaml');

    $result = SetupElection::run($electionPath, $precinctPath);

    expect($result['ok'])->toBeTrue();
});

test('SetupElectionFromFiles::asController returns error on missing files', function () {
    File::delete(base_path('config/election.json'));
    File::delete(base_path('config/precinct.yaml'));

    $response = $this->postJson('/election/setup');

    $response->assertStatus(200)
        ->assertJson(fn (AssertableJson $json) =>
        $json->where('ok', false)
            ->has('error')
        );
})->skip(); //do not unskip

test('Returned result includes resolved file paths', function () {
    $result = SetupElection::run();

    expect($result['files']['election'])->toEndWith('election.json')
        ->and($result['files']['precinct'])->toEndWith('precinct.yaml');
});

test('Running setup twice does not create duplicates', function () {
    SetupElection::run();
    $initialCount = Candidate::count();

    SetupElection::run();
    expect(Candidate::count())->toBe($initialCount); // or whatever behavior you define
});

test('SetupElectionFromFiles::asController returns correct summary', function () {
    $response = $this->postJson(route('election.setup'));

    $response->assertJson([
        'ok' => true,
        'summary' => [
            'precinct_code' => 'CURRIMAO-001',
        ],
    ]);
});


