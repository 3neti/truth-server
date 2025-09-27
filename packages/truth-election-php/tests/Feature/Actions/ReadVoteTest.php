<?php

use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Actions\ReadVote;
use TruthElection\Data\BallotData;
use TruthElection\Data\VoteData;
use TruthElection\Enums\Level;

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->resetElectionStore();

    $this->store = \TruthElection\Support\InMemoryElectionStore::instance();

    $this->precinct = \TruthElection\Data\PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [],
    ]);

    $this->store->putPrecinct($this->precinct);

    // Define positions
    $this->presidentPosition = new PositionData(
        code: 'PRESIDENT',
        name: 'President of the Philippines',
        level: Level::NATIONAL,
        count: 1
    );

    $this->senatorPosition = new PositionData(
        code: 'SENATOR',
        name: 'Senator',
        level: Level::NATIONAL,
        count: 12
    );

    // Define candidates
    $this->presidentCandidate1 = new CandidateData(
        code: 'CANDIDATE-001',
        name: 'Juan Dela Cruz',
        alias: 'JUAN',
        position: $this->presidentPosition
    );

    $this->presidentCandidate2 = new CandidateData(
        code: 'CANDIDATE-004',
        name: 'John Die',
        alias: 'JOHN',
        position: $this->presidentPosition
    );

    $this->senatorCandidate1 = new CandidateData(
        code: 'CANDIDATE-002',
        name: 'Maria Santos',
        alias: 'MARIA',
        position: $this->senatorPosition
    );

    $this->senatorCandidate2 = new CandidateData(
        code: 'CANDIDATE-003',
        name: 'Pedro Reyes',
        alias: 'PEDRO',
        position: $this->senatorPosition
    );

    $this->store->setCandidates([
        $this->presidentCandidate1,
        $this->presidentCandidate2,
        $this->senatorCandidate1,
        $this->senatorCandidate2,
    ]);

    $this->store->setMappings([
        'code' => $this->precinct->code,
        'location_name' => $this->precinct->location_name,
        'district' => 'District 1',
        'marks' => [
            ['key' => 'PRESIDENT-1', 'value' => $this->presidentCandidate1->code],
            ['key' => 'PRESIDENT-2', 'value' => $this->presidentCandidate2->code],
            ['key' => 'SENATOR-1', 'value' => $this->senatorCandidate1->code],
            ['key' => 'SENATOR-2', 'value' => $this->senatorCandidate2->code],
        ],
    ]);
});

it('reads multiple votes for different positions and assembles them into a complete ballot', function () {
    $ballotCode = 'BALLOT-2025-001';

    // Simulate sequential scanning of ovals on the same ballot
    ReadVote::run($ballotCode, 'PRESIDENT-1');
    ReadVote::run($ballotCode, 'SENATOR-1');
    $finalBallot = ReadVote::run($ballotCode, 'SENATOR-2');

    expect($finalBallot)->toBeInstanceOf(BallotData::class);
    expect($finalBallot->code)->toBe($ballotCode);

    $votes = $finalBallot->votes;
    expect($votes)->toHaveCount(2);

    $presidentVote = $votes->first(fn (VoteData $v) => $v->candidates->toCollection()->first()->position->code === 'PRESIDENT');
    $senatorVote   = $votes->first(fn (VoteData $v) => $v->candidates->toCollection()->first()->position->code === 'SENATOR');

    expect($presidentVote)->not->toBeNull();
    expect($senatorVote)->not->toBeNull();

    expect($presidentVote->candidates)->toHaveCount(1);
    expect($presidentVote->candidates->toCollection()->first()->code)->toBe('CANDIDATE-001');

    expect($senatorVote->candidates)->toHaveCount(2);
    $senatorCodes = $senatorVote->candidates->toCollection()->pluck('code')->all();
    expect($senatorCodes)->toContain('CANDIDATE-002');
    expect($senatorCodes)->toContain('CANDIDATE-003');
});

it('voids the vote for a position when overvoted but keeps other positions valid', function () {
    $ballotCode = 'BALLOT-2025-002';

    // Simulate two marks for PRESIDENT (should only allow 1)
    ReadVote::run($ballotCode, 'PRESIDENT-1');
    ReadVote::run($ballotCode, 'PRESIDENT-2'); // <- overvote

    // Still mark valid senators
    ReadVote::run($ballotCode, 'SENATOR-1');
    $finalBallot = ReadVote::run($ballotCode, 'SENATOR-2');

    expect($finalBallot)->toBeInstanceOf(BallotData::class);
    expect($finalBallot->code)->toBe($ballotCode);

    $votes = $finalBallot->votes;

    expect($votes)->toHaveCount(1); // Only SENATOR vote should survive

    // Make sure PRESIDENT vote was voided
    $presidentVote = $votes->first(fn (VoteData $v) => $v->candidates->first()->position->code === 'PRESIDENT');
    expect($presidentVote)->toBeNull();

    // Make sure SENATOR vote was retained and valid
    $senatorVote = $votes->first(fn (VoteData $v) => $v->candidates->first()->position->code === 'SENATOR');
    expect($senatorVote)->not->toBeNull();
    expect($senatorVote->candidates)->toHaveCount(2);
});
