<?php

use TruthElection\Data\{CandidateData, ElectoralInspectorData, PositionData, PrecinctData, VoteData};
use TruthElection\Support\{ElectionStoreInterface, ParseCompactBallotFormat, PrecinctContext};
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;

beforeEach(function () {
    /** @var ElectionStoreInterface $store */

    $store = app(ElectionStoreInterface::class);
    $store->reset();

    $precinctCode = 'CURRIMAO-001';

    $precinctData = new PrecinctData(
        code: $precinctCode,
        location_name: 'Currimao National High School',
        latitude: 17.993217,
        longitude: 120.488902,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, []),
        watchers_count: 0,
        precincts_count: 0,
        registered_voters_count: 0,
        actual_voters_count: 0,
        ballots_in_box_count: 0,
        unused_ballots_count: 0,
        spoiled_ballots_count: 0,
        void_ballots_count: 0,
    );
    $store->putPrecinct($precinctData);
//    $precinctContext = app(PrecinctContext::class);
    $precinctContext = new PrecinctContext($store, 'CURRIMAO-001');

    // Register basic positions
    $store->setPositions([
        'PRESIDENT' => new PositionData(
            code: 'PRESIDENT',
            name: 'President',
            level: Level::NATIONAL,
            count: 1
        ),
        'VICE-PRESIDENT' => new PositionData(
            code: 'VICE-PRESIDENT',
            name: 'Vice President',
            level: Level::NATIONAL,
            count: 1
        ),
        'REPRESENTATIVE-PARTY-LIST' => new PositionData(
            code: 'REPRESENTATIVE-PARTY-LIST',
            name: 'Party List Representative',
            level: Level::NATIONAL,
            count: 1
        ),
    ]);

    // Register basic candidates
    $store->setCandidates([
        'LD_001' => new CandidateData(
            code: 'LD_001',
            name: 'Leonardo DiCaprio',
            alias: 'LD',
            position: $store->getPosition('PRESIDENT')
        ),
        'TH_001' => new CandidateData(
            code: 'TH_001',
            name: 'Tom Hanks',
            alias: 'TH',
            position: $store->getPosition('VICE-PRESIDENT')
        ),
        'THE_MATRIX_008' => new CandidateData(
            code: 'THE_MATRIX_008',
            name: 'The Matrix',
            alias: 'TM',
            position: $store->getPosition('REPRESENTATIVE-PARTY-LIST')
        ),
    ]);

    $this->store = $store;

    $this->parser = new ParseCompactBallotFormat($store, $precinctContext);
});

it('parses compact format into expected JSON string', function () {
    $compact = 'BAL001|PRESIDENT:LD_001;VICE-PRESIDENT:TH_001';
    $precinct = 'CURRIMAO-001';

    $json = ($this->parser)($compact);

    $data = json_decode($json, true);

    $votes = new DataCollection(VoteData::class, [
        [
            'position' => [
                'code' => 'PRESIDENT',
                'name' => 'President',
                'level' => 'national',
                'count' => 1,
            ],
            'candidates' => [
                [
                    'code' => 'LD_001',
                    'name' => 'Leonardo DiCaprio',
                    'alias' => 'LD',
                    'position' => [
                        'code' => 'PRESIDENT',
                        'name' => 'President',
                        'level' => 'national',
                        'count' => 1,
                    ]
                ]
            ],
        ],
        [
            'position' => [
                'code' => 'VICE-PRESIDENT',
                'name' => 'Vice President',
                'level' => 'national',
                'count' => 1,
            ],
            'candidates' => [
                [
                    'code' => 'TH_001',
                    'name' => 'Tom Hanks',
                    'alias' => 'TH',
                    'position' => [
                        'code' => 'VICE-PRESIDENT',
                        'name' => 'Vice President',
                        'level' => 'national',
                        'count' => 1,
                    ]
                ]
            ],
        ]
    ]);
    expect($data)->toMatchArray([
        'ballot_code' => 'BAL001',
        'precinct_code' => 'CURRIMAO-001',
        'votes' => $votes->toArray()
    ]);
});

it('throws when position code is unknown', function () {
    $this->parser->__invoke('BAL002|SENATOR:XX_001', 'CURRIMAO-001');
})->throws(RuntimeException::class, 'Unknown position code: SENATOR');

it('throws when candidate code is unknown', function () {
    $this->parser->__invoke('BAL003|PRESIDENT:UNKNOWN_999', 'CURRIMAO-001');
})->throws(RuntimeException::class, 'Unknown candidate code: UNKNOWN_999');

