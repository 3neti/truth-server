<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Support\ElectionStoreInterface;
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElection\Data\ElectionReturnData;
use TruthElectionDb\Actions\WrapUpVoting;
use Illuminate\Support\Facades\Artisan;
use TruthElectionDb\Models\Precinct;
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

    $er = app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001');
    $this->er_code = $er->code;

    $jsonPayload = json_encode([
        'watchers_count' => 5,
        'registered_voters_count' => 800,
        'actual_voters_count' => 700,
        'ballots_in_box_count' => 695,
        'unused_ballots_count' => 105,
    ]);

    $this->artisan('election:record-statistics', [
        'payload' => $jsonPayload,
    ]);
});

test('artisan election:wrapup-voting completes and finalizes return', function () {
    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-juan:signature123',
    ]);

    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-maria:signature456',
    ]);

    $this->artisan('election:wrapup-voting', [
        '--disk' => 'local',
        '--payload' => 'minimal',
        '--max_chars' => 1200,
        '--dir' => 'final',
        '--force' => false,
    ])
        ->expectsOutputToContain('✅ Election Return successfully finalized.')
        ->expectsOutputToContain('🗳 Precinct: CURRIMAO-001')
        ->expectsOutputToContain('📦 Saved to:')
        ->assertExitCode(0)
    ;

    $precinct = Precinct::query()->where('code', 'CURRIMAO-001')->first();
    expect($precinct->closed_at)->not->toBeNull();

    $store = app(ElectionStoreInterface::class);
    $er = $store->getElectionReturnByPrecinct('CURRIMAO-001');
    expect($er)->toBeInstanceOf(ElectionReturnData::class);
    expect($er->signedInspectors())->toHaveCount(2);
});

test('artisan election:wrapup-voting throws if already finalized without --force', function () {
    $precinct = Precinct::query()->where('code', 'CURRIMAO-001')->first();
    $precinct->closed_at = now()->toISOString();
    $precinct->save();

    $this->artisan('election:wrapup-voting')
//        ->expectsOutputToContain('Balloting already closed. Nothing to do.')
        ->assertExitCode(1)
    ;
});

test('artisan election:wrapup fails if signatures are incomplete', function () {
    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-juan:signature123',
    ]);

    $this->artisan('election:wrapup-voting', [
        '--force' => false,
    ])
//        ->expectsOutputToContain('Signature validation failed')
        ->assertExitCode(1)
    ;
});

test('artisan election:wrapup-voting invokes WrapUpVoting::run with expected args', function () {
    $json = [
        'id' => 'uuid-er-001',
        'code' => 'ER-001',
        'precinct' => [
            'id' => 'uuid-precinct-001',
            'code' => 'CURRIMAO-001',
            'location_name' => 'Currimao Central School',
            'latitude' => 17.993217,
            'longitude' => 120.488902,
            'electoral_inspectors' => [
                [
                    'id' => 'uuid-ei-001',
                    'name' => 'Juan dela Cruz',
                    'role' => 'chairperson',
//                    'signature' => null,
//                    'signed_at' => null,
                ],
                [
                    'id' => 'uuid-ei-002',
                    'name' => 'Maria Santos',
                    'role' => 'member',
//                    'signature' => null,
//                    'signed_at' => null,
                ],
            ],
            'watchers_count' => 2,
            'precincts_count' => 10,
            'registered_voters_count' => 250,
            'actual_voters_count' => 200,
            'ballots_in_box_count' => 198,
            'unused_ballots_count' => 52,
            'spoiled_ballots_count' => 3,
            'void_ballots_count' => 1,
        ],
        'tallies' => [
            [
                'position_code' => 'PRESIDENT',
                'candidate_code' => 'uuid-bbm',
                'candidate_name' => 'Ferdinand Marcos Jr.',
                'count' => 300,
            ],
            [
                'position_code' => 'SENATOR',
                'candidate_code' => 'uuid-jdc',
                'candidate_name' => 'Juan Dela Cruz',
                'count' => 280,
            ],
        ],
        'signatures' => [
            [
                'id' => 'uuid-ei-001',
                'name' => 'Juan dela Cruz',
                'role' => 'chairperson',
                'signature' => 'base64-image-data',
                'signed_at' => '2025-08-07T12:00:00+08:00',
            ],
            [
                'id' => 'uuid-ei-002',
                'name' => 'Maria Santos',
                'role' => 'member',
                'signature' => 'base64-image-data',
                'signed_at' => '2025-08-07T12:05:00+08:00',
            ],
        ],
        'ballots' => [
            [
                'id' => 'uuid-ballot-001',
                'code' => 'BAL-001',
                'precinct' => [
                    'id' => 'uuid-precinct-001',
                    'code' => 'CURRIMAO-001',
                    'location_name' => 'Currimao Central School',
                    'latitude' => 17.993217,
                    'longitude' => 120.488902,
                    'electoral_inspectors' => [
                        [
                            'id' => 'uuid-ei-001',
                            'name' => 'Juan dela Cruz',
                            'role' => 'chairperson',
//                    'signature' => null,
//                    'signed_at' => null,
                        ],
                        [
                            'id' => 'uuid-ei-002',
                            'name' => 'Maria Santos',
                            'role' => 'member',
//                    'signature' => null,
//                    'signed_at' => null,
                        ],
                    ],
                ],
                'votes' => [
                    [
                        'position' => [
                            'code' => 'PRESIDENT',
                            'name' => 'President of the Philippines',
                            'level' => 'national',
                            'count' => 1,
                        ],
                        'candidates' => [
                            [
                                'code' => 'uuid-bbm',
                                'name' => 'Ferdinand Marcos Jr.',
                                'alias' => 'BBM',
                                'position' => [
                                    'code' => 'PRESIDENT',
                                    'name' => 'President of the Philippines',
                                    'level' => 'national',
                                    'count' => 1,
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
        'created_at' => '2025-08-07T12:00:00+08:00',
        'updated_at' => '2025-08-07T12:10:00+08:00',
    ];
    $mockReturn = ElectionReturnData::from($json);

    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-juan:signature123',
    ]);

    $this->artisan('election:attest-return', [
        'payload' => 'BEI:uuid-maria:signature456',
    ]);

    $mock = \Mockery::mock(WrapUpVoting::class);
    $mock->shouldReceive('handle')
        ->once()
        ->withArgs(function (...$args) {
            [$disk, $payload, $maxChars, $dir, $force] = $args;

            return $disk === 'local'
                && $payload === 'minimal'
                && $maxChars === 1200
                && $dir === 'final'
                && $force === false;
        })
        ->andReturn($mockReturn);

    app()->instance(WrapUpVoting::class, $mock);

    $exitCode = Artisan::call('election:wrapup-voting', [
        '--disk' => 'local',
        '--payload' => 'minimal',
        '--max_chars' => 1200,
        '--dir' => 'final',
        '--force' => false,
    ]);

    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('✅ Election Return successfully finalized.');
    expect($output)->toContain('🗳 Precinct: CURRIMAO-001');
    expect($output)->toContain('📦 Saved to: ER-ER-001/final (local)');
});
