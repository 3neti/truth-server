<?php

use TruthElection\Data\{ElectoralInspectorData, PrecinctData, BallotData};
use TruthElectionDb\Database\Factories\BallotFactory;
use TruthElectionDb\Database\Factories\PrecinctFactory;
use Spatie\SchemalessAttributes\SchemalessAttributes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Enums\ElectoralInspectorRole;
use TruthElectionDb\Models\{Precinct, Ballot};
use Spatie\LaravelData\DataCollection;

uses(RefreshDatabase::class);

it('can create a precinct with ballots and map to PrecinctData correctly', function () {
    // Create precinct with inspectors
    $inspectors = collect([
        new ElectoralInspectorData(
            id: 'ei-001',
            name: 'Juan Dela Cruz',
            role: ElectoralInspectorRole::CHAIRPERSON
        ),
        new ElectoralInspectorData(
            id: 'ei-002',
            name: 'Maria Santos',
            role: ElectoralInspectorRole::MEMBER
        ),
    ]);

    $precinct = Precinct::create([
        'code' => 'CURRIMAO-001',
        'location_name' => 'Currimao Central School',
        'latitude' => 17.993217,
        'longitude' => 120.488902,
        'electoral_inspectors' => $inspectors->toArray(),
    ]);

    // Attach ballots
    $ballot1 = Ballot::create([
        'code' => 'BAL-001',
        'precinct_code' => $precinct->code,
        'votes' =>  [
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
    ]);

    $ballot2 = Ballot::create([
        'code' => 'BAL-002',
        'precinct_code' => $precinct->code,
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
    ]);

    // Assert: Precinct model
    expect($precinct)->toBeInstanceOf(Precinct::class)
        ->and($precinct->ballots)->toBeArray()
        ->and($precinct->ballots)->toHaveCount(2)
        ->and($precinct->ballots[0]['code'])->toBe('BAL-001')
    ;

    // Convert to DTO
    $data = $precinct->getData();

    // Assert: DTO
    expect($data)->toBeInstanceOf(PrecinctData::class)
        ->and($data->code)->toBe('CURRIMAO-001')
        ->and($data->location_name)->toBe('Currimao Central School')
        ->and($data->latitude)->toBe(17.993217)
        ->and($data->longitude)->toBe(120.488902)
        ->and($data->electoral_inspectors)->toBeInstanceOf(DataCollection::class)
        ->and($data->electoral_inspectors)->toHaveCount(2)
        ->and($data->electoral_inspectors[0])->toBeInstanceOf(ElectoralInspectorData::class)
        ->and($data->electoral_inspectors[0]->role)->toBe(ElectoralInspectorRole::CHAIRPERSON)
        ->and($data->ballots)->toBeInstanceOf(DataCollection::class)
        ->and($data->ballots)->toHaveCount(2)
        ->and($data->ballots[0])->toBeInstanceOf(BallotData::class)
        ->and($data->ballots[0]->code)->toBe('BAL-001')
    ;

    // Assert: Tally is correctly aggregated
    $tallies = $precinct->tallies;

    expect($tallies)->toBeArray()
        ->and($tallies)->toHaveCount(1)
        ->and($tallies[0]['position_code'])->toBe('PRESIDENT')
        ->and($tallies[0]['candidate_code'])->toBe('uuid-bbm')
        ->and($tallies[0]['candidate_name'])->toBe('Ferdinand Marcos Jr.')
        ->and($tallies[0]['count'])->toBe(2);
});

test('precinct has attributes', function () {
   $precinct = Precinct::factory()->create();
   expect($precinct)->toBeInstanceOf(Precinct::class)
       ->and($precinct->id)->toBeUuid()
       ->and($precinct->code)->toBe('CURRIMAO-001')
       ->and($precinct->location_name)->toBe('Currimao Central School')
       ->and($precinct->latitude)->toBe(17.993217)
       ->and($precinct->longitude)->toBe(120.488902)
       ->and($precinct->electoral_inspectors)->toBeArray()
       ->and($precinct->electoral_inspectors)->toHaveCount(3)
       ->and($precinct->electoral_inspectors)->toMatchArray(PrecinctFactory::electoral_inspectors())
       ->and($precinct->meta)->toBeInstanceOf(SchemalessAttributes ::class)
       ->and($precinct->meta)->toHaveCount(0)
   ;
});

test('precinct has schemaless attributes', function () {
    $precinct = Precinct::factory()->withPrecinctMeta()->create();
    expect($precinct->meta)->toHaveCount(8)
        ->and($precinct->meta->toArray())->toMatchArray(PrecinctFactory::precinct_meta());
});

dataset('precinct', function () {
    return [
        'precinct with ballots' => function () {
            Ballot::factory(2)->forPrecinct(['code' => 'BALLOT-001'])->create();
            return Precinct::where('code', 'BALLOT-001')->first();
        }
    ];
});

test('precinct has ballots and tallies attributes', function (Precinct $precinct) {
    expect($precinct->ballots)->toBeArray();
    expect($precinct->ballots)->toHaveCount(2);
    expect($precinct->tallies)->toBeArray();
})->with('precinct');

test('precinct has dataClass', function (Precinct $precinct) {
    $data = $precinct->getData();
    expect($data)->toBeInstanceOf(PrecinctData::class);
    expect($data->electoral_inspectors)->toBeInstanceOf(DataCollection::class);
    expect($data->electoral_inspectors->toArray())->toMatchArray(PrecinctFactory::electoral_inspectors());
    expect($data->ballots)->toBeInstanceOf(DataCollection::class);
    expect($data->ballots->toArray())->toHaveCount(2);
    expect($data->ballots->toArray()[0]['votes'])->toMatchArray(BallotFactory::votes());
})->with('precinct');

use Illuminate\Support\Str;

it('can persist a precinct and nested ballots from PrecinctData', function () {
    // Arrange: Prepare ElectoralInspectors and Ballots
    $inspectors = collect([
        new ElectoralInspectorData(
            id: 'ei-100',
            name: 'Inspector One',
            role: ElectoralInspectorRole::CHAIRPERSON,
        ),
        new ElectoralInspectorData(
            id: 'ei-101',
            name: 'Inspector Two',
            role: ElectoralInspectorRole::MEMBER,
        ),
    ]);

    $ballots = collect([
        BallotData::from([
            'code' => 'BAL-100',
            'precinct_code' => 'PX-001',
            'votes' => BallotFactory::votes(),
        ]),
        BallotData::from([
            'code' => 'BAL-101',
            'precinct_code' => 'PX-001',
            'votes' => BallotFactory::votes(),
        ]),
    ]);

    $data = new PrecinctData(
        code: 'PX-001',
        location_name: 'Sample Elementary School',
        latitude: 10.123456,
        longitude: 122.654321,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, $inspectors),
        ballots: new DataCollection(BallotData::class, $ballots),
    );

    // Act: Hydrate the Precinct from Data
    $precinct = Precinct::fromData($data);

    // Assert: Model is persisted
    expect($precinct)->toBeInstanceOf(Precinct::class)
        ->and($precinct->exists)->toBeTrue()
        ->and($precinct->ballots)->toHaveCount(2)
        ->and($precinct->ballots[0]['votes'])->toMatchArray(BallotFactory::votes())
        ->and($precinct->tallies)->toBeArray()
        ->and($precinct->tallies[0]['candidate_name'])->toBe('Ferdinand Marcos Jr.')
    ;

    // Assert: Can serialize back to data and match
    $round_trip = $precinct->getData();
    expect($round_trip)->toBeInstanceOf(PrecinctData::class)
        ->and($round_trip->code)->toBe('PX-001')
        ->and($round_trip->ballots)->toHaveCount(2)
        ->and($round_trip->electoral_inspectors)->toHaveCount(2)
        ->and($round_trip->electoral_inspectors[0])->toBeInstanceOf(ElectoralInspectorData::class)
    ;
});
