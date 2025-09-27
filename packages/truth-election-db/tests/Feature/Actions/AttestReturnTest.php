<?php

use TruthElectionDb\Actions\{AttestReturn, SetupElection, CastBallot, TallyVotes};
use TruthElection\Data\{CandidateData, PositionData, VoteData, SignPayloadData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Support\ElectionStoreInterface;
use TruthElectionDb\Models\Precinct;
use TruthElectionDb\Tests\ResetsElectionStore;
use TruthElectionDb\Models\ElectionReturn;
use Spatie\LaravelData\DataCollection;
use Illuminate\Support\Facades\File;
use TruthElection\Enums\Level;

uses(ResetsElectionStore::class, RefreshDatabase::class)->beforeEach(function () {
    $this->resetElectionStore();

    File::ensureDirectoryExists(base_path('config'));
    File::copy(realpath(__DIR__ . '/../../../config/election.json'), base_path('config/election.json'));
    File::copy(realpath(__DIR__ . '/../../../config/precinct.yaml'), base_path('config/precinct.yaml'));

    SetupElection::run();

    CastBallot::run('BAL-001', collect([
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
    ]));

    CastBallot::run('BAL-002', collect([
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
    ]));

    CastBallot::run('BAL-003', collect([
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
    ]));

    app(TallyVotes::class)->run('CURRIMAO-001');
});

dataset('er', function () {
    return [
        fn() => app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001'),
    ];
});

test('successfully signs election return using AttestReturn', function ($er) {
    $payload = SignPayloadData::fromQrString('BEI:uuid-juan:signature123');
    $response = app(AttestReturn::class)->run($payload);

    expect($response)
        ->message->toBe('Signature saved successfully.')
        ->id->toBe('uuid-juan')
        ->name->toBe('Juan dela Cruz')
        ->role->toBe('chairperson')
        ->signed_at->toBeString();

    $updated = ElectionReturn::where('code', $er->code)->first()?->getData();
    $signed = $updated->signedInspectors();
    $ids = $signed->pluck('id');
    expect($ids)->toContain('uuid-juan');

    $juan = $updated->findSignatory('uuid-juan');
    expect($juan->name)->toBe('Juan dela Cruz');
})->with('er');

test('appends second inspector signature using AttestReturn', function () {
    $er = app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001');

    AttestReturn::run(SignPayloadData::fromQrString('BEI:uuid-juan:signature123'));
    AttestReturn::run(SignPayloadData::fromQrString('BEI:uuid-maria:signature456'));

    $updated = ElectionReturn::where('code', $er->code)->first()?->getData();
    $signed = $updated->signedInspectors();

    expect($signed)->toHaveCount(2);

    $ids = $signed->pluck('id');
    expect($ids)->toContain('uuid-juan')->toContain('uuid-maria');

    $maria = $updated->findSignatory('uuid-maria');
    expect($maria->signature)->toBe('signature456');
});

test('returns 404 for unknown inspector', function () {
    $er = app(ElectionStoreInterface::class)->getElectionReturnByPrecinct('CURRIMAO-001');
    $payload = SignPayloadData::fromQrString('BEI:Z9:wrong');

    AttestReturn::run($payload);
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Inspector with ID [Z9] not found.');

test('returns 404 for missing election return', function () {
    $payload = SignPayloadData::fromQrString('BEI:uuid-juan:legit');

    AttestReturn::run($payload, 'NON-EXISTENT-ER');
})->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class, 'Election return [NON-EXISTENT-ER] not found.')->skip();

test('can attest election return via API', function () {
    $payload = ['payload' => 'BEI:uuid-juan:signature123'];

    $response = $this->postJson(route('attest.return'), $payload);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message', 'id', 'name', 'role', 'signed_at', 'er'
        ])
        ->assertJson([
            'message' => 'Signature saved successfully.',
            'id' => 'uuid-juan',
        ]);
});

test('returns 404 for unknown inspector via API', function () {
    $payload = ['payload' => 'BEI:invalid-id:bad-sign'];

    $response = $this->postJson(route('attest.return'), $payload);

    $response->assertStatus(404);
    $response->assertSeeText('Inspector with ID [invalid-id] not found.');
});

test('returns 404 for missing election return via API', function () {
    // Wipe out ERs to simulate missing data
    \TruthElectionDb\Models\ElectionReturn::query()->delete();

    $payload = ['payload' => 'BEI:uuid-juan:signature123'];

    $response = $this->postJson(route('attest.return'), $payload);

    $response->assertStatus(404);
    $response->assertSeeText('Election return');
});
