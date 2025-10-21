<?php

use TruthElection\Data\{
    ElectionReturnData,
    ElectoralInspectorData,
    VoteCountData,
    PrecinctData,
    ERData,
    ERVoteCountData,
    ERElectoralInspectorData
};
use TruthElection\Enums\ElectoralInspectorRole;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\DataCollection;

it('hydrates ElectionReturnData from JSON and exports to array', function () {
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

    $dto = ElectionReturnData::from($json);

    expect($dto)->toBeInstanceOf(ElectionReturnData::class)
        ->and($dto->code)->toBe('ER-001')
        ->and($dto->precinct)->toBeInstanceOf(PrecinctData::class)
        ->and($dto->signatures)->toBeInstanceOf(DataCollection::class)
        ->and($dto->signatures)->toHaveCount(2)
        ->and($dto->signatures[0])->toBeInstanceOf(ElectoralInspectorData::class)
        ->and($dto->signatures[0]->role)->toBe(ElectoralInspectorRole::CHAIRPERSON)
        ->and($dto->created_at)->toBeInstanceOf(Carbon::class)
        ->and($dto->updated_at)->toBeInstanceOf(Carbon::class);

    $array = $dto->toArray();

    expect($array)->toHaveKeys([
        'id',
        'code',
        'precinct',
        'tallies',
        'signatures',
        'ballots',
        'created_at',
        'updated_at',
    ])
        ->and($array['precinct'])->toHaveKeys([
            'code',
            'location_name',
            'latitude',
            'longitude',
            'electoral_inspectors',
            'watchers_count',
            'precincts_count',
            'registered_voters_count',
            'actual_voters_count',
            'ballots_in_box_count',
            'unused_ballots_count',
            'spoiled_ballots_count',
            'void_ballots_count',
    ]);

});

it('can create ElectionReturnData from ERData', function () {
    // Create sample ERData with minified structure
    $tallies = new DataCollection(ERVoteCountData::class, [
        new ERVoteCountData('AJ_006', 150),  // Angelina Jolie - PRESIDENT
        new ERVoteCountData('SJ_002', 120),  // Scarlett Johansson - PRESIDENT 
        new ERVoteCountData('TH_001', 200),  // Tom Hanks - VICE-PRESIDENT
        new ERVoteCountData('ES_002', 180),  // Emma Stone - SENATOR
    ]);
    
    $signatures = new DataCollection(ERElectoralInspectorData::class, [
        new ERElectoralInspectorData('uuid-juan', 'signature123', Carbon::now()),
        new ERElectoralInspectorData('uuid-maria', 'signature456', Carbon::now()),
    ]);
    
    $erData = new ERData(
        id: 'test-er-001',
        code: 'ER-2024-TEST',
        tallies: $tallies,
        signatures: $signatures,
        created_at: Carbon::now(),
        updated_at: Carbon::now(),
    );
    
    // Convert ERData to full ElectionReturnData
    $fullElectionReturn = ElectionReturnData::fromERData($erData);
    
    // Test basic structure
    expect($fullElectionReturn)->toBeInstanceOf(ElectionReturnData::class)
        ->and($fullElectionReturn->id)->toBe('test-er-001')
        ->and($fullElectionReturn->code)->toBe('ER-2024-TEST')
        ->and($fullElectionReturn->precinct)->toBeInstanceOf(PrecinctData::class)
        ->and($fullElectionReturn->tallies)->toBeInstanceOf(DataCollection::class)
        ->and($fullElectionReturn->signatures)->toBeInstanceOf(DataCollection::class)
        ->and($fullElectionReturn->ballots)->toBeInstanceOf(DataCollection::class);
    
    // Test precinct data was populated from config
    expect($fullElectionReturn->precinct->code)->toBe('CURRIMAO-001')
        ->and($fullElectionReturn->precinct->location_name)->toBe('Currimao National High School')
        ->and($fullElectionReturn->precinct->electoral_inspectors)->toHaveCount(3);
    
    // Test that tallies were expanded with position codes and candidate names
    expect($fullElectionReturn->tallies)->toHaveCount(4);
    
    $angelinaTally = $fullElectionReturn->tallies->toCollection()->firstWhere('candidate_code', 'AJ_006');
    expect($angelinaTally)->not->toBeNull()
        ->and($angelinaTally->position_code)->toBe('PRESIDENT')
        ->and($angelinaTally->candidate_name)->toBe('Angelina Jolie')
        ->and($angelinaTally->count)->toBe(150);
    
    $tomTally = $fullElectionReturn->tallies->toCollection()->firstWhere('candidate_code', 'TH_001');
    expect($tomTally)->not->toBeNull()
        ->and($tomTally->position_code)->toBe('VICE-PRESIDENT')
        ->and($tomTally->candidate_name)->toBe('Tom Hanks')
        ->and($tomTally->count)->toBe(200);
    
    // Test that signatures were expanded with names and roles
    expect($fullElectionReturn->signatures)->toHaveCount(2);
    
    $juanSignature = $fullElectionReturn->signatures->toCollection()->firstWhere('id', 'uuid-juan');
    expect($juanSignature)->not->toBeNull()
        ->and($juanSignature->name)->toBe('Juan dela Cruz')
        ->and($juanSignature->role)->toBe(ElectoralInspectorRole::CHAIRPERSON)
        ->and($juanSignature->signature)->toBe('signature123');
    
    $mariaSignature = $fullElectionReturn->signatures->toCollection()->firstWhere('id', 'uuid-maria');
    expect($mariaSignature)->not->toBeNull()
        ->and($mariaSignature->name)->toBe('Maria Santos')
        ->and($mariaSignature->role)->toBe(ElectoralInspectorRole::MEMBER)
        ->and($mariaSignature->signature)->toBe('signature456');
    
    // Test that ballots collection is empty as expected
    expect($fullElectionReturn->ballots)->toHaveCount(0);
});
