<?php

use TruthElection\Actions\{GenerateElectionReturn, SubmitBallot};
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Support\InMemoryElectionStore;
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;
use TruthElection\Data\{
    ElectionReturnData,
    CandidateData,
    PrecinctData,
    PositionData,
    VoteData
};

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->resetElectionStore();

    $this->store = InMemoryElectionStore::instance();

    $this->precinct = PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [],
    ]);

    $this->store->putPrecinct($this->precinct);

    $votes1 = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-001', name: 'Juan Dela Cruz', alias: 'JUAN', position:  new PositionData(
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

    $votes3 = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(code: 'CANDIDATE-006', name: 'Emilio Aguinaldo', alias: 'EMILIO', position: $position = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-007', name: 'Apolinario Mabini', alias: 'APO', position: $position),
                new CandidateData(code: 'CANDIDATE-008', name: 'Gregorio del Pilar', alias: 'GREG', position: $position),
                new CandidateData(code: 'CANDIDATE-009', name: 'Melchora Aquino', alias: 'TANDANG', position: $position),
                new CandidateData(code: 'CANDIDATE-010', name: 'Antonio Luna', alias: 'TONIO', position: $position),
                new CandidateData(code: 'CANDIDATE-011', name: 'Marcelo del Pilar', alias: 'CEL', position: $position),
                new CandidateData(code: 'CANDIDATE-012', name: 'Diego Silang', alias: 'DIEGO', position: $position),
                new CandidateData(code: 'CANDIDATE-013', name: 'Gabriela Silang', alias: 'GABRIELA', position: $position),
                new CandidateData(code: 'CANDIDATE-014', name: 'Francisco Baltazar', alias: 'BALTAZAR', position: $position),
                new CandidateData(code: 'CANDIDATE-015', name: 'Leona Florentino', alias: 'LEONA', position: $position),
                new CandidateData(code: 'CANDIDATE-016', name: 'Josefa Llanes Escoda', alias: 'JOSEFA', position: $position),
                new CandidateData(code: 'CANDIDATE-017', name: 'Manuel Quezon', alias: 'QUEZON', position: $position),
                new CandidateData(code: 'CANDIDATE-018', name: 'Sergio Osmeña', alias: 'OSMENA', position: $position),
            ])
        ),
    ]);

    SubmitBallot::run('BAL-001', $votes1); // valid
    SubmitBallot::run('BAL-002', $votes2); // valid
    SubmitBallot::run('BAL-003', $votes3); // overvote (13 senators), should be rejected
});

it('generates an election return from in-memory data', function () {

    $return = GenerateElectionReturn::run();

    expect($return)->toBeInstanceOf(ElectionReturnData::class)
        ->and($return->precinct->code)->toBe('PRECINCT-01')
        ->and($return->ballots)->toHaveCount(3)
        ->and($return->tallies)->toBeInstanceOf(DataCollection::class)
    ;
    $return->tallies->each(fn ($tally) => expect($tally->count)->toBeGreaterThan(0));
});

it('generates an election return with correct tallies', function () {
    $return = GenerateElectionReturn::run();

    expect($return)->toBeInstanceOf(ElectionReturnData::class)
        ->and($return->precinct->code)->toBe('PRECINCT-01')
        ->and($return->ballots)->toHaveCount(3) // 3 ballots submitted
        ->and($return->tallies)->toBeInstanceOf(DataCollection::class);

    // Convert tallies to base Laravel collection
    $tallies = $return->tallies->toCollection();

    // ✅ PRESIDENT tallies (2 ballots voted for president)
    $presidentTallies = $tallies->where('position_code', 'PRESIDENT');

    expect($presidentTallies)->toHaveCount(2); // 2 valid votes for PRESIDENT

    $presidentVotes = $presidentTallies->keyBy('candidate_code')->map->count;

    expect($presidentVotes->get('CANDIDATE-001'))->toBe(1); // Juan Dela Cruz (BAL-001)
    expect($presidentVotes->get('CANDIDATE-004'))->toBe(1); // Jose Rizal (BAL-002)

    // ✅ SENATOR tallies (BAL-001 and BAL-002 only — BAL-003 is overvote and ignored)
    $senatorTallies = $tallies->where('position_code', 'SENATOR');

    expect($senatorTallies)->toHaveCount(3); // Only 3 valid senator votes from 2 ballots

    $senatorVotes = $senatorTallies->keyBy('candidate_code')->map->count;

    expect($senatorVotes->get('CANDIDATE-002'))->toBe(2); // Maria Santos (BAL-001 and BAL-002)
    expect($senatorVotes->get('CANDIDATE-003'))->toBe(1); // Pedro Reyes (BAL-001)
    expect($senatorVotes->get('CANDIDATE-005'))->toBe(1); // Andres Bonifacio (BAL-002)

    // 🚫 Ensure over voted senators from BAL-003 are excluded
    expect($senatorVotes->has('CANDIDATE-006'))->toBeFalse(); // Emilio Aguinaldo (BAL-003 only)
});

it('stores election return in the election store', function () {
    expect($this->store->electionReturns)->toBeEmpty();

    $return = GenerateElectionReturn::run();

    expect($return)->toBeInstanceOf(ElectionReturnData::class)
        ->and($return->ballots)->toHaveCount(3)
        ->and($return->tallies)->toBeInstanceOf(DataCollection::class)
        ->and($return->code)->toBeString();

    // 🧠 Confirm it was saved into the store
    $stored = $this->store->getElectionReturn($return->code);

    expect($stored)->not->toBeNull()
        ->and($stored->code)->toBe($return->code)
        ->and($stored->precinct->code)->toBe('PRECINCT-01');
});

it('generates an election return with a custom code', function () {
    $customCode = 'ER-PRECINCT-01-CUSTOM';

    // Run GenerateElectionReturn with optional electionReturnCode
    $return = GenerateElectionReturn::run($customCode);

    expect($return)->toBeInstanceOf(ElectionReturnData::class)
        ->and($return->code)->toBe($customCode)
        ->and($return->precinct->code)->toBe('PRECINCT-01');

    // 🧠 Confirm it was saved with the custom code
    $stored = $this->store->getElectionReturn($customCode);

    expect($stored)->not->toBeNull()
        ->and($stored->code)->toBe($customCode);
});
