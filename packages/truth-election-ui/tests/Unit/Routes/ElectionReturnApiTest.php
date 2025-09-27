<?php

use TruthElection\Data\{CandidateData, ElectionReturnData, PositionData, PrecinctData, VoteData};
use TruthElection\Actions\{GenerateElectionReturn, SubmitBallot};
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Enums\ElectoralInspectorRole;
use TruthElectionUi\Tests\ResetsElectionStore;
use Illuminate\Testing\Fluent\AssertableJson;
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;

uses(ResetsElectionStore::class)->beforeEach(function () {

    $this->store = InMemoryElectionStore::instance();
    $this->store->reset();

    $this->precinct = PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [
            [
                'id' => 'A1',
                'name' => 'Alice',
                'role' => ElectoralInspectorRole::CHAIRPERSON,
            ],
            [
                'id' => 'B2',
                'name' => 'Bob',
                'role' => ElectoralInspectorRole::MEMBER,
            ],
        ]
    ]);

    $this->store->putPrecinct($this->precinct);

    $votes1 = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-001', name: 'Juan Dela Cruz', alias: 'JUAN', position: new PositionData(
                    code: 'PRESIDENT',
                    name: 'President of the Philippines',
                    level: Level::NATIONAL,
                    count: 1
                )),
            ])
        ),
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-002', name: 'Maria Santos', alias: 'MARIA', position: $position = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-003', name: 'Pedro Reyes', alias: 'PEDRO', position: $position),
            ])
        ),
    ]);

    $votes2 = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-004', name: 'Jose Rizal', alias: 'JOSE', position: new PositionData(
                    code: 'PRESIDENT',
                    name: 'President of the Philippines',
                    level: Level::NATIONAL,
                    count: 1
                )),
            ])
        ),
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-002', name: 'Maria Santos', alias: 'MARIA', position: $position = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-005', name: 'Andres Bonifacio', alias: 'ANDRES', position: $position),
            ])
        ),
    ]);

    SubmitBallot::run('BAL-001', $votes1);
    SubmitBallot::run('BAL-001', $votes2);

    GenerateElectionReturn::run('PRECINCT-01');
});

it('returns the election return payload as JSON', function () {
    $response = $this->getJson(route('election-return', ['payload' => 'full']))
        ->assertOk()
        ->assertJson(fn (AssertableJson $json) =>
        $json
            ->has('id')
            ->has('code')
            ->has('precinct')
            ->has('tallies')
            ->has('signatures', 2)
            ->has('ballots', 1)
            ->has('created_at')
            ->has('updated_at')
            ->has('last_ballot')
            ->has('last_ballot.votes', 2)
            ->etc()
        );

    $data = ElectionReturnData::from($response->json());
    expect($data)->toBeInstanceOf(ElectionReturnData::class);
});

it('transforms election return based on payload level', function () {
    $fullResponse = $this->getJson(route('election-return', ['payload' => 'full']))
        ->assertOk();

    $fullData = $fullResponse->json();

    expect($fullData)->toHaveKeys(['signatures', 'ballots']);
    expect($fullData['precinct'])->toHaveKey('ballots');

    $minimalResponse = $this->getJson(route('election-return')) // defaults to minimal
    ->assertOk();

    $minimalData = $minimalResponse->json();

    expect($minimalData)->not()->toHaveKey('signatures');
    expect($minimalData)->not()->toHaveKey('ballots');
    expect($minimalData['precinct'])->not()->toHaveKey('ballots');

    expect($minimalData)->toHaveKeys([
        'id', 'code', 'precinct', 'tallies', 'created_at', 'updated_at', 'last_ballot'
    ]);
});
