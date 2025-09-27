<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use Illuminate\Support\Facades\Artisan;
use TruthElectionDb\Models\Ballot;
use TruthElection\Data\BallotData;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    $this->ballotCode = 'BALREAD-001';
    $this->markKey = 'A1';

    // Preload required config files and mappings
    $this->artisan('election:setup-precinct')->assertExitCode(0);

    // Manually map mark to candidate in database store
    $store = app(\TruthElectionDb\Support\DatabaseElectionStore::class);
    $store->setMappings([
        'code' => 'CURRIMAO-001',
        'location_name' => 'Currimao, Ilocos Norte',
        'district' => '1',
        'marks' => [
            ['key' => $this->markKey, 'value' => 'LD_001'],
        ]
    ]);
});

test('election:read-vote reads mark and outputs ballot summary', function () {
    $exit = Artisan::call('election:read-vote', [
        'ballot_code' => $this->ballotCode,
        'mark_key' => $this->markKey,
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('✅ Vote successfully read:')
        ->and($output)->toContain("Ballot Code: {$this->ballotCode}")
        ->and($output)->toContain('Leonardo DiCaprio');
});

test('election:read-vote fails if unknown mark key', function () {
    $exit = Artisan::call('election:read-vote', [
        'ballot_code' => 'BALREAD-002',
        'mark_key' => 'UNKNOWN_KEY',
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(1)
        ->and($output)->toContain('❌ Error reading vote')
        ->and($output)->toContain('Mark');
});
