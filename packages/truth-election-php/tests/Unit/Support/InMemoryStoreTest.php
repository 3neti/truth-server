<?php

use TruthElection\Data\{ElectionReturnData, PrecinctData};
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Data\ElectoralInspectorData;
use TruthElection\Data\BallotData;
use Illuminate\Support\Carbon;

uses(ResetsElectionStore::class)->beforeEach(fn () => $this->resetElectionStore());

it('can store and retrieve ballots and precincts in memory', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $precinct = PrecinctData::from([
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 18.2,
        'longitude' => 120.5,
        'electoral_inspectors' => [],
        'ballots' => [],
    ]);

    $ballot = BallotData::from([
        'code' => 'BAL-001',
        'votes' => [],
    ]);

    $store->putPrecinct($precinct);
    $store->putBallot($ballot, $precinct->code); // ✅ attach ballot to precinct by code

    $ballots = $store->getBallotsForPrecinct('PRECINCT-01');

    expect($store->precincts)->toHaveKey('PRECINCT-01')
//        ->and($store->ballots)->toHaveKey('BAL-001')
        ->and(
            $store
                ->getBallots($precinct->code)
                ->toCollection()
                ->keyBy('code')
                ->all()
        )->toHaveKey('BAL-001')
        ->and($ballots)->toHaveCount(1)
        ->and($ballots[0]['code'])->toBe('BAL-001');
});

it('can store multiple ballots for the same precinct', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $precinct = PrecinctData::from([
        'code' => 'PRECINCT-01',
        'location_name' => 'School',
        'latitude' => 10.0,
        'longitude' => 120.0,
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
        ->and($ballots[1]['code'])->toBe('BAL-002')
        ->and($ballots[2]['code'])->toBe('BAL-003');
});

it('resets the store correctly', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    expect($store->precincts)->toBeEmpty()
        ->and($store->ballots)->toBeEmpty();
});

it('returns empty array when no ballots exist for a precinct', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $ballots = $store->getBallotsForPrecinct('NON_EXISTENT');

    expect($ballots)->toBeArray()->toBeEmpty();
});

it('can retrieve election return by code', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $electionReturn = ElectionReturnData::from([
        'id' => 'er-id-001',
        'code' => 'ER-001',
        'precinct' => [
            'id' => 'precinct-1',
            'code' => 'PRECINCT-99',
            'location_name' => 'Gymnasium',
            'latitude' => 14.6,
            'longitude' => 121.0,
            'electoral_inspectors' => [],
        ],
        'tallies' => [
            [
                'position_code' => 'PRESIDENT',
                'candidate_code' => 'CAND-A',
                'candidate_name' => 'Candidate A',
                'count' => 123,
            ],
        ],
        'signatures' => [],
        'ballots' => [],
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $store->putPrecinct($electionReturn->precinct);
    $store->putElectionReturn($electionReturn);

    $fetched = $store->getElectionReturn('ER-001');

    expect($fetched)->not->toBeNull()
        ->and($fetched->code)->toBe('ER-001')
        ->and($fetched->precinct->code)->toBe('PRECINCT-99')
        ->and($fetched->tallies)->toHaveCount(1)
        ->and($fetched->tallies[0]->candidate_name)->toBe('Candidate A')
    ;
});

it('can update election return signatures and replace it in the store', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    // Step 1: Seed original election return with inspectors
    $original = ElectionReturnData::from([
        'id' => 'er-id-001',
        'code' => 'ER-001',
        'precinct' => [
            'id' => 'precinct-1',
            'code' => 'PRECINCT-99',
            'location_name' => 'Gymnasium',
            'latitude' => 14.6,
            'longitude' => 121.0,
            'electoral_inspectors' => [
                [
                    'id' => 'A1',
                    'name' => 'Alice',
                    'role' => 'chairperson',
                ],
                [
                    'id' => 'B2',
                    'name' => 'Bob',
                    'role' => 'member',
                ],
            ],
            'ballots' => []
        ],
        'tallies' => [
            [
                'position_code' => 'PRESIDENT',
                'candidate_code' => 'CAND-A',
                'candidate_name' => 'Candidate A',
                'count' => 123,
            ],
        ],
        'signatures' => [],
        'ballots' => [],
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $store->putPrecinct($original->precinct);
    $store->putElectionReturn($original);

    // Step 2: Sign as "Bob" (B2), get his info from precinct
    $inspector = $store->findInspector($original, 'B2');
    expect($inspector)->toBeInstanceOf(ElectoralInspectorData::class);

    $signed = new ElectoralInspectorData(
        id: $inspector->id,
        name: $inspector->name,
        role: $inspector->role,
        signature: 'data:image/png;base64,FAKESIGNATURE==',
        signed_at: Carbon::now()
    );

    // Step 3: Rebuild election return with updated signatures
    $updatedArray = $original->toArray();
    $updatedArray['signatures'][] = $signed->toArray(); // or just use $signed directly with from()

    $updated = ElectionReturnData::from($updatedArray);
    $store->replaceElectionReturn($updated);

    // Step 4: Verify
    $fetched = $store->getElectionReturn('ER-001');

    expect($fetched)->not->toBeNull()
        ->and($fetched->signatures)->toHaveCount(1)
        ->and($fetched->signatures[0]->id)->toBe('B2')
        ->and($fetched->signatures[0]->name)->toBe('Bob')
        ->and($fetched->signatures[0]->role->value)->toBe('member')
        ->and($fetched->signatures[0]->signature)->toBe('data:image/png;base64,FAKESIGNATURE==');
});

it('can retrieve a precinct by code', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $precinct = [
        'id' => 'precinct-42',
        'code' => 'PRECINCT-42',
        'location_name' => 'Barangay Hall',
        'latitude' => 15.0,
        'longitude' => 120.7,
        'electoral_inspectors' => [],
    ];

    $store->putPrecinct(PrecinctData::from($precinct));

    $fetched = $store->getPrecinct('PRECINCT-42');

    expect($fetched)->not->toBeNull()
        ->and($fetched->code)->toBe('PRECINCT-42')
        ->and($fetched->location_name)->toBe('Barangay Hall');
});

it('returns election return by precinct code', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $precinct = PrecinctData::from([
        'id' => 'precinct-1',
        'code' => 'P-123',
        'location_name' => 'Gymnasium',
        'latitude' => 14.6,
        'longitude' => 121.0,
        'electoral_inspectors' => [
            [
                'id' => 'A1',
                'name' => 'Alice',
                'role' => 'chairperson',
            ],
            [
                'id' => 'B2',
                'name' => 'Bob',
                'role' => 'member',
            ],
        ],
        'ballots' => [],
    ]);

    $er = $original = ElectionReturnData::from([
        'id' => 'er-id-001',
        'code' => 'ER-001',
        'precinct' => $precinct->toArray(),
        'tallies' => [
            [
                'position_code' => 'PRESIDENT',
                'candidate_code' => 'CAND-A',
                'candidate_name' => 'Candidate A',
                'count' => 123,
            ],
        ],
        'signatures' => [],
        'ballots' => [],
        'created_at' => Carbon::now(),
        'updated_at' => Carbon::now(),
    ]);

    $store->putPrecinct($precinct);
    $store->putElectionReturn($er);

    $found = $store->getElectionReturnByPrecinct('P-123');

    expect($found)->toEqual($er);
});

