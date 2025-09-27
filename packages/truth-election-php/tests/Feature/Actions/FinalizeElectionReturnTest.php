<?php

use TruthElection\Data\{CandidateData, ElectionReturnData, PositionData, PrecinctData, SignPayloadData, VoteData};
use TruthElection\Actions\{FinalizeElectionReturn, SignElectionReturn, SubmitBallot, GenerateElectionReturn};
use TruthElection\Enums\{ElectoralInspectorRole, Level};
use TruthElection\Support\ElectionStoreInterface;
use TruthElection\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->tmpDir = base_path('tests/Fixtures/templates_' . uniqid());

    add_er_template();
    add_er_qr_template();

    $this->store = app(ElectionStoreInterface::class);
    $this->store->reset();

    $this->precinct = PrecinctData::from([
        'id' => 'PR001',
        'code' => 'PRECINCT-01',
        'location_name' => 'City Hall',
        'latitude' => 14.5995,
        'longitude' => 120.9842,
        'electoral_inspectors' => [
            ['id' => 'A1', 'name' => 'Alice', 'role' => ElectoralInspectorRole::CHAIRPERSON],
            ['id' => 'B2', 'name' => 'Bob', 'role' => ElectoralInspectorRole::MEMBER],
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
    SubmitBallot::run('BAL-002', $votes2);

    $return = GenerateElectionReturn::run();

    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:A1:sig1'), $return->code);
    SignElectionReturn::run(SignPayloadData::fromQrString('BEI:B2:sig2'), $return->code);

    $this->return = $this->store->getElectionReturn($return->code);
});

function add_er_template()
{
    $templatePath = test()->tmpDir . '/precinct/er';
    @mkdir($templatePath, 0777, true);

    // Set the namespaced path correctly for core
    config()->set('truth-renderer.paths', [
        'core' => test()->tmpDir,
    ]);

    // Save the template as template.hbs inside precinct/er/
    $template = <<<ER
<h2>Vote Tallies</h2>

{{#groupBy tallies key="position_code"}}
  <h3>{{key}}</h3>
  <table style="width: 100%; border-collapse: collapse; margin-bottom: 1em;">
    <thead>
      <tr>
        <th style="text-align: left; border-bottom: 1px solid #ccc;">Candidate</th>
        <th style="text-align: right; border-bottom: 1px solid #ccc;">Votes</th>
      </tr>
    </thead>
    <tbody>
      {{#each items}}
        <tr>
          <td>{{candidate_name}}</td>
          <td style="text-align: right;">{{count}}</td>
        </tr>
      {{/each}}
    </tbody>
  </table>
{{/groupBy}}
ER;

    file_put_contents($templatePath . '/template.hbs', $template);
}

function add_er_qr_template()
{
    $templatePath = test()->tmpDir . '/precinct/er_qr';
    @mkdir($templatePath, 0777, true);

    // Set the namespaced path correctly for core
    config()->set('truth-renderer.paths', [
        'core' => test()->tmpDir,
    ]);

    // Save the template as template.hbs inside precinct/er/
    $template = <<<ER
<h2>Vote Tallies</h2>

{{#groupBy tallyMeta.tallies key="position_code"}}
    <h3>{{key}}</h3>
    <table>
        {{#each items}}
            <tr>
                <td>{{candidate_name}}</td>
                <td>{{count}}</td>
            </tr>
        {{/each}}
    </table>
{{/groupBy}}

<h2>QR Codes</h2>
{{#each qrMeta.qr}}
    <div>{{{this}}}</div>
{{/each}}
ER;

    file_put_contents($templatePath . '/template.hbs', $template);
}

afterEach(function () {
    if (!is_dir($this->tmpDir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($this->tmpDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        $file->isDir()
            ? @rmdir($file->getRealPath())
            : @unlink($file->getRealPath());
    }

    @rmdir($this->tmpDir); // Remove the base tmpDir
    @unlink('election_return.pdf');
    @unlink('election_return_payload.pdf');
});

test('finalize election return passes with complete signatures and valid content', function () {
    $result = FinalizeElectionReturn::run(
        disk: 'local',
        payload: 'minimal',
        maxChars: 1200,
        dir: 'final',
        force: false
    );

    expect($result)->toBeInstanceOf(ElectionReturnData::class);
    expect($result->code)->toEqual($this->return->code);

    expect($result->precinct->code)->toEqual('PRECINCT-01');
    expect($result->precinct->location_name)->toBe('City Hall');

    expect($result->signatures)->toHaveCount(2)
        ->and($result->signedInspectors())->toHaveCount(2);

    expect($result->tallies)->not->toBeEmpty();
    expect($result->ballots)->not->toBeEmpty();

    $lastBallot = $result->with()['last_ballot'];
    expect($lastBallot)->not->toBeNull();
    expect($lastBallot)->toBeInstanceOf(\TruthElection\Data\BallotData::class);
});

//test('finalize election return fails without required signatures', function () {
//    $this->store->reset();
//
//    $precinct = PrecinctData::from([
//        'id' => 'PR002',
//        'code' => 'PRECINCT-02',
//        'location_name' => 'Plaza',
//        'latitude' => 14.6,
//        'longitude' => 121.0,
//        'electoral_inspectors' => [
//            ['id' => 'X1', 'name' => 'Xavier', 'role' => ElectoralInspectorRole::CHAIRPERSON],
//            ['id' => 'Y2', 'name' => 'Yasmin', 'role' => ElectoralInspectorRole::MEMBER],
//        ]
//    ]);
//
//    $this->store->putPrecinct($precinct);
//
//    $votes = collect([
//        new VoteData(
//            candidates: new DataCollection(CandidateData::class, [
//                new CandidateData(code: 'CANDIDATE-007', name: 'Luna Star', alias: 'LUNA', position:  new PositionData(
//                    code: 'PRESIDENT',
//                    name: 'President',
//                    level: Level::NATIONAL,
//                    count: 1
//                )),
//            ])
//        )
//    ]);
//
//    SubmitBallot::run('BAL-003', $votes);
//    GenerateElectionReturn::run();
//    FinalizeElectionReturn::run();
//})->throws(RuntimeException::class, 'Missing required signatures');
//
//test('finalize can be forced to bypass signature check', function () {
//    $this->store->reset();
//
//    $precinct = PrecinctData::from([
//        'id' => 'PR004',
//        'code' => 'PRECINCT-04',
//        'location_name' => 'University Gym',
//        'latitude' => 14.61,
//        'longitude' => 121.03,
//        'electoral_inspectors' => [
//            ['id' => 'Z1', 'name' => 'Zoe', 'role' => ElectoralInspectorRole::CHAIRPERSON],
//            ['id' => 'W2', 'name' => 'Wally', 'role' => ElectoralInspectorRole::MEMBER],
//        ]
//    ]);
//
//    $this->store->putPrecinct($precinct);
//
//    $votes = collect([
//        new VoteData(
//            candidates: new DataCollection(CandidateData::class, [
//                new CandidateData(code: 'CANDIDATE-999', name: 'Gorio', alias: 'GORIO', position: new PositionData(
//                    code: 'MAYOR',
//                    name: 'Mayor',
//                    level: Level::LOCAL,
//                    count: 1
//                )),
//            ])
//        )
//    ]);
//
//    SubmitBallot::run('BAL-005', $votes);
//    GenerateElectionReturn::run();
//
//    $final = FinalizeElectionReturn::run(
//        disk: 'local',
//        payload: 'minimal',
//        maxChars: 1200,
//        dir: 'final',
//        force: true
//    );
//
//    expect($final->signedInspectors())->toHaveCount(0);
//    expect($final->signatures)->toHaveCount(2);
//    expect($final->code)->toBeString(); // to ensure it got finalized
//});
//
//test('finalize fails when election return is missing', function () {
//    $this->store->putPrecinct(
//        PrecinctData::from([
//            'id' => 'PR404',
//            'code' => 'PRECINCT-404',
//            'location_name' => 'Nowhere',
//            'latitude' => 0,
//            'longitude' => 0,
//            'electoral_inspectors' => [],
//        ])
//    );
//
//    FinalizeElectionReturn::run(
//        disk: 'local',
//        payload: 'minimal',
//        maxChars: 1200,
//        dir: 'final',
//        force: false
//    );
//})->throws(RuntimeException::class, 'Election Return for [PRECINCT-404] not found.')->skip();//do not unskip
//
//test('finalize fails when balloting is already closed and not forced', function () {
//    $this->return->precinct->meta['balloting_open'] = false;
//    $this->store->putPrecinct($this->return->precinct); // overwrite
//
//    FinalizeElectionReturn::run(
//        disk: 'local',
//        payload: 'minimal',
//        maxChars: 1200,
//        dir: 'final',
//        force: false
//    );
//})->throws(RuntimeException::class, 'Balloting already closed. Nothing to do.');
//
//test('finalize election return sets closed_at timestamp', function () {
//    $code = $this->precinct->code;
//
//    FinalizeElectionReturn::run(
//        disk: 'local',
//        payload: 'minimal',
//        maxChars: 1200,
//        dir: 'final',
//        force: false
//    );
//
//    $precinct = $this->store->precincts[$code];
//
//    expect($precinct->closed_at)->not->toBeNull()
//        ->and(strtotime($precinct->closed_at))->toBeGreaterThan(0);
//});
