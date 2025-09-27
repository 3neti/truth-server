<?php

use TruthElection\Data\{CandidateData, PositionData, PrecinctData, SignPayloadData, VoteData, FinalizeErContext};
use TruthElection\Actions\{SubmitBallot, GenerateElectionReturn, SignElectionReturn};
use TruthElection\Enums\{ElectoralInspectorRole, Level};
use TruthElection\Pipes\RenderElectionReturnPayloadPdf;
use TruthElection\Support\ElectionStoreInterface;
use TruthRenderer\TruthRendererServiceProvider;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelData\DataCollection;

uses()->beforeEach(function () {
    $this->app->register(TruthRendererServiceProvider::class);

    // Setup fake disk and temporary template directory
    Storage::fake('local');
    $this->tmpDir = base_path('tests/Fixtures/templates_' . uniqid());
    $templatePath = $this->tmpDir . '/precinct/er_qr';
    @mkdir($templatePath, 0777, true);

    config()->set('truth-renderer.paths', [
        'core' => $this->tmpDir,
    ]);

    // Create the .hbs template
    file_put_contents($templatePath . '/template.hbs', <<<HBS
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
HBS
    );

    // Reset the store using the configured ElectionStoreInterface
    $this->store = app(ElectionStoreInterface::class);
    $this->store->reset();

    // Seed the precinct
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

    // Submit ballots
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
                new CandidateData(code: 'CANDIDATE-002', name: 'Maria Santos', alias: 'MARIA', position: $senator = new PositionData(
                    code: 'SENATOR',
                    name: 'Senator',
                    level: Level::NATIONAL,
                    count: 12
                )),
                new CandidateData(code: 'CANDIDATE-003', name: 'Pedro Reyes', alias: 'PEDRO', position: $senator),
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
                new CandidateData(code: 'CANDIDATE-002', name: 'Maria Santos', alias: 'MARIA', position: $senator),
                new CandidateData(code: 'CANDIDATE-005', name: 'Andres Bonifacio', alias: 'ANDRES', position: $senator),
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

test('renders ER QR+Tally PDF payload to disk', function () {
    $payload = file_get_contents('tests/Fixtures/ER-317537-payload.json');

    $context = new FinalizeErContext(
        precinct: $this->precinct,
        er: $this->return,
        disk: 'local',
        folder: 'ER-317537/final',
        payload: $payload,
        maxChars: 8000,
        force: false,
    );

    $pipe = new RenderElectionReturnPayloadPdf();

    $result = $pipe->handle($context, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);

    $expectedPath = "ER-317537/final/election_return_payload.pdf";
    Storage::disk('local')->assertExists($expectedPath);

    $pdf = Storage::disk('local')->get($expectedPath);
    expect($pdf)->toStartWith('%PDF');
});
