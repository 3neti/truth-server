<?php

use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Actions\ReadVote;
use TruthElection\Actions\FinalizeBallot;
use TruthElection\Data\BallotData;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Data\PrecinctData;
use TruthElection\Enums\Level;

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

    $this->president = new PositionData(
        code: 'PRESIDENT',
        name: 'President of the Philippines',
        level: Level::NATIONAL,
        count: 1
    );

    $this->senator = new PositionData(
        code: 'SENATOR',
        name: 'Senator',
        level: Level::NATIONAL,
        count: 2
    );

    $this->candidates = [
        new CandidateData(
            code: 'CANDIDATE-001',
            name: 'Juan Dela Cruz',
            alias: 'JUAN',
            position: $this->president
        ),
        new CandidateData(
            code: 'CANDIDATE-002',
            name: 'Maria Santos',
            alias: 'MARIA',
            position: $this->senator
        ),
        new CandidateData(
            code: 'CANDIDATE-003',
            name: 'Pedro Reyes',
            alias: 'PEDRO',
            position: $this->senator
        ),
    ];

    $this->store->setCandidates($this->candidates);

    $this->store->setMappings([
        'code' => $this->precinct->code,
        'location_name' => $this->precinct->location_name,
        'district' => 'District 1',
        'marks' => [
            ['key' => 'PRESIDENT-1', 'value' => 'CANDIDATE-001'],
            ['key' => 'SENATOR-1', 'value' => 'CANDIDATE-002'],
            ['key' => 'SENATOR-2', 'value' => 'CANDIDATE-003'],
        ],
    ]);
});

it('finalizes a ballot with valid votes from marks', function () {
    $ballotCode = 'BALLOT-ABC-001';

    ReadVote::run($ballotCode, 'PRESIDENT-1');
    ReadVote::run($ballotCode, 'SENATOR-1');
    ReadVote::run($ballotCode, 'SENATOR-2');

    $ballot = FinalizeBallot::run($ballotCode);

    expect($ballot)->toBeInstanceOf(BallotData::class);
    expect($ballot->code)->toBe($ballotCode);
    expect($ballot->votes)->toHaveCount(2);

    $presidentVote = $ballot->votes->first(fn ($v) => $v->position->code === 'PRESIDENT');
    $senatorVote = $ballot->votes->first(fn ($v) => $v->position->code === 'SENATOR');

    expect($presidentVote)->not->toBeNull();
    expect($presidentVote->candidates)->toHaveCount(1);
    expect($presidentVote->candidates[0]->code)->toBe('CANDIDATE-001');

    expect($senatorVote)->not->toBeNull();
    expect($senatorVote->candidates)->toHaveCount(2);
    expect($senatorVote->candidates->toCollection()->pluck('code')->all())->toMatchArray([
        'CANDIDATE-002',
        'CANDIDATE-003'
    ]);
});

it('stores finalized ballot in the election store', function () {
    $ballotCode = 'BAL-FINALIZED-001';

    // Simulate votes for a full ballot
    ReadVote::run($ballotCode, 'PRESIDENT-1');
    ReadVote::run($ballotCode, 'SENATOR-1');
    ReadVote::run($ballotCode, 'SENATOR-2');

    // Finalize and submit
    FinalizeBallot::run($ballotCode);

    // Fetch from store instead of relying on return
    $ballots = $this->store->getBallots($this->precinct->code);
    $storedBallot = $ballots->toCollection()->first(fn ($b) => $b->code === $ballotCode);

    expect($storedBallot)->not->toBeNull();
    expect($storedBallot->votes)->toHaveCount(2);

    $presidentVote = $storedBallot->votes->first(fn ($v) => $v->position->code === 'PRESIDENT');
    $senatorVote   = $storedBallot->votes->first(fn ($v) => $v->position->code === 'SENATOR');

    expect($presidentVote)->not->toBeNull();
    expect($presidentVote->candidates)->toHaveCount(1);
    expect($presidentVote->candidates[0]->code)->toBe('CANDIDATE-001');

    expect($senatorVote)->not->toBeNull();
    expect($senatorVote->candidates)->toHaveCount(2);

    $senatorCodes = $senatorVote->candidates->toCollection()->pluck('code')->all();
    expect($senatorCodes)->toContain('CANDIDATE-002');
    expect($senatorCodes)->toContain('CANDIDATE-003');
});
