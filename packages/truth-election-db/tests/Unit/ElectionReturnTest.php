<?php

use TruthElection\Data\{BallotData,
    CandidateData,
    ElectionReturnData,
    ElectoralInspectorData,
    PositionData,
    PrecinctData,
    VoteCountData,
    VoteData};
use TruthElectionDb\Models\{Ballot, Precinct, ElectionReturn};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Enums\ElectoralInspectorRole;
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;

uses(RefreshDatabase::class);

test('election return has attributes', function () {
    $er = ElectionReturn::factory()->create();
    expect($er)->toBeInstanceOf(ElectionReturn::class);
    expect($er->id)->toBeUuid();
    expect($er->signatures)->toBeArray();
    expect($er->signatures)->toBeEmpty();
    expect($er->ballots)->toBeArray();
    expect($er->ballots)->toBeEmpty();
    expect($er->tallies)->toBeArray();
    expect($er->tallies)->toBeEmpty();
});

test('election return has precinct relation but cast as array', function () {
    $er = ElectionReturn::factory()->forPrecinct()->create();
    expect($er->belongsTo(Precinct::class, 'precinct_code', 'code')->getResults())->toBeInstanceOf(Precinct::class);
    expect($er->precinct)->toBeArray();
    expect($er->precinct)->toMatchArray(Precinct::factory()->definition());
});

test('election return has ballots and tallies', function () {
    $er = ElectionReturn::factory()->forPrecinct()->create();
    expect($er->ballots)->toBeArray();
    expect($er->ballots)->toHaveCount(0);
    expect($er->tallies)->toBeArray();
    expect($er->tallies)->toHaveCount(0);
    $ballots = Ballot::factory(2)
        ->state(['precinct_code' => $er->precinct['code']])
        ->create();
    expect($er->ballots)->toHaveCount(2);
    expect($er->ballots)->toMatchArray($ballots->toArray());
    expect($er->tallies)->toBeGreaterThan(0);
    expect($er->tallies)->toMatchArray($er->precinct['tallies']);
});

test('election return has signatures', function () {
    $er = ElectionReturn::factory()->withSignatures()->create();
    expect($er->signatures)->toMatchArray(ElectionReturn::factory()->signatures());

});

test('election return has a dataClass', function () {
    $precinct = Precinct::factory()->withPrecinctMeta()->create();

    $er = ElectionReturn::factory()
        ->withSignatures()
        ->create(['precinct_code' => $precinct->code]);

    Ballot::factory(2)
        ->state(['precinct_code' => $er->precinct['code']])
        ->create();

    $data = $er->getData();

    expect($data)->toBeInstanceOf(ElectionReturnData::class);
    expect($data->precinct)->toBeInstanceOf(PrecinctData::class);
    expect($data->tallies)->toBeInstanceOf(DataCollection::class);
    expect($data->signatures)->toBeInstanceOf(DataCollection::class);
    expect($data->ballots)->toBeInstanceOf(DataCollection::class);
    //TODO: test the values of each property on each of the DTO
});

it('can be created with a valid precinct', function () {
    $precinct = Precinct::factory()->create([
        'code' => 'PR-001',
        'location_name' => 'Sample Precinct',
    ]);

    $electionReturn = ElectionReturn::factory()->create([
        'code' => 'ER-001',
        'precinct_code' => $precinct->code,
        'signatures' => [],
    ]);

    expect($electionReturn)->toBeInstanceOf(ElectionReturn::class);
    expect($electionReturn->precinct)->toBeArray();
    expect($electionReturn->precinct['code'])->toEqual('PR-001');
});

it('can accept a precinct object in setPrecinctAttribute', function () {
    $precinct = Precinct::factory()->create();

    $electionReturn = new ElectionReturn([
        'code' => 'ER-002',
        'signatures' => [],
    ]);

    $electionReturn->setAttribute('precinct', $precinct);

    expect($electionReturn->precinct_code)->toEqual($precinct->code);
});

it('can accept a precinct code in setPrecinctAttribute', function () {
    $electionReturn = new ElectionReturn([
        'code' => 'ER-003',
        'signatures' => [],
    ]);

    $electionReturn->setAttribute('precinct', 'PR-CUSTOM');

    expect($electionReturn->precinct_code)->toEqual('PR-CUSTOM');
});

it('returns empty array if precinct is missing', function () {
    $electionReturn = new ElectionReturn([
        'code' => 'ER-004',
        'precinct_code' => 'NON-EXISTENT',
        'signatures' => [],
    ]);

    expect($electionReturn->precinct)->toBeArray()->toBeEmpty();
    expect($electionReturn->tallies)->toBeArray()->toBeEmpty();
    expect($electionReturn->ballots)->toBeArray()->toBeEmpty();
});

it('can create or update an election return from data', function () {
    // 1. Create a related precinct first
    $precinct = Precinct::factory()->create(['code' => 'PR-010']);

    // 2. Build the ElectionReturnData DTO
    $data = new ElectionReturnData(
        id: 'xxx',
        code: 'ER-010',
        precinct: PrecinctData::from($precinct->toArray()),
        tallies: new DataCollection(VoteCountData::class, []),
        signatures: new DataCollection(ElectoralInspectorData::class, [
            new ElectoralInspectorData('aaa', 'Jane Doe', ElectoralInspectorRole::CHAIRPERSON),
            new ElectoralInspectorData('bbb', 'John Smith', ElectoralInspectorRole::MEMBER),
        ]),
        ballots: new DataCollection(BallotData::class, [
            new BallotData(
                code: 'BALLOT-XYZ',
                votes: new DataCollection(VoteData::class, [
                    new VoteData(
                        candidates: new DataCollection(CandidateData::class, [
                            new CandidateData(
                                code: 'uuid-bbm-1234',
                                name: 'Ferdinand Marcos Jr.',
                                alias: 'BBM',
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

    // 3. Use the model's fromData() method
    $model = ElectionReturn::fromData($data);

    // 4. Basic assertions on ElectionReturn
    expect($model)->toBeInstanceOf(ElectionReturn::class)
        ->and($model->code)->toBe('ER-010')
        ->and($model->precinct_code)->toBe('PR-010')
        ->and($model->signatures)->toBeArray()
        ->and($model->signatures)->toHaveCount(2)
        ->and($model->signatures[0]['role'])->toBe('chairperson')
        ->and($model->signatures[1]['name'])->toBe('John Smith');

    // 5. Assert ballot was persisted
    $ballot = Ballot::where('code', 'BALLOT-XYZ')->first();

    expect($ballot)->not->toBeNull()
        ->and($ballot->precinct_code)->toBe('PR-010')
        ->and($ballot->votes)->toBeArray()
        ->and($ballot->votes[0]['candidates'][0]['name'])->toBe('Ferdinand Marcos Jr.')
        ->and($ballot->votes[0]['candidates'][0]['position']['code'])->toBe('PRESIDENT');

    // 6. Re-run fromData with same data to ensure update not duplication
    $updated = ElectionReturn::fromData($data);
    expect(ElectionReturn::count())->toBe(1)
        ->and($updated->is($model))->toBeTrue(); // still the same record
});
