<?php

use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Data\PrecinctData;
use TruthElection\Data\VoteData;
use TruthElection\Enums\Level;
use TruthElectionDb\Database\Factories\BallotFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Models\{Ballot, Precinct};
use Spatie\LaravelData\DataCollection;
use TruthElection\Data\BallotData;

uses(RefreshDatabase::class);

test('ballot has factory', function () {
    $ballot = Ballot::factory()->create();

    expect($ballot)->toBeInstanceOf(Ballot::class)
        ->and($ballot->code)->toStartWith('BALLOT-')
        ->and($ballot->votes)->toBeArray()
        ->and($ballot->votes)->toHaveCount(2)
        ->and($ballot->votes)->toMatchArray(BallotFactory::votes())
    ;

    $data = $ballot->getData();

    expect($data)->toBeInstanceOf(BallotData::class)
        ->and($data->code)->toBe($ballot->code)
        ->and($data->votes)->toBeInstanceOf(DataCollection::class)
        ->and($data->votes)->toHaveCount(2)
        ->and($data->votes->first()->position->code)->toBe('PRESIDENT');
});

test('ballot has precinct relation', function () {
    $ballot = Ballot::factory()->forPrecinct()->create();
    expect($ballot->precinct)->toBeInstanceOf(Precinct::class);
});

test('ballot has data class', function () {
    $ballot = Ballot::factory()->create();
    $data = $ballot->getData();
    expect($data)->toBeInstanceOf(BallotData::class);
    expect($data->votes)->toBeInstanceOf(DataCollection::class);
    expect($data->votes)->each->toBeInstanceOf(VoteData::class);
    expect($data->votes->toArray())->toMatchArray(BallotFactory::votes());
});

it('persists a ballot and maps to BallotData correctly', function () {
    $code = 'AA-537';

    $votes = [
        [
            'position' => [
                'code' => 'PRESIDENT',
                'name' => 'President of the Philippines',
                'level' => 'national',
                'count' => 1,
            ],
            'candidates' => [
                [
                    'code' => 'uuid-bbm-1234',
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
        [
            'position' => [
                'code' => 'SENATOR',
                'name' => 'Senator of the Philippines',
                'level' => 'national',
                'count' => 12,
            ],
            'candidates' => [
                [
                    'code' => 'uuid-jdc-001',
                    'name' => 'Juan Dela Cruz',
                    'alias' => 'JDC',
                    'position' => [
                        'code' => 'SENATOR',
                        'name' => 'Senator of the Philippines',
                        'level' => 'national',
                        'count' => 12,
                    ],
                ],
                [
                    'code' => 'uuid-mrp-002',
                    'name' => 'Maria Rosario P.',
                    'alias' => 'MRP',
                    'position' => [
                        'code' => 'SENATOR',
                        'name' => 'Senator of the Philippines',
                        'level' => 'national',
                        'count' => 12,
                    ],
                ],
            ],
        ],
    ];

    // Act: Create and retrieve model
    $ballot = Ballot::create(compact('code', 'votes'));

    // Assert: Eloquent model
    expect($ballot)->toBeInstanceOf(Ballot::class)
        ->and($ballot->code)->toBe('AA-537')
        ->and($ballot->votes)->toBeArray()
        ->and($ballot->votes)->toHaveCount(2);

    // First vote group (President)
    $presidentVote = $ballot->votes[0];
    expect($presidentVote['position']['code'])->toBe('PRESIDENT')
        ->and($presidentVote['candidates'][0]['alias'])->toBe('BBM');

    // Second vote group (Senators)
    $senatorVote = $ballot->votes[1];
    expect($senatorVote['position']['count'])->toBe(12)
        ->and($senatorVote['candidates'][1]['name'])->toBe('Maria Rosario P.');

    // Act: Map to Data Object
    $data = $ballot->getData();

    // Assert: DTO
    expect($data)->toBeInstanceOf(BallotData::class)
        ->and($data->code)->toBe('AA-537')
        ->and($data->votes)->toHaveCount(2);

    // Assert: DTO vote positions and candidates
    $presidentialVote = $data->votes[0];
    expect($presidentialVote->position->code)->toBe('PRESIDENT')
        ->and($presidentialVote->candidates)->toHaveCount(1)
        ->and($presidentialVote->candidates[0]->alias)->toBe('BBM');

    $senatorialVote = $data->votes[1];
    expect($senatorialVote->position->code)->toBe('SENATOR')
        ->and($senatorialVote->position->count)->toBe(12)
        ->and($senatorialVote->candidates)->toHaveCount(2)
        ->and($senatorialVote->candidates[1]->name)->toBe('Maria Rosario P.');
});

it('creates or updates a ballot using fromData', function () {
    // Arrange: Create the related precinct
    $precinct = Precinct::factory()->create(['code' => 'P-001']);

    // Arrange: Create position and vote data
    $position = new PositionData(
        code: 'PRESIDENT',
        name: 'President',
        level: Level::NATIONAL,
        count: 1
    );

    $candidate = new CandidateData(
        code: 'uuid-bbm-1234',
        name: 'Ferdinand Marcos Jr.',
        alias: 'BBM',
        position: $position
    );

    $vote = new VoteData(
        candidates: new DataCollection(CandidateData::class, [$candidate]),
    );

    $data = new BallotData(
        code: 'BALLOT-XYZ',
        votes: new DataCollection(VoteData::class, [$vote]),
    );

    $data->setPrecinctCode($precinct->code);

    // Act: Persist the ballot using fromData
    $ballot = Ballot::fromData($data);

    // Assert: Eloquent model fields
    expect($ballot)->toBeInstanceOf(Ballot::class)
        ->and($ballot->code)->toBe('BALLOT-XYZ')
        ->and($ballot->votes)->toBeArray()
        ->and($ballot->votes)->toHaveCount(1)
        ->and($ballot->votes[0]['candidates'][0]['alias'])->toBe('BBM')
        ->and($ballot->precinct_code)->toBe('P-001');

    // Assert: Round-trip DTO from saved model
    $dto = $ballot->getData();

    expect($dto)->toBeInstanceOf(BallotData::class)
        ->and($dto->getPrecinctCode())->toBe('P-001')
        ->and($dto->code)->toBe('BALLOT-XYZ')
        ->and($dto->votes)->toHaveCount(1)
        ->and($dto->votes[0]->position->code)->toBe('PRESIDENT')
        ->and($dto->votes[0]->candidates[0]->alias)->toBe('BBM');
});
