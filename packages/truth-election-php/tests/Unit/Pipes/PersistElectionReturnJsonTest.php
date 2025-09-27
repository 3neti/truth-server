<?php

use Illuminate\Support\Facades\Storage;
use TruthElection\Data\{CandidateData, FinalizeErContext, PositionData, SignPayloadData, VoteData};
use TruthElection\Actions\{GenerateElectionReturn, SignElectionReturn, SubmitBallot};
use TruthElection\Pipes\PersistElectionReturnJson;
use TruthElection\Enums\Level;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->store = InMemoryElectionStore::instance();
    $this->store->reset();

    $this->precinctCode = 'PRECINCT-01';
    $this->precinct = \TruthElection\Data\PrecinctData::from([
        'code' => $this->precinctCode,
        'location_name' => 'City Hall',
        'latitude' => 0,
        'longitude' => 0,
        'electoral_inspectors' => [],
    ]);

    $this->store->putPrecinct($this->precinct);

    $votes = collect([
        new VoteData(
            candidates: new DataCollection(CandidateData::class, [
                new CandidateData(
                    code: 'CAND-001',
                    name: 'Candidate A',
                    alias: 'CA',
                    position: new PositionData('PRESIDENT', 'President', Level::NATIONAL, 1)
                )
            ])
        )
    ]);

    SubmitBallot::run('BAL-001', $votes);

    $this->return = GenerateElectionReturn::run($this->precinctCode);
});

test('persists ER JSON to disk at correct path', function () {
    Storage::fake('local');

    $context = new FinalizeErContext(
        precinct: $this->precinct,
        er: $this->return,
        disk: 'local',
        folder: 'ER-' . $this->return->code . '/final',
        payload: '{}',
        maxChars: 8000,
        force: false,
    );

    $pipe = new PersistElectionReturnJson();

    $result = $pipe->handle($context, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);

    Storage::disk('local')->assertExists("ER-{$this->return->code}/final/election_return.json");

    $content = Storage::disk('local')->get("ER-{$this->return->code}/final/election_return.json");
    $json = json_decode($content, true);

    expect($json)->toBeArray()
        ->and($json['code'])->toEqual($this->return->code);
});
