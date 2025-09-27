<?php

use Illuminate\Support\Facades\Log;
use TruthElection\Data\BallotData;
use TruthElection\Data\CandidateData;
use TruthElection\Data\PositionData;
use TruthElection\Data\VoteData;
use TruthElection\Enums\Level;
use TruthElection\Support\PrecinctContext;
use TruthElection\Data\PrecinctData;
use TruthElection\Data\ElectoralInspectorData;
use TruthElection\Support\ElectionStoreInterface;
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\ElectoralInspectorRole;

use function Pest\Laravel\mock;

it('can resolve the precinct data and expose attributes', function () {
    $precinctCode = 'CURRIMAO-001';

    $inspectors = [
        new ElectoralInspectorData(
            id: 'uuid-juan',
            name: 'Juan Dela Cruz',
            role: ElectoralInspectorRole::CHAIRPERSON
        ),
        new ElectoralInspectorData(
            id: 'uuid-maria',
            name: 'Maria Santos',
            role: ElectoralInspectorRole::MEMBER
        ),
        new ElectoralInspectorData(
            id: 'uuid-pedro',
            name: 'Pedro Reyes',
            role: ElectoralInspectorRole::MEMBER
        ),
    ];

    $mockPrecinct = new PrecinctData(
        code: $precinctCode,
        location_name: 'Currimao National High School',
        latitude: 17.993217,
        longitude: 120.488902,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, $inspectors),
        watchers_count: 0,
        precincts_count: 0,
        registered_voters_count: 0,
        actual_voters_count: 0,
        ballots_in_box_count: 0,
        unused_ballots_count: 0,
        spoiled_ballots_count: 0,
        void_ballots_count: 0,
    );

    // Mock ElectionStoreInterface
    $mockStore = $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getPrecinct')
        ->with($precinctCode)
        ->andReturn($mockPrecinct)
        ->getMock();

    // Instantiate PrecinctContext
    $context = new PrecinctContext($mockStore, $precinctCode);

    // Accessors
    expect($context->code())->toBe($precinctCode);
    expect($context->location())->toBe('Currimao National High School');
    expect($context->latitude())->toBe(17.993217);
    expect($context->longitude())->toBe(120.488902);

    // Inspectors
    $inspectorsCollection = $context->inspectors();
    expect($inspectorsCollection)->toBeInstanceOf(DataCollection::class);
    expect($inspectorsCollection)->toHaveCount(3);

    // Chairperson
    $chairperson = $context->chairperson();
    expect($chairperson)->toBeInstanceOf(ElectoralInspectorData::class);
    expect($chairperson->name)->toBe('Juan Dela Cruz');
    expect($chairperson->role)->toBe(ElectoralInspectorRole::CHAIRPERSON);

    // Members
    $members = $context->members();
    expect($members)->toBeInstanceOf(DataCollection::class);
    expect($members)->toHaveCount(2);
    expect($members->toCollection()->pluck('name')->all())->toBe(['Maria Santos', 'Pedro Reyes']);
});

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;

it('resolves PrecinctContext via container using request input', function () {
    // Register a test route to simulate request lifecycle
    Route::get('/test-precinct-context', function (Request $request) {
        return app(PrecinctContext::class)->code();
    });

    // Prepare mock precinct
    $precinctCode = 'CURRIMAO-001';

    $mockPrecinct = new PrecinctData(
        code: $precinctCode,
        location_name: 'Currimao National High School',
        latitude: 17.993217,
        longitude: 120.488902,
        electoral_inspectors: new DataCollection(ElectoralInspectorData::class, []),
        watchers_count: 0,
        precincts_count: 0,
        registered_voters_count: 0,
        actual_voters_count: 0,
        ballots_in_box_count: 0,
        unused_ballots_count: 0,
        spoiled_ballots_count: 0,
        void_ballots_count: 0,
    );

    // Mock the ElectionStoreInterface binding
    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getPrecinct')
        ->with($precinctCode)
        ->andReturn($mockPrecinct);

    // Simulate a request with precinct_code input
    $this->get("/test-precinct-context?precinct_code={$precinctCode}")
        ->assertOk()
        ->assertSee($precinctCode);
});

