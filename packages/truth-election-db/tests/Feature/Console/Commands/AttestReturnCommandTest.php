<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Support\ElectionStoreInterface;
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElectionDb\Actions\AttestReturn;
use Illuminate\Support\Facades\Artisan;
use TruthElection\Data\SignPayloadData;
use Illuminate\Support\Facades\File;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    File::ensureDirectoryExists(base_path('config'));

    $electionSource = realpath(__DIR__ . '/../../../../config/election.json');
    $precinctSource = realpath(__DIR__ . '/../../../../config/precinct.yaml');

    expect($electionSource)->not->toBeFalse("Missing election.json fixture");
    expect($precinctSource)->not->toBeFalse("Missing precinct.yaml fixture");

    File::copy($electionSource, base_path('config/election.json'));
    File::copy($precinctSource, base_path('config/precinct.yaml'));

    $this->artisan('election:setup-precinct')->assertExitCode(0);

    $this->artisan('election:cast-ballot', [
        '--json' => '{"ballot_code":"BAL001","precinct_code":"CURRIMAO-001","votes":[{"position":{"code":"PRESIDENT","name":"President","level":"national","count":1},"candidates":[{"code":"LD_001","name":"Leonardo DiCaprio","alias":"LD","position":{"code":"PRESIDENT","name":"President","level":"national","count":1}}]}]}'
    ])->assertExitCode(0);

    $this->artisan('election:tally-votes');
});

test('artisan election:attest-return successfully signs multiple inspectors and persists in DB', function () {
    $er = app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001');

    // ➤ Sign chairperson
    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-juan:signature123',
    ])
        ->expectsOutputToContain('✅ Signature saved successfully:')
        ->expectsOutputToContain('🧑 Inspector: Juan dela Cruz (chairperson)')
        ->expectsOutputToContain("🗳 Election Return: $er->code")
        ->assertExitCode(0);

    // ➤ Sign member
    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-maria:signature456',
    ])
        ->expectsOutputToContain('✅ Signature saved successfully:')
        ->expectsOutputToContain('🧑 Inspector: Maria Santos (member)')
        ->expectsOutputToContain("🗳 Election Return: $er->code")
        ->assertExitCode(0);

    $updated = \TruthElectionDb\Models\ElectionReturn::where('code', $er->code)->first()?->getData();
    $signed = $updated->signedInspectors();

    // 🔍 Assertions for Juan
    $juan = $updated->findSignatory('uuid-juan');
    expect($juan)->not->toBeNull();
    expect($juan->name)->toBe('Juan dela Cruz');
    expect($juan->signature)->toBe('signature123');
    expect($juan->role->value)->toBe('chairperson');

    // 🔍 Assertions for Maria
    $maria = $updated->findSignatory('uuid-maria');
    expect($maria)->not->toBeNull();
    expect($maria->name)->toBe('Maria Santos');
    expect($maria->signature)->toBe('signature456');
    expect($maria->role->value)->toBe('member');

    // ✅ Check count
    expect($signed->pluck('id'))->toContain('uuid-juan', 'uuid-maria');
});

test('artisan election:attest-return fails with unknown inspector', function () {
    $er = app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001');

    $this->artisan('election:attest-return', [
        'payload' => 'BEI:Z9:invalid',
    ])
        ->expectsOutputToContain('❌ Failed to attest election return: Inspector with ID [Z9] not found.')
        ->assertExitCode(1);
});

test('artisan election:attest fails with non-existent election return', function () {
    $this->artisan('election:attest-return', [
        'election_return_code' => 'NON-EXISTENT-ER',
        'payload' => 'BEI:uuid-juan:signature123',
    ])
        ->expectsOutputToContain('❌ Failed to attest election return: Election return [NON-EXISTENT-ER] not found.')
        ->assertExitCode(1);
})->skip();

test('artisan election:attest-return invokes AttestReturn::run and shows output', function () {
    // Arrange
    $payloadString = 'BEI:uuid-juan:signature123';
    $payload = SignPayloadData::fromQrString($payloadString);

    // Prepare expected return array
    $mockResult = [
        'message' => 'Signature saved successfully.',
        'id' => $payload->id,
        'name' => 'Juan dela Cruz',
        'role' => 'chairperson',
        'signed_at' => now()->toIso8601String(),
        'er' => $er = app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001'),
    ];

    // Mock the AttestReturn action
    $mock = \Mockery::mock(AttestReturn::class);
    $mock->shouldReceive('run')
        ->once()
        ->withArgs(function (SignPayloadData $arg) use ($payload) {
            return $arg->id === $payload->id;
        })
        ->andReturn($mockResult);

    app()->instance(AttestReturn::class, $mock);

    // Act
    $exitCode = Artisan::call('election:attest-return', [
        'payload' => $payloadString,
    ]);

    $output = Artisan::output();

    // Assert
    expect($exitCode)->toBe(0);
    expect($output)->toContain('✅ Signature saved successfully:');
    expect($output)->toContain('Juan dela Cruz');
    expect($output)->toContain('chairperson');
    expect($output)->toContain($er->code);
});
