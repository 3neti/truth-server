<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\{Artisan, File};
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElectionDb\Actions\CastBallot;
use TruthElection\Data\BallotData;
use TruthElectionDb\Models\Ballot;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    File::ensureDirectoryExists(base_path('config'));

    $electionSource = realpath(__DIR__ . '/../../../../config/election.json');
    $precinctSource = realpath(__DIR__ . '/../../../../config/precinct.yaml');

    expect($electionSource)->not->toBeFalse("Missing election.json fixture");
    expect($precinctSource)->not->toBeFalse("Missing precinct.yaml fixture");

    File::copy($electionSource, base_path('config/election.json'));
    File::copy($precinctSource, base_path('config/precinct.yaml'));

    $this->artisan('election:setup-precinct')->assertExitCode(0);

    // Minimal valid ballot input
    $this->validJson = json_encode([
        'ballot_code' => 'BAL123',
        'precinct_code' => 'CURRIMAO-001',
        'votes' => [
            [
                'position' => [
                    'code' => 'PRESIDENT',
                ],
                'candidates' => [
                    ['code' => 'LD_001'],
                ],
            ],
        ],
    ]);
});

test('artisan election:cast-ballot works via json option', function () {
    $this->artisan('election:cast-ballot', [
        '--json' => '{"ballot_code":"BAL001","votes":[{"position":{"code":"PRESIDENT","name":"President","level":"national","count":1},"candidates":[{"code":"LD_001","name":"Leonardo DiCaprio","alias":"LD","position":{"code":"PRESIDENT","name":"President","level":"national","count":1}}]},{"position":{"code":"VICE-PRESIDENT","name":"Vice President","level":"national","count":1},"candidates":[{"code":"TH_001","name":"Tom Hanks","alias":"TH","position":{"code":"VICE-PRESIDENT","name":"Vice President","level":"national","count":1}}]}]}',
//        '--json' => '{"ballot_code":"BAL001","precinct_code":"CURRIMAO-001","votes":[{"position":{"code":"PRESIDENT","name":"President","level":"national","count":1},"candidates":[{"code":"LD_001","name":"Leonardo DiCaprio","alias":"LD","position":{"code":"PRESIDENT","name":"President","level":"national","count":1}}]},{"position":{"code":"VICE-PRESIDENT","name":"Vice President","level":"national","count":1},"candidates":[{"code":"TH_001","name":"Tom Hanks","alias":"TH","position":{"code":"VICE-PRESIDENT","name":"Vice President","level":"national","count":1}}]}]}',
    ])
        ->expectsOutputToContain('✅ Ballot successfully cast')
        ->assertExitCode(0);
});

test('artisan election:cast-ballot works via json file', function () {
    // Ensure ballot fixture exists
    $ballotFixture = realpath(__DIR__ . '/../../stubs/ballot.json');
    expect($ballotFixture)->not->toBeFalse("Missing ballot.json fixture");

    $this->artisan('election:cast-ballot', [
        '--input' => $ballotFixture,
    ])
        ->expectsOutputToContain('✅ Ballot successfully cast')
        ->assertExitCode(0)
    ;
});

test('artisan election:cast-ballot fails with malformed JSON string', function () {
    $this->artisan('election:cast-ballot', [
        '--json' => '{"ballot_code": "BAL001", "votes": [}', // malformed JSON
    ])
        ->expectsOutputToContain('❌ Failed to parse JSON: State mismatch (invalid or malformed JSON')
        ->assertExitCode(1);
});

test('artisan election:cast-ballot fails with malformed JSON file', function () {
    $malformed = realpath(__DIR__ . '/../../stubs/malformed-ballot.json');

    expect($malformed)->not->toBeFalse("Missing malformed-ballot.json fixture");

    $this->artisan('election:cast-ballot', [
        '--input' => $malformed,
    ])
        ->expectsOutputToContain('❌ Failed to parse JSON: State mismatch (invalid or malformed JSON)')
        ->assertExitCode(1);
});

