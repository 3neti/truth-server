<?php

use Spatie\LaravelData\DataCollection;
use TruthElection\Data\{
    CandidateData,
    PositionData,
    PrecinctData,
    BallotData,
    VoteData
};
use TruthElection\Enums\Level;

it('creates a PrecinctData object from nested array', function () {
    $precinct = PrecinctData::from([
        'code' => 'CURRIMAO-001',
        'location_name' => 'Currimao Central School',
        'latitude' => 17.993217,
        'longitude' => 120.488902,
        'electoral_inspectors' => [],
        'watchers_count' => 2,
        'precincts_count' => 10,
        'registered_voters_count' => 250,
        'actual_voters_count' => 200,
        'ballots_in_box_count' => 198,
        'unused_ballots_count' => 52,
        'spoiled_ballots_count' => 3,
        'void_ballots_count' => 1,
        'ballots' => [
            [
                'id' => 'ballot-uuid-1234',
                'code' => 'BALLOT-001',
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
                ],
            ],
        ],
    ]);

    expect($precinct)->toBeInstanceOf(PrecinctData::class)
        ->and($precinct->code)->toBe('CURRIMAO-001')
        ->and($precinct->location_name)->toBe('Currimao Central School')
        ->and($precinct->latitude)->toBe(17.993217)
        ->and($precinct->longitude)->toBe(120.488902)
        ->and($precinct->watchers_count)->toBe(2)
        ->and($precinct->precincts_count)->toBe(10)
        ->and($precinct->registered_voters_count)->toBe(250)
        ->and($precinct->actual_voters_count)->toBe(200)
        ->and($precinct->ballots_in_box_count)->toBe(198)
        ->and($precinct->unused_ballots_count)->toBe(52)
        ->and($precinct->spoiled_ballots_count)->toBe(3)
        ->and($precinct->void_ballots_count)->toBe(1)
        ->and($precinct->ballots)->toBeInstanceOf(DataCollection::class)
        ->and($precinct->ballots)->toHaveCount(1);

    $ballot = $precinct->ballots->first();

    expect($ballot)->toBeInstanceOf(BallotData::class)
        ->and($ballot->code)->toBe('BALLOT-001')
        ->and($ballot->votes)->toBeInstanceOf(DataCollection::class)
        ->and($ballot->votes->first())->toBeInstanceOf(VoteData::class)
        ->and($ballot->votes->first()->position)->toBeInstanceOf(PositionData::class)
        ->and($ballot->votes->first()->position->level)->toBe(Level::NATIONAL)
        ->and($ballot->votes->first()->candidates->first())->toBeInstanceOf(CandidateData::class)
        ->and($ballot->votes->first()->candidates->first()->alias)->toBe('BBM');
});

it('supports copyWith updates to ballots only', function () {
    $original = PrecinctData::from([
        'code' => 'LAOAG-001',
        'location_name' => 'Laoag High School',
        'latitude' => 18.2,
        'longitude' => 120.6,
        'electoral_inspectors' => [],
    ]);

    $updatedBallots = new DataCollection(BallotData::class, [
        BallotData::from(['code' => 'B-001', 'votes' => []]),
    ]);

    $updated = $original->copyWith([
        'ballots' => $updatedBallots,
    ]);

    expect($updated)->not->toBe($original)
        ->and($updated->code)->toBe($original->code)
        ->and($updated->ballots)->not->toBeNull()
        ->and($updated->ballots)->toHaveCount(1)
        ->and($updated->ballots->first())->toBeInstanceOf(BallotData::class)
        ->and($updated->ballots->first()->code)->toBe('B-001');
});

it('throws error when required fields are missing', function () {
    expect(fn () => PrecinctData::from([
        'code' => 'NO-LOCATION',
        // Missing location_name, latitude, longitude
        'electoral_inspectors' => [],
    ]))->toThrow(\Spatie\LaravelData\Exceptions\CannotCreateData::class);
});

it('accepts empty ballots collection', function () {
    $precinct = PrecinctData::from([
        'code' => 'EMPTY-PRECINCT',
        'location_name' => 'Vacant Lot',
        'latitude' => 0.0,
        'longitude' => 0.0,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    expect($precinct->ballots)->toBeInstanceOf(DataCollection::class)
        ->and($precinct->ballots)->toHaveCount(0);
});
