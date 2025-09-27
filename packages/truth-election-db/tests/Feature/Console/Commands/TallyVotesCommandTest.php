<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElection\Data\ElectionReturnData;
use Illuminate\Support\Facades\Artisan;
use TruthElectionDb\Actions\TallyVotes;
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

    // Cast a valid ballot
    $this->artisan('election:cast-ballot', [
        '--json' => '{"ballot_code":"BAL001","votes":[{"position":{"code":"PRESIDENT","name":"President","level":"national","count":1},"candidates":[{"code":"LD_001","name":"Leonardo DiCaprio","alias":"LD","position":{"code":"PRESIDENT","name":"President","level":"national","count":1}}]}]}',
    ])->assertExitCode(0);
});

test('artisan election:tally-votes shows expected vote tally for precinct', function () {
    $this->artisan('election:tally-votes')
        ->expectsOutputToContain('✅ Tally complete:')
        ->expectsOutputToContain('Precinct: CURRIMAO-001')
        ->expectsOutputToContain('Position: President')
        ->expectsOutputToContain('Leonardo DiCaprio (1 vote)')
        ->assertExitCode(0)
    ;
});

//test('artisan election:tally-votes fails when precinct code does not exist', function () {
//    $this->artisan('election:tally', [
//        'precinct_code' => 'NONEXISTENT-999',
//    ])
//        ->expectsOutputToContain('❌ Error generating tally:')
//        ->assertExitCode(1);
//})->skip();

test('artisan election:tally-votes invokes TallyVotes::run and shows output', function () {
    // Given
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

    // 🧪 Mock TallyVotes
    $mock = \Mockery::mock(TallyVotes::class);
    $mock->shouldReceive('run')
        ->once()
        ->andReturn($mockReturn);

    $this->app->instance(TallyVotes::class, $mock);

    // When
    $exitCode = Artisan::call('election:tally-votes');

    // Then
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('✅ Tally complete:');
    expect($output)->toContain('Precinct: CURRIMAO-001');
    expect($output)->toContain('Ferdinand Marcos Jr. (1 vote)');
});

test('artisan election:tally with optional electionReturnCode invokes TallyVotes::run correctly', function () {
    // Given
    $electionReturnCode = 'ER-XYZ';

    $mockReturn = ElectionReturnData::from([
        'id' => 'uuid-er-001',
        'code' => $electionReturnCode,
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
    ]);

    // 🧪 Mock TallyVotes
    $mock = \Mockery::mock(TallyVotes::class);
    $mock->shouldReceive('run')
        ->once()
        ->with($electionReturnCode)
        ->andReturn($mockReturn);

    $this->app->instance(TallyVotes::class, $mock);

    // When
    $exitCode = Artisan::call('election:tally-votes', [
        'election_return_code' => $electionReturnCode,
    ]);

    // Then
    $output = Artisan::output();

    expect($exitCode)->toBe(0);
    expect($output)->toContain('✅ Tally complete:');
    expect($output)->toContain('Precinct: CURRIMAO-001');
    expect($output)->toContain('Ferdinand Marcos Jr. (1 vote)');
});