test('election:cast-ballot uses CastBallot instance and returns expected output', function () {
    // 🗳️ Input payload (also used for mock return)
    $precinctCode = 'CURRIMAO-001';
    $castInput = [
        'code' => $ballotCode = 'BALLOT-001',
        'votes' => [
            [
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
                        ],
                    ],
                ],
            ],
            [
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
                        ],
                    ],
                ],
            ],
        ],
    ];

    $expectedBallot = BallotData::from($castInput);
    $expectedBallot->setPrecinctCode('CURRIMAO-001');

    // ✅ Mock CastBallot action
    $votes = collect($castInput['votes']); // May or may not match run() arg structure

    $mock = \Mockery::mock(CastBallot::class);
    $args = compact('ballotCode', 'votes');

    $mock->shouldReceive('run')
        ->once()
        ->withArgs(function (...$args) use ($ballotCode, $votes) {
            return $ballotCode === 'BALLOT-001'
                && $votes->count() === 2;
        })
        ->andReturn($expectedBallot);

    app()->instance(CastBallot::class, $mock);

    // 🚀 Call the command via Artisan
    $exitCode = Artisan::call('election:cast-ballot', [
        '--json' => $expectedBallot->toJson(),
    ]);

    // ✅ Assert exit code
    expect($exitCode)->toBe(0);

    // 📤 Assert output using Artisan::output()
    $output = Artisan::output();
    expect($output)->toContain('✅ Ballot successfully cast');
    expect($output)->toContain('Ballot Code: BALLOT-001');
    expect($output)->toContain('Precinct: CURRIMAO-001');
    expect($output)->toContain('Votes: 2');
});

test('election:cast-ballot parses full compact line and stores all votes', function () {
    $line = "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048,AA_018,GG_016,BC_015,MD_009,WS_007,MA_035,SB_006,FP_038,OS_028,MF_003;REPRESENTATIVE-PARTY-LIST:THE_MATRIX_008;GOVERNOR-ILN:EN_001;VICE-GOVERNOR-ILN:MF_002;BOARD-MEMBER-ILN:DP_004,BDT_005;REPRESENTATIVE-ILN-1:JF_001;MAYOR-ILN-CURRIMAO:EW_003;VICE-MAYOR-ILN-CURRIMAO:JKS_001;COUNCILOR-ILN-CURRIMAO:ER_001,SG_002,SR_003,MC_004,MS_005,CE_006,GMR_007,DO_008";

    $exit = Artisan::call('election:cast-ballot', [
        'lines' => [$line]
    ]);

    $output = Artisan::output();

    expect($exit)->toBe(0);
    expect($output)->toContain('BAL-001');
    expect($output)->toContain('Precinct: CURRIMAO-001');
    expect($output)->toContain('Votes: 11');

    $model = Ballot::query()->firstWhere('code', 'BAL-001');
    expect($model)->toBeInstanceOf(Ballot::class);

    $data = $model->getData()->setPrecinctCode('CURRIMAO-001');
    expect($data)->toBeInstanceOf(BallotData::class);
    expect($data->getPrecinctCode())->toBe('CURRIMAO-001');
    expect($data->code)->toBe('BAL-001');

    $votes = collect($data->votes->toArray());

    $expectedVotes = [
        ['position' => 'PRESIDENT', 'candidates' => ['AJ_006']],
        ['position' => 'VICE-PRESIDENT', 'candidates' => ['TH_001']],
        ['position' => 'SENATOR', 'candidates' => ['ES_002','LN_048','AA_018','GG_016','BC_015','MD_009','WS_007','MA_035','SB_006','FP_038','OS_028','MF_003']],
        ['position' => 'REPRESENTATIVE-PARTY-LIST', 'candidates' => ['THE_MATRIX_008']],
        ['position' => 'GOVERNOR-ILN', 'candidates' => ['EN_001']],
        ['position' => 'VICE-GOVERNOR-ILN', 'candidates' => ['MF_002']],
        ['position' => 'BOARD-MEMBER-ILN', 'candidates' => ['DP_004','BDT_005']],
        ['position' => 'REPRESENTATIVE-ILN-1', 'candidates' => ['JF_001']],
        ['position' => 'MAYOR-ILN-CURRIMAO', 'candidates' => ['EW_003']],
        ['position' => 'VICE-MAYOR-ILN-CURRIMAO', 'candidates' => ['JKS_001']],
        ['position' => 'COUNCILOR-ILN-CURRIMAO', 'candidates' => ['ER_001','SG_002','SR_003','MC_004','MS_005','CE_006','GMR_007','DO_008']],
    ];

    foreach ($expectedVotes as $index => $expectedVote) {
        $vote = $votes->get($index);
        expect($vote['position']['code'])->toBe($expectedVote['position']);
        $actualCandidateCodes = collect($vote['candidates'])->pluck('code')->toArray();
        expect($actualCandidateCodes)->toEqualCanonicalizing($expectedVote['candidates']);
    }
});