it('puts and merges ballots correctly using putBallot()', function () {
    Log::spy(); // Spy on logger

    $precinctCode = 'CURRIMAO-001';

    // 🧾 Setup positions
    $president = new PositionData('PRESIDENT', 'President', Level::NATIONAL, 1);
    $senator   = new PositionData('SENATOR', 'Senator', Level::NATIONAL, 12);

    // 🗳️ Existing ballot: 1 PRESIDENT vote
    $existingBallot = new BallotData('BALLOT-001', new DataCollection(VoteData::class, [
        new VoteData(new DataCollection(CandidateData::class, [
            new CandidateData('CAND-001', 'Original President', 'OP', $president),
        ])),
    ]));

    // 🗳️ Incoming ballot: 1 PRESIDENT override + 12 SENATORS
    $presidentOverrideVote = new VoteData(new DataCollection(CandidateData::class, [
        new CandidateData('CAND-002', 'Override President', 'NP', $president),
    ]));

    $senatorCandidates = [];
    for ($i = 1; $i <= 12; $i++) {
        $senatorCandidates[] = new CandidateData("SEN-$i", "Senator $i", "S$i", $senator);
    }

    $senatorVote = new VoteData(new DataCollection(CandidateData::class, $senatorCandidates));

    $incomingBallot = new BallotData('BALLOT-001', new DataCollection(VoteData::class, [
        $presidentOverrideVote,
        $senatorVote,
    ]));

    // 🧪 Mock store behavior
    $storeMock = $this->mock(ElectionStoreInterface::class);

    $storeMock->shouldReceive('getPrecinct')
        ->with($precinctCode)
        ->andReturn(new PrecinctData(
            code: $precinctCode,
            location_name: 'Currimao National High School',
            latitude: 0,
            longitude: 0,
            electoral_inspectors: new DataCollection(ElectoralInspectorData::class, []),
            watchers_count: 0,
            precincts_count: 0,
            registered_voters_count: 0,
            actual_voters_count: 0,
            ballots_in_box_count: 0,
            unused_ballots_count: 0,
            spoiled_ballots_count: 0,
            void_ballots_count: 0,
        ));

    $storeMock->shouldReceive('getBallots')
        ->andReturn(new DataCollection(BallotData::class, [$existingBallot]));

    $storeMock->shouldReceive('putBallot')
        ->once()
        ->withArgs(function (BallotData $merged, string $code) use ($precinctCode) {
            expect($merged->votes)->toHaveCount(2); // PRESIDENT + SENATOR

            $presidentVote = $merged->votes->toCollection()->first(
                fn($vote) => $vote->position->code === 'PRESIDENT'
            );
            expect($presidentVote)->not()->toBeNull();
            expect($presidentVote->candidates->first()->alias)->toBe('NP');

            $senatorVote = $merged->votes->toCollection()->first(
                fn($vote) => $vote->position->code === 'SENATOR'
            );
            expect($senatorVote)->not()->toBeNull();
            expect($senatorVote->candidates)->toHaveCount(12);

            expect($code)->toBe($precinctCode);

            return true;
        });

    // 🔧 Call putBallot
    $context = new PrecinctContext($storeMock, $precinctCode);
    $context->putBallot($incomingBallot);

    // ✅ Assert logging
    Log::shouldHaveReceived('info')->once()->with(
        "[Ballot Merge] Updated page for ballot: BALLOT-001",
        \Mockery::on(fn ($context) =>
            $context['precinct'] === $precinctCode
            && $context['votes'] === 2
            && $context['mergedVotes'] === 2
        )
    );
});

it('overwrites vote for the same position on subsequent putBallot()', function () {
    Log::spy(); // Spy on logger

    $precinctCode = 'CURRIMAO-001';

    // 🧾 Setup positions
    $president = new PositionData('PRESIDENT', 'President', Level::NATIONAL, 1);
    $senator   = new PositionData('SENATOR', 'Senator', Level::NATIONAL, 12);

    $senatorCandidate1 = new CandidateData('SEN-001', 'Senator One', 'S1', $senator);
    $senatorCandidate2 = new CandidateData('SEN-002', 'Senator Two', 'S2', $senator);

    $senatorVote1 = new VoteData(new DataCollection(CandidateData::class, [$senatorCandidate1]));
    $senatorVote2 = new VoteData(new DataCollection(CandidateData::class, [$senatorCandidate2]));

    $incomingBallot1 = new BallotData('BALLOT-001', new DataCollection(VoteData::class, [
        $senatorVote1,
    ]));
    $incomingBallot2 = new BallotData('BALLOT-001', new DataCollection(VoteData::class, [
        $senatorVote2,
    ]));

//    dd($incomingBallot2->toArray());
    // 🧪 Mock store behavior
    $storeMock = $this->mock(ElectionStoreInterface::class);

    $storeMock->shouldReceive('getPrecinct')
        ->with($precinctCode)
        ->andReturn(new PrecinctData(
            code: $precinctCode,
            location_name: 'Currimao National High School',
            latitude: 0,
            longitude: 0,
            electoral_inspectors: new DataCollection(ElectoralInspectorData::class, []),
            watchers_count: 0,
            precincts_count: 0,
            registered_voters_count: 0,
            actual_voters_count: 0,
            ballots_in_box_count: 0,
            unused_ballots_count: 0,
            spoiled_ballots_count: 0,
            void_ballots_count: 0,
        ));

    $storeMock->shouldReceive('getBallots')
        ->andReturn(new DataCollection(BallotData::class, [$incomingBallot1]));

    $storeMock->shouldReceive('putBallot')
        ->once()
        ->withArgs(function (BallotData $merged, string $code) use ($precinctCode) {
            expect($merged->votes)->toHaveCount(1); // PRESIDENT + SENATOR

            $senatorVote = $merged->votes->toCollection()->first(
                fn($vote) => $vote->position->code === 'SENATOR'
            );
//            dd($senatorVote->toArray());
            expect($senatorVote)->not()->toBeNull();
            expect($senatorVote->candidates)->toHaveCount(2);

            expect($code)->toBe($precinctCode);

            return true;
        });


    // 🔧 Call putBallot
    $context = new PrecinctContext($storeMock, $precinctCode);
    $context->putBallot($incomingBallot2);
});
