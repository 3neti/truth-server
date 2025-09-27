<?php

use Illuminate\Support\Facades\Storage;
use TruthElection\Data\{
    CandidateData,
    FinalizeErContext,
    PositionData,
    VoteData
};
use TruthElection\Actions\{
    GenerateElectionReturn,
    SubmitBallot
};
use TruthElection\Pipes\RenderElectionReturnPdf;
use TruthElection\Enums\Level;
use TruthElection\Support\InMemoryElectionStore;
use TruthElection\Tests\ResetsElectionStore;
use Spatie\LaravelData\DataCollection;
use TruthRenderer\TruthRendererServiceProvider;

uses(ResetsElectionStore::class)->beforeEach(function () {
    $this->app->register(TruthRendererServiceProvider::class);

    $this->tmpDir = base_path('tests/Fixtures/templates_' . uniqid());
    $templatePath = $this->tmpDir . '/precinct/er';
    @mkdir($templatePath, 0777, true);

    // Set the namespaced path correctly for core
    config()->set('truth-renderer.paths', [
        'core' => $this->tmpDir,
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

test('renders ER as PDF to disk at correct path', function () {
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

    $pipe = new RenderElectionReturnPdf();

    $result = $pipe->handle($context, fn ($ctx) => $ctx);

    expect($result)->toBeInstanceOf(FinalizeErContext::class);

    $expectedPath = "ER-{$this->return->code}/final/election_return.pdf";

    Storage::disk('local')->assertExists($expectedPath);

    $contents = Storage::disk('local')->get($expectedPath);
    expect($contents)->toStartWith('%PDF');
    @unlink('election_return.pdf');
});
