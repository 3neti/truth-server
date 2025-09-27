<?php

use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Support\ElectionReturnContext;
use TruthElection\Data\ElectionReturnData;
use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

dataset('er', function () {
    return [
        fn() => ElectionReturnData::from([
            'id' => 'uuid-er-001',
            'code' => 'CURRIMAO-001-ER',
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
        ])
    ];
});

it('can resolve the election return and expose attributes', function ($mockReturn) {
    $mockStore = $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getElectionReturn')
        ->with('CURRIMAO-001-ER')
        ->andReturn($mockReturn)
        ->getMock();

    $context = new ElectionReturnContext($mockStore, 'CURRIMAO-001-ER');

    expect($context->code())->toBe('CURRIMAO-001-ER');
    expect($context->getElectionReturn())->toBeInstanceOf(ElectionReturnData::class);
    expect($context->precinct->code)->toBe('CURRIMAO-001');
})->with('er');

it('resolves ElectionReturnContext via container using request input', function ($mockReturn) {
    Route::get('/test-election-return-context', function (Request $request) {
        return app(ElectionReturnContext::class)->code();
    });

    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getElectionReturn')
        ->andReturn($mockReturn);

    $this->get("/test-election-return-context")
        ->assertOk()
        ->assertSee('CURRIMAO-001-ER');
})->with('er');