it('returns null if precinct has no election return', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    expect($store->getElectionReturnByPrecinct('P-999'))->toBeNull();
});

it('binds ElectionStoreInterface to InMemoryElectionStore singleton', function () {
    $resolved = app(ElectionStoreInterface::class);

    expect($resolved)->toBeInstanceOf(InMemoryElectionStore::class)
        ->and($resolved)->toBe(InMemoryElectionStore::instance());
});

it('can retrieve inspectors for a precinct using getInspectorsForPrecinct()', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $precinct = PrecinctData::from([
        'id' => 'precinct-42',
        'code' => 'PRECINCT-42',
        'location_name' => 'Barangay Hall',
        'latitude' => 15.0,
        'longitude' => 120.7,
        'electoral_inspectors' => [
            [
                'id' => 'I-123',
                'name' => 'Inspector One',
                'role' => 'chairperson',
            ],
            [
                'id' => 'I-456',
                'name' => 'Inspector Two',
                'role' => 'member',
            ],
        ],
    ]);

    $store->putPrecinct($precinct);

    $inspectors = $store->getInspectorsForPrecinct('PRECINCT-42');

    expect($inspectors)->toHaveCount(2)
        ->and($inspectors[0]->id)->toBe('I-123')
        ->and($inspectors[0]->name)->toBe('Inspector One')
        ->and($inspectors[0]->role->value)->toBe('chairperson')
        ->and($inspectors[1]->id)->toBe('I-456')
        ->and($inspectors[1]->role->value)->toBe('member');
});

it('returns the first precinct if no code is provided', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $precinct1 = PrecinctData::from([
        'id' => 'p1',
        'code' => 'P-001',
        'location_name' => 'Precinct One',
        'latitude' => 10.0,
        'longitude' => 120.0,
        'electoral_inspectors' => [],
    ]);

    $precinct2 = PrecinctData::from([
        'id' => 'p2',
        'code' => 'P-002',
        'location_name' => 'Precinct Two',
        'latitude' => 11.0,
        'longitude' => 121.0,
        'electoral_inspectors' => [],
    ]);

    $store->putPrecinct($precinct1);
    $store->putPrecinct($precinct2);

    $defaultPrecinct = $store->getPrecinct(); // No code passed

    expect($defaultPrecinct)->toBeInstanceOf(PrecinctData::class)
        ->and($defaultPrecinct->code)->toBe('P-001'); // First added
});

use TruthElection\Data\{MarkData, MappingData};

it('can store and retrieve a mapping via setMappings and getMappings', function () {
    $store = InMemoryElectionStore::instance();
    $store->reset();

    $mapping = new MappingData(
        code: '0102800000',
        location_name: 'Currimao, Ilocos Norte',
        district: '2',
        marks: new \Spatie\LaravelData\DataCollection(MarkData::class, [
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
