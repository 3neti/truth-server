<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, File};
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElection\Actions\FinalizeBallot;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    File::ensureDirectoryExists(base_path('config'));

    $electionSource = realpath(__DIR__ . '/../../../../config/election.json');
    $precinctSource = realpath(__DIR__ . '/../../../../config/precinct.yaml');
    $mappingSource = realpath(__DIR__ . '/../../../../config/mapping.yaml');

    expect($electionSource)->not->toBeFalse("Missing election.json fixture");
    expect($precinctSource)->not->toBeFalse("Missing precinct.yaml fixture");
    expect($mappingSource)->not->toBeFalse("Missing mapping.yaml fixture");

    File::copy($electionSource, base_path('config/election.json'));
    File::copy($precinctSource, base_path('config/precinct.yaml'));
    File::copy($mappingSource, base_path('config/mapping.yaml'));

    $this->artisan('election:setup-precinct')->assertExitCode(0);
});

test('election:finalize-ballot successfully finalizes vote', function () {
    // Simulate reading a vote before finalizing
    Artisan::call('election:read-vote', [
        'ballot_code' => 'FINALIZE-001',
        'mark_key' => 'A1',
    ]);

    $exitCode = Artisan::call('election:finalize-ballot', [
        'ballot_code' => 'FINALIZE-001',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0)
        ->and($output)->toContain('✅ Ballot successfully finalized.')
        ->and($output)->toContain('Ballot Code: FINALIZE-001')
        ->and($output)->toContain('Votes:') // Optional, if your command includes this
        ->and($output)->toContain('🔹'); // At least one vote line
});

test('election:finalize-ballot fails for unknown mark key (via read-vote)', function () {
    $exitCode = Artisan::call('election:read-vote', [
        'ballot_code' => 'FINALIZE-002',
        'mark_key' => 'INVALID_KEY',
    ]);

    expect($exitCode)->toBe(1); // Make sure this test fails first if setup is wrong

    // FinalizeBallot should now see no votes
    $exitCode = Artisan::call('election:finalize-ballot', [
        'ballot_code' => 'FINALIZE-002',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0) // Still a valid (but empty) submission
    ->and($output)->toContain('Ballot Code: FINALIZE-002')
        ->and($output)->toContain('Votes: 0');
});

test('election:finalize-ballot handles exceptions cleanly', function () {
    // You can simulate an error by mocking FinalizeBallot to throw
    $mock = \Mockery::mock(FinalizeBallot::class);
    $mock->shouldReceive('run')
        ->with('FAULTY-001')
        ->andThrow(new \RuntimeException('Simulated error'));

    app()->instance(FinalizeBallot::class, $mock);

    $exitCode = Artisan::call('election:finalize-ballot', [
        'ballot_code' => 'FAULTY-001',
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(1)
        ->and($output)->toContain('❌ Error finalizing ballot')
//        ->and($output)->toContain('Simulated error')
    ;
});
