<?php

use TruthElectionDb\Models\{Candidate, Position, Precinct};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElection\Actions\InitializeSystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    File::ensureDirectoryExists(base_path('config'));

    //assumption is test is in tests/Feature/Console/Commands directory
    $electionSource = realpath(__DIR__ . '/../../../../config/election.json');
    $precinctSource = realpath(__DIR__ . '/../../../../config/precinct.yaml');

    expect($electionSource)->not->toBeFalse("Missing election.json fixture");
    expect($precinctSource)->not->toBeFalse("Missing precinct.yaml fixture");

    File::copy($electionSource, base_path('config/election.json'));
    File::copy($precinctSource, base_path('config/precinct.yaml'));
});

test('artisan election:setup-precinct works and displays expected output', function () {
    expect(Precinct::count())->toBe(0);
    expect(Position::count())->toBe(0);
    expect(Candidate::count())->toBe(0);

    $this->artisan('election:setup-precinct')
        ->expectsOutput('✅ Election setup complete.')
        ->assertSuccessful()
    ;

    expect(Precinct::count())->toBe(1);
    expect(Position::count())->toBeGreaterThan(0);
    expect(Candidate::count())->toBeGreaterThan(0);
});


test('artisan election:setup-precinct displays expected table', function () {
    Artisan::call('election:setup-precinct');
    $output = Artisan::output();

    expect($output)->toContain('✅ Election setup complete.');

    // Headers
    expect($output)->toContain('Precinct Code');
    expect($output)->toContain('Positions Created');
    expect($output)->toContain('Candidates Created');

    // Values
    expect($output)->toContain('CURRIMAO-001');
    expect($output)->toMatch('/\|\s+\d+\s+\|\s+\d+\s+\|/'); // loosely match row with two numeric columns
});

test('artisan election:setup-precinct does not duplicate data when run twice', function () {
    $this->artisan('election:setup-precinct')->assertExitCode(0);

    $position_count = Position::count();
    $candidate_count = Candidate::count();

    $this->artisan('election:setup-precinct')->assertExitCode(0);

    expect(Precinct::count())->toBe(1);
    expect(Position::count())->toBe($position_count);
    expect(Candidate::count())->toBe($candidate_count);
});

test('election:setup-precinct calls InitializeSystem with correct paths and handles output', function () {
    // Step 1: Mock the handle() static method BEFORE it's autoloaded
    $mock = \Mockery::mock(InitializeSystem::class);
    $mock->shouldReceive('handle')
        ->once()
        ->withArgs(function ($electionPath, $precinctPath) {
            return $electionPath === null && $precinctPath === null;
        })
        ->andReturn([
            'summary' => [
                'precinct_code' => 'CURRIMAO-001',
                'positions' => ['created' => 10],
                'candidates' => ['created' => 60],
            ]
        ]);

    // Step 2: Bind the mock instance into Laravel's container
    app()->instance(InitializeSystem::class, $mock);

    // Step 3: Run the command
    $exit = Artisan::call('election:setup-precinct');
    expect($exit)->toBe(0);

    $output = Artisan::output();
    expect($output)->toContain('✅ Election setup complete.');
    expect($output)->toContain('CURRIMAO-001');
    expect($output)->toContain('10');
    expect($output)->toContain('60');
});

test('election:setup-precinct accepts --config-path option', function () {
    // Create a test config directory
    $testConfigPath = base_path('tests/fixtures/test-config');
    File::ensureDirectoryExists($testConfigPath);

    // Copy fixtures to test directory
    $electionSource = realpath(__DIR__ . '/../../../../config/election.json');
    $precinctSource = realpath(__DIR__ . '/../../../../config/precinct.yaml');
    $mappingSource = realpath(__DIR__ . '/../../../../config/mapping.yaml');

    File::copy($electionSource, $testConfigPath . '/election.json');
    File::copy($precinctSource, $testConfigPath . '/precinct.yaml');
    File::copy($mappingSource, $testConfigPath . '/mapping.yaml');

    expect(Precinct::count())->toBe(0);

    // Run with --config-path option
    $this->artisan('election:setup-precinct', ['--config-path' => 'tests/fixtures/test-config'])
        ->expectsOutputToContain('📂 Loading configs from: tests/fixtures/test-config')
        ->expectsOutput('✅ Election setup complete.')
        ->assertSuccessful();

    expect(Precinct::count())->toBe(1);
    expect(Position::count())->toBeGreaterThan(0);
    expect(Candidate::count())->toBeGreaterThan(0);

    // Cleanup
    File::deleteDirectory($testConfigPath);
});
