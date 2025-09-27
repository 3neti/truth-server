<?php

use TruthElection\Data\{BallotData, CandidateData, PositionData, VoteData};
use TruthElectionDb\Actions\{CastBallot, SetupElection};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Collection;
use TruthElectionDb\Models\Ballot;
use TruthElection\Enums\Level;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    File::ensureDirectoryExists(base_path('config'));
    File::copy(realpath(__DIR__ . '/../../../config/election.json'), base_path('config/election.json'));
    File::copy(realpath(__DIR__ . '/../../../config/precinct.yaml'), base_path('config/precinct.yaml'));

    SetupElection::run();
});

dataset('votes', function () {
    return [
        'votes' => collect([
            new VoteData(
                candidates: new DataCollection(CandidateData::class, [
                    new CandidateData(
                        code: 'LD_001',
                        name: 'Leonardo DiCaprio',
                        alias: 'LD',
                        position: new PositionData(
                            code: 'PRESIDENT',
                            name: 'President',
                            level: Level::NATIONAL,
                            count: 1
                        )
                    ),
                ])
            ),
            new VoteData(
                candidates: new DataCollection(CandidateData::class, [
                    new CandidateData(
                        code: 'TH_001',
                        name: 'Tom Hanks',
                        alias: 'TH',
                        position: new PositionData(
                            code: 'VICE-PRESIDENT',
                            name: 'Vice President',
                            level: Level::NATIONAL,
                            count: 1
                        )
                    ),
                ])
            ),
        ])
    ];
});

it('successfully casts a ballot using CastBallot::handle()', function (Collection $votes) {
    $data = CastBallot::run(
        ballotCode: 'BALLOT-123',
        votes: $votes,
    );
    expect($data)->toBeInstanceOf(BallotData::class);

    $ballot = Ballot::query()->where('code', 'BALLOT-123')->first();

    expect($ballot)->toBeInstanceOf(Ballot::class)
        ->and($ballot->code)->toBe('BALLOT-123')
        ->and($ballot->votes)->toHaveCount(2)
        ->and($ballot->votes[0]['position']['code'])->toBe('PRESIDENT')
        ->and($ballot->votes[1]['position']['code'])->toBe('VICE-PRESIDENT');
})->with('votes');

it('casts a ballot successfully via controller', function (Collection $votes) {
    $response = $this->postJson(route('cast.ballot'), [
        'ballot_code' => 'BALLOT-001',
        'votes' => $votes->toArray(),
    ]);

    $response->assertOk()
        ->assertJson([
            'ok' => true,
            'ballot' => [
                'code' => 'BALLOT-001',
            ],
        ]);

    $ballot = Ballot::query()->where('code', 'BALLOT-001')->first();
    expect($ballot)->toBeInstanceOf(Ballot::class);
    expect($ballot->precinct->code)->toBe('CURRIMAO-001');
})->with('votes');

it('validates missing fields', function () {
    $response = $this->postJson(route('cast.ballot'), [
        // no data
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors([
            'ballot_code',
            'votes',
        ]);
});

it('fails when candidate code is missing', function () {
    $response = $this->postJson(route('cast.ballot'), [
        'ballot_code' => 'BALLOT-003',
        'votes' => [
            [
                'position' => ['code' => 'PRESIDENT'],
                'candidates' => [['name' => 'Unnamed']], // missing code
            ],
        ],
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors([
        'votes.0.candidates.0.code',
    ]);
});

it('fails when votes is not an array of objects', function () {
    $response = $this->postJson(route('cast.ballot'), [
        'ballot_code' => 'BALLOT-004',
        'votes' => 'not-an-array',
    ]);

    $response->assertStatus(422)->assertJsonValidationErrors(['votes']);
});

it('rejects duplicate ballot codes for the same precinct', function (Collection $votes) {
    config(['app.debug' => true]);
    // First submission should succeed
    $this->postJson(route('ballot.cast'), [
        'ballot_code' => 'BALLOT-999',
        'votes' => $votes->toArray(),
    ])->assertOk();

    // Second submission: expect exception
    $response = $this->postJson(route('cast.ballot'), [
        'ballot_code' => 'BALLOT-999',
        'votes' => $votes->toArray(),
    ]);

    $response->assertStatus(422);
    $response->assertJsonValidationErrors('ballot_code');
    $response->assertSeeText('Duplicate ballot code [BALLOT-999] for precinct [CURRIMAO-001].');
})->with('votes')->skip();