it('parses positions with multiple candidates', function () {
    /** @var ElectionStoreInterface $store */
    $store = $this->store;

    // 0. Setup Precinct
    $precinctCode = 'CURRIMAO-001';
    $precinctData = new PrecinctData(
        code: $precinctCode,
        location_name: 'Currimao National High School',
        latitude: 17.993217,
        longitude: 120.488902,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, []),
        watchers_count: 0,
        precincts_count: 0,
        registered_voters_count: 0,
        actual_voters_count: 0,
        ballots_in_box_count: 0,
        unused_ballots_count: 0,
        spoiled_ballots_count: 0,
        void_ballots_count: 0,
    );
    $store->putPrecinct($precinctData);
    $precinctContext = new PrecinctContext($store, 'CURRIMAO-001');

    // 1. Setup Positions
    $multiVotePositions = [
        'SENATOR' => 12,
        'BOARD-MEMBER-ILN' => 2,
        'COUNCILOR-ILN-CURRIMAO' => 8,
        'GOVERNOR-ILN' => 1,
        'VICE-GOVERNOR-ILN' => 1,
        'REPRESENTATIVE-ILN-1' => 1,
        'MAYOR-ILN-CURRIMAO' => 1,
        'VICE-MAYOR-ILN-CURRIMAO' => 1,
    ];

    foreach ($multiVotePositions as $code => $count) {
        $store->setPositions(array_merge(
            $store->allPositions(),
            [
                $code => new PositionData(
                    code: $code,
                    name: str_replace('-', ' ', ucwords(strtolower($code), '-')),
                    level: Level::LOCAL,
                    count: $count
                )
            ]
        ));
    }

    // 2. Setup Candidates
    $candidates = [
        'AJ_006' => 'PRESIDENT',
        'TH_001' => 'VICE-PRESIDENT',
        'THE_MATRIX_008' => 'REPRESENTATIVE-PARTY-LIST',
        'EN_001' => 'GOVERNOR-ILN',
        'MF_002' => 'VICE-GOVERNOR-ILN',
        'JF_001' => 'REPRESENTATIVE-ILN-1',
        'EW_003' => 'MAYOR-ILN-CURRIMAO',
        'JKS_001' => 'VICE-MAYOR-ILN-CURRIMAO',
        'DP_004' => 'BOARD-MEMBER-ILN',
        'BDT_005' => 'BOARD-MEMBER-ILN',
        'ER_001' => 'COUNCILOR-ILN-CURRIMAO',
        'SG_002' => 'COUNCILOR-ILN-CURRIMAO',
        'SR_003' => 'COUNCILOR-ILN-CURRIMAO',
        'MC_004' => 'COUNCILOR-ILN-CURRIMAO',
        'MS_005' => 'COUNCILOR-ILN-CURRIMAO',
        'CE_006' => 'COUNCILOR-ILN-CURRIMAO',
        'GMR_007' => 'COUNCILOR-ILN-CURRIMAO',
        'DO_008' => 'COUNCILOR-ILN-CURRIMAO',
        // 12 Senators
        'ES_002' => 'SENATOR',
        'LN_048' => 'SENATOR',
        'AA_018' => 'SENATOR',
        'GG_016' => 'SENATOR',
        'BC_015' => 'SENATOR',
        'MD_009' => 'SENATOR',
        'WS_007' => 'SENATOR',
        'MA_035' => 'SENATOR',
        'SB_006' => 'SENATOR',
        'FP_038' => 'SENATOR',
        'OS_028' => 'SENATOR',
        'MF_003' => 'SENATOR',
    ];

    foreach ($candidates as $code => $positionCode) {
        $position = $store->getPosition($positionCode);

        if (! $position) {
            $position = new PositionData(
                code: $positionCode,
                name: $positionCode,
                level: Level::LOCAL,
                count: 1
            );

            $store->setPositions(array_merge(
                $store->allPositions(),
                [$positionCode => $position]
            ));
        }

        $store->setCandidates(array_merge(
            $store->allCandidates(),
            [
                $code => new CandidateData(
                    code: $code,
                    name: 'Candidate ' . $code,
                    alias: substr($code, 0, 3),
                    position: $position
                )
            ]
        ));
    }

    // 3. Parse
    $parser = new ParseCompactBallotFormat($store, $precinctContext);

    $compact = "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048,AA_018,GG_016,BC_015,MD_009,WS_007,MA_035,SB_006,FP_038,OS_028,MF_003;REPRESENTATIVE-PARTY-LIST:THE_MATRIX_008;GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:MF_002;BOARD-MEMBER-ILN:DP_004,BDT_005;REPRESENTATIVE-ILN-1:JF_001;MAYOR-ILN-CURRIMAO:EW_003;VICE-MAYOR-ILN-CURRIMAO:JKS_001;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008";

    $json = $parser($compact);
    $parsed = json_decode($json, true);

    expect($parsed)->toHaveKeys(['ballot_code', 'precinct_code', 'votes']);
    expect($parsed['ballot_code'])->toBe('BAL-001');
    expect($parsed['precinct_code'])->toBe('CURRIMAO-001');

    // Validate candidate counts for multi-vote positions
    $senatorVote = collect($parsed['votes'])->firstWhere('position.code', 'SENATOR');
    expect($senatorVote['candidates'])->toHaveCount(12);

    $councilorVote = collect($parsed['votes'])->firstWhere('position.code', 'COUNCILOR-ILN-CURRIMAO');
    expect($councilorVote['candidates'])->toHaveCount(8);
});
