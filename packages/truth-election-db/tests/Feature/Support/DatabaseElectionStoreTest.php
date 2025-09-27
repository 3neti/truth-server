<?php

use TruthElection\Enums\{ElectoralInspectorRole, Level};
use TruthElection\Data\{CandidateData, PositionData};
use TruthElectionDb\Database\Factories\BallotFactory;
use TruthElectionDb\Models\Ballot;
use TruthElectionDb\Models\Candidate;
use TruthElectionDb\Models\ElectionReturn;
use TruthElectionDb\Models\Position;
use TruthElectionDb\Support\DatabaseElectionStore;
use TruthElection\Data\{BallotData, PrecinctData};
use TruthElection\Data\{VoteData, VoteCountData};
use TruthElection\Data\ElectoralInspectorData;
use TruthElection\Data\ElectionReturnData;
use Spatie\LaravelData\DataCollection;
use TruthElectionDb\Models\Precinct;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

it('can store and retrieve precincts from database', function () {
    $store = new DatabaseElectionStore();

    $precinct = PrecinctData::from([
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 18.2,
        'longitude' => 120.5,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    $store->putPrecinct($precinct);

    $retrieved = $store->getPrecinct('PRECINCT-01');

    expect($retrieved)->not->toBeNull()
        ->and($retrieved->code)->toBe('PRECINCT-01')
        ->and($retrieved->location_name)->toBe('City Hall');
});

it('can attach and retrieve ballots to a precinct', function () {
    $store = new DatabaseElectionStore();

    $precinct = PrecinctData::from([
        'code' => 'PRECINCT-02',
        'location_name' => 'Barangay Hall',
        'latitude' => 10.5,
        'longitude' => 122.0,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    $store->putPrecinct($precinct);

    foreach (range(1, 3) as $i) {
        $ballot = BallotData::from([
            'code' => "BAL-00$i",
            'votes' => [],
        ]);

        $store->putBallot($ballot, $precinct->code);
    }

    $ballots = $store->getBallotsForPrecinct($precinct->code);

    expect($ballots)->toHaveCount(3)
        ->and($ballots[0]['code'])->toBe('BAL-001')
        ->and($ballots[2]['code'])->toBe('BAL-003');
});

it('can store and retrieve election return (tallies are computed from ballots)', function () {
    $store = new DatabaseElectionStore();

    // 1. Create and store the precinct
    $precinct = PrecinctData::from([
        'code' => 'P-001',
        'location_name' => 'Gymnasium',
        'latitude' => 14.6,
        'longitude' => 121.0,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    $store->putPrecinct($precinct);

    // 2. Create election return data with ballots and votes
    $data = new ElectionReturnData(
        id: 'AA-537',
        code: 'ER-001',
        precinct: PrecinctData::from($precinct->toArray()),
        tallies: new DataCollection(VoteCountData::class, []), // this will be ignored and computed from ballots
        signatures: new DataCollection(ElectoralInspectorData::class, []),
        ballots: new DataCollection(BallotData::class, [
            new BallotData(
                code: 'BALLOT-001',
                votes: new DataCollection(VoteData::class, [
                    new VoteData(
                        candidates: new DataCollection(CandidateData::class, [
                            new CandidateData(
                                code: 'CAND-1',
                                name: 'Candidate One',
                                alias: 'One',
                                position: new PositionData(
                                    code: 'PRESIDENT',
                                    name: 'President',
                                    level: Level::NATIONAL,
                                    count: 1
                                )
                            )
                        ])
                    )
                ])
            ),
            new BallotData(
                code: 'BALLOT-002',
                votes: new DataCollection(VoteData::class, [
                    new VoteData(
                        candidates: new DataCollection(CandidateData::class, [
                            new CandidateData(
                                code: 'CAND-1',
                                name: 'Candidate One',
                                alias: 'One',
                                position: new PositionData(
                                    code: 'PRESIDENT',
                                    name: 'President',
                                    level: Level::NATIONAL,
                                    count: 1
                                )
                            )
                        ])
                    )
                ])
            )
        ]),
        created_at: now(),
        updated_at: now()
    );

    // 3. Store using the store class
    $store->putElectionReturn($data);

    // 4. Retrieve and verify computed tally
    $fetched = $store->getElectionReturn('ER-001');

    expect($fetched)->not->toBeNull()
        ->and($fetched->code)->toBe('ER-001')
        ->and($fetched->ballots)->toHaveCount(2)
        ->and($fetched->tallies)->toHaveCount(1)
        ->and($fetched->tallies[0]->candidate_code)->toBe('CAND-1')
        ->and($fetched->tallies[0]->count)->toBe(2); // 2 ballots voted for CAND-1
});

it('can update election return signatures', function () {
    $store = new DatabaseElectionStore();

    // 1. Prepare inspectors and ballots
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

    // 2. Build PrecinctData and hydrate model
    $data = new PrecinctData(
        code: 'PX-001',
        location_name: 'Sample Elementary School',
        latitude: 10.123456,
        longitude: 122.654321,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, $inspectors),
        ballots: new DataCollection(BallotData::class, $ballots),
    );

    $precinct = Precinct::fromData($data);
    $store->putPrecinct($data);

    // 3. Create election return with no initial signatures
    $er = new ElectionReturnData(
        id: 'ER-002',
        code: 'ER-002',
        precinct: $data,
        tallies: new DataCollection(VoteCountData::class, []),
        signatures: new DataCollection(ElectoralInspectorData::class, []),
        ballots: new DataCollection(BallotData::class, $ballots),
        created_at: now(),
        updated_at: now()
    );

    $store->putElectionReturn($er);

    // 4. Simulate signing by Inspector Two
    $signed = new ElectoralInspectorData(
        id: 'ei-101',
        name: 'Inspector Two',
        role: ElectoralInspectorRole::MEMBER,
        signature: 'data:image/png;base64,FAKESIGN==',
        signed_at: now()
    );

// 5. Manually create updated ElectionReturnData with the new signature
    $updated = new ElectionReturnData(
        id: $er->id,
        code: $er->code,
        precinct: $er->precinct,
        tallies: $er->tallies,
        signatures: new DataCollection(ElectoralInspectorData::class, [$signed]),
        ballots: $er->ballots,
        created_at: $er->created_at,
        updated_at: now(),
    );

    $store->replaceElectionReturn($updated);

// 6. Retrieve and assert
    $fetched = $store->getElectionReturn('ER-002');

    expect($fetched->signatures)->toHaveCount(1)
        ->and($fetched->signatures[0]->id)->toBe('ei-101')
        ->and($fetched->signatures[0]->signature)->toBe('data:image/png;base64,FAKESIGN==')
        ->and($fetched->signatures[0]->signed_at)->not->toBeNull();
});

it('returns election return by precinct code', function () {
    $store = new DatabaseElectionStore();

    // 1. Prepare inspectors
    $inspectors = collect([
        new ElectoralInspectorData(
            id: 'ei-201',
            name: 'Inspector Alpha',
            role: ElectoralInspectorRole::CHAIRPERSON,
        ),
    ]);

    // 2. Prepare ballots
    $ballots = collect([
        BallotData::from([
            'code' => 'BAL-201',
            'precinct_code' => 'P-999',
            'votes' => BallotFactory::votes(),
        ]),
    ]);

    // 3. Create PrecinctData
    $precinctData = new PrecinctData(
        code: 'P-999',
        location_name: 'Basketball Court',
        latitude: 15.5,
        longitude: 120.9,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, $inspectors),
        ballots: new DataCollection(BallotData::class, $ballots),
    );

    // 4. Store precinct
    $precinct = Precinct::fromData($precinctData);
    $store->putPrecinct($precinctData);

    // 5. Create election return with this precinct
    $er = new ElectionReturnData(
        id: 'ER-ID-999',
        code: 'ER-999',
        precinct: $precinctData,
        tallies: new DataCollection(VoteCountData::class, []),
        signatures: new DataCollection(ElectoralInspectorData::class, []),
        ballots: new DataCollection(BallotData::class, $ballots),
        created_at: now(),
        updated_at: now(),
    );

    $store->putElectionReturn($er);

    // 6. Retrieve by precinct code
    $fetched = $store->getElectionReturnByPrecinct('P-999');

    expect($fetched)->not->toBeNull()
        ->and($fetched->code)->toBe('ER-999');
});

it('resets all election-related tables', function () {
    $store = new DatabaseElectionStore();

    // Seed models
    Precinct::factory()->create();
    Ballot::factory()->create();
    ElectionReturn::factory()->create();

    $store->reset();

    expect(Precinct::count())->toBe(0);
    expect(Ballot::count())->toBe(0);
    expect(ElectionReturn::count())->toBe(0);
});

it('loads positions and creates empty ballots', function () {
    $store = new DatabaseElectionStore();

    $precinct = Precinct::factory()->create(['code' => 'PX-003'])->getData();

    $positions = [
        PositionData::from([
            'code' => 'MAYOR',
            'name' => 'Municipal Mayor',
            'level' => 'local',
            'count' => 1,
            'candidates' => [
                new CandidateData(
                    code: 'uuid-mayor1',
                    name: 'Mayor One',
                    alias: 'ONE',
                    position: new PositionData('MAYOR', 'Municipal Mayor', Level::LOCAL, 1)
                )
            ],
        ]),
    ];

    $store->load($positions, $precinct);

    $ballots = $store->getBallotsForPrecinct('PX-003');

    expect($ballots)->toHaveCount(1)
        ->and($ballots[0]['code'])->toBe('MAYOR')
        ->and($ballots[0]['votes'])->toBeArray()
        ->and($ballots[0]['votes'])->toHaveCount(0);
});

it('persists positions using setPositions()', function () {
    $store = new DatabaseElectionStore();

    $position = new PositionData(
        code: 'MAYOR',
        name: 'Municipal Mayor',
        level: Level::LOCAL,
        count: 1
    );

    $store->setPositions([
        'MAYOR' => $position,
    ]);

    expect(Position::count())->toBe(1);
    expect(Position::first()->code)->toBe('MAYOR');
});

it('persists candidates using setCandidates()', function () {
    $store = new DatabaseElectionStore();

    $position = new PositionData('MAYOR', 'Municipal Mayor', Level::LOCAL, 1);
    $candidate = new CandidateData(
        code: 'cand-123',
        name: 'Jane Doe',
        alias: 'JANE',
        position: $position,
    );

    $store->setCandidates([
        'cand-123' => $candidate,
    ]);

    expect(Candidate::count())->toBe(1);
    expect(Candidate::first()->alias)->toBe('JANE');
});

it('retrieves electoral inspectors for a given precinct', function () {
    $store = new DatabaseElectionStore();

    // Create a precinct with inspectors
    $inspectors = new DataCollection(ElectoralInspectorData::class, [
        new ElectoralInspectorData(
            id: 'ei-301',
            name: 'Inspector Uno',
            role: ElectoralInspectorRole::CHAIRPERSON
        ),
        new ElectoralInspectorData(
            id: 'ei-302',
            name: 'Inspector Dos',
            role: ElectoralInspectorRole::MEMBER
        ),
    ]);

    $precinct = new PrecinctData(
        code: 'PX-301',
        location_name: 'Town Hall',
        latitude: 11.11,
        longitude: 123.45,
        electoral_inspectors: $inspectors,
        ballots: new DataCollection(BallotData::class, []),
    );

    // Store it
    $store->putPrecinct($precinct);

    // Retrieve inspectors using the method under test
    $retrievedInspectors = $store->getInspectorsForPrecinct('PX-301');

    expect($retrievedInspectors)->toBeInstanceOf(DataCollection::class)
        ->and($retrievedInspectors)->toHaveCount(2)
        ->and($retrievedInspectors->first()->id)->toBe('ei-301')
        ->and($retrievedInspectors->last()->name)->toBe('Inspector Dos');
});

it('returns an empty collection if the precinct is not found', function () {
    $store = new DatabaseElectionStore();

    $inspectors = $store->getInspectorsForPrecinct('NON-EXISTENT');

    expect($inspectors)->toBeInstanceOf(DataCollection::class)
        ->and($inspectors)->toHaveCount(0);
});

it('returns an empty collection if electoral_inspectors is null', function () {
    Precinct::create([
        'code' => 'PX-302',
        'location_name' => 'Sample Location', // ✅ required field
        'latitude' => 0,                      // optional but often required depending on your schema
        'longitude' => 0,
        'electoral_inspectors' => null,
    ]);

    $store = new DatabaseElectionStore();

    $inspectors = $store->getInspectorsForPrecinct('PX-302');

    expect($inspectors)->toBeInstanceOf(DataCollection::class)
        ->and($inspectors)->toHaveCount(0);
});

it('returns the first precinct if no code is provided', function () {
    $store = new DatabaseElectionStore();

    $precinct1 = PrecinctData::from([
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 10.1,
        'longitude' => 120.1,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    $precinct2 = PrecinctData::from([
        'code' => 'PRECINCT-02',
        'location_name' => 'Barangay Hall',
        'latitude' => 11.2,
        'longitude' => 121.2,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    $store->putPrecinct($precinct1);
    $store->putPrecinct($precinct2);

    $default = $store->getPrecinct(); // No code passed

    expect($default)->not->toBeNull()
        ->and($default->code)->toBe('PRECINCT-01') // The first one inserted
        ->and($default->location_name)->toBe('City Hall');
});

use TruthElection\Data\{MarkData, MappingData};

it('can store and retrieve a mapping via setMappings and getMappings', function () {
    $store = new DatabaseElectionStore();
    $store->reset();

    $mapping = new MappingData(
        code: '0102800000',
        location_name: 'Currimao, Ilocos Norte',
        district: '2',
        marks: new DataCollection(MarkData::class, [
            new MarkData(key: 'A1', value: 'LD_001'),
            new MarkData(key: 'A2', value: 'SJ_002'),
            new MarkData(key: 'A3', value: 'DW_003'),
        ])
    );

    $store->setMappings($mapping);
    $fetched = $store->getMappings();

    expect($fetched)->toBeInstanceOf(MappingData::class)
        ->and($fetched->code)->toBe('0102800000')
        ->and($fetched->location_name)->toBe('Currimao, Ilocos Norte')
        ->and($fetched->district)->toBe('2')
        ->and($fetched->marks)->toHaveCount(3)
        ->and($fetched->marks[0]->key)->toBe('A1')
        ->and($fetched->marks[0]->value)->toBe('LD_001');
});

it('can store and retrieve ballot mark keys for a ballot', function () {
    $store = new DatabaseElectionStore();
    $store->reset();

    $store->addBallotMark('BALLOT-ABC-001', 'A1');
    $store->addBallotMark('BALLOT-ABC-001', 'A2');
    $store->addBallotMark('BALLOT-ABC-001', 'A3');
    $store->addBallotMark('BALLOT-ABC-001', 'A2'); // Duplicate should be ignored

    $markKeys = $store->getBallotMarkKeys('BALLOT-ABC-001');

    expect($markKeys)->toBeArray()
        ->and($markKeys)->toHaveCount(3)
        ->and($markKeys)->toContain('A1')
        ->and($markKeys)->toContain('A2')
        ->and($markKeys)->toContain('A3');
});
