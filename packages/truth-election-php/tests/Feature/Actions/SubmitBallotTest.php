<?php

use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Tests\ResetsElectionStore;
use TruthElection\Events\BallotSubmitted;
use TruthElection\Actions\SubmitBallot;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\Event;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PrecinctData;
use TruthElection\Data\PositionData;
use TruthElection\Data\BallotData;
use TruthElection\Data\VoteData;
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

    $this->votes = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(
                    code: 'CANDIDATE-001',
                    name: 'Juan Dela Cruz',
                    alias: 'JUAN',
                    position: new PositionData(
                        code: 'PRESIDENT',
                        name: 'President of the Philippines',
                        level: Level::NATIONAL,
                        count: 1
                    )
                ),
            ])
        ),
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(
                    code: 'CANDIDATE-002',
                    name: 'Maria Santos',
                    alias: 'MARIA',
                    position: $position = new PositionData(
                        code: 'SENATOR',
                        name: 'Senator',
                        level: Level::NATIONAL,
                        count: 12
                    )
                ),
                new CandidateData(
                    code: 'CANDIDATE-003',
                    name: 'Pedro Reyes',
                    alias: 'PEDRO',
                    position: $position
                ),
            ])
        ),
    ]);
});

it('submits a ballot to an existing precinct', function () {
    $ballot = SubmitBallot::run('BAL-001', $this->votes);

    expect($ballot)->toBeInstanceOf(BallotData::class)
        ->and($ballot->code)->toBe('BAL-001')
        ->and($ballot->votes)->toHaveCount(2)
        ->and(
            $this->store
                ->getBallots($this->precinct->code)
                ->toCollection()
                ->keyBy('code')
                ->all()
        )->toHaveKey('BAL-001');
    ;
});

it('stores vote data correctly', function () {
    $ballot = SubmitBallot::run('BAL-003', $this->votes);

    $vote1 = $ballot->votes[0];
    $vote2 = $ballot->votes[1];

    expect($vote1->position->code)->toBe('PRESIDENT')
        ->and($vote1->candidates)->toHaveCount(1)
        ->and($vote2->position->code)->toBe('SENATOR')
        ->and($vote2->candidates[1]->alias)->toBe('PEDRO');
});

it('dispatches BallotSubmitted event when a ballot is submitted', function () {
    Event::fake();

    $ballot = SubmitBallot::run('BAL-004', $this->votes);

    Event::assertDispatched(BallotSubmitted::class, function (BallotSubmitted $event) use ($ballot) {
        return $event->ballot->code === $ballot->code
            && $event->ballot->getPrecinctCode() === $ballot->getPrecinctCode()
            && $event->broadcastOn()[0]->name === "precinct.{$ballot->getPrecinctCode()}";
    });
});
