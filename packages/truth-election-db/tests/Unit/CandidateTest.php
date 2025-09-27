<?php

use TruthElection\Data\{CandidateData, PositionData};
use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElectionDb\Models\{Candidate, Position};
use TruthElection\Enums\Level;


uses(RefreshDatabase::class);

it('creates a candidate using factory', function () {
    $candidate = Candidate::factory()->create();

    expect($candidate)->toBeInstanceOf(Candidate::class);
    expect($candidate->getKey())->toEqual($candidate->code);
});

it('sets position_code via setPositionAttribute with Position model', function () {
    $position = Position::factory()->create(['code' => 'MAYOR']);
    $candidate = new Candidate([
        'code' => 'CAND-001',
        'name' => 'John Doe',
        'alias' => 'JOHN',
    ]);

    $candidate->setAttribute('position',  $position);

    expect($candidate->position_code)->toEqual('MAYOR');
});

it('sets position_code via setPositionAttribute with string', function () {
    $candidate = new Candidate([
        'code' => 'CAND-002',
        'name' => 'Jane Doe',
        'alias' => 'JANE',
    ]);

    $candidate->setAttribute('position',  'GOVERNOR');

    expect($candidate->position_code)->toEqual('GOVERNOR');
});

it('returns position as array from getPositionAttribute', function () {
    $position = Position::factory()->create([
        'code' => 'PRES',
        'name' => 'President',
        'level' => Level::NATIONAL,
        'count' => 1,
    ]);

    $candidate = Candidate::factory()->create([
        'position_code' => 'PRES',
    ]);

    $array = $candidate->position;

    expect($array)->toBeArray();
    expect($array['code'])->toEqual('PRES');
    expect($array['name'])->toEqual('President');
});

it('returns data object via getData()', function () {
    $position = Position::factory()->create(['code' => 'VICE']);
    $candidate = Candidate::factory()->create([
        'code' => 'CAND-123',
        'name' => 'Vice Lord',
        'alias' => 'VICE',
        'position_code' => 'VICE',
    ]);

    $data = $candidate->getData();

    expect($data)->toBeInstanceOf(CandidateData::class);
    expect($data->code)->toEqual('CAND-123');
    expect($data->alias)->toEqual('VICE');
});

it('can create a candidate from CandidateData DTO', function () {
    $positionData = new PositionData(
        code: 'SENATOR',
        name: 'Senator of the Republic',
        level: Level::NATIONAL,
        count: 12
    );

    $position = Position::fromData($positionData);

    $candidateData = new CandidateData(
        code: 'SEN001',
        name: 'Juan Dela Cruz',
        alias: 'JUAN',
        position: $positionData
    );

    $candidate = Candidate::fromData($candidateData);

    expect($candidate)->toBeInstanceOf(Candidate::class);
    expect($candidate->code)->toBe('SEN001');
    expect($candidate->name)->toBe('Juan Dela Cruz');
    expect($candidate->alias)->toBe('JUAN');
    expect($candidate->position_code)->toBe('SENATOR');
    expect($candidate->position)->not()->toBeNull();
    expect($candidate->position['name'])->toBe('Senator of the Republic');
});

it('updates an existing candidate from CandidateData DTO', function () {
    Position::fromData(new PositionData(
        code: 'MAYOR',
        name: 'Municipal Mayor',
        level: Level::LOCAL,
        count: 1
    ));

    Candidate::create([
        'code' => 'MAY001',
        'name' => 'Jane Doe',
        'alias' => 'JANE',
        'position_code' => 'MAYOR',
    ]);

    $updatedData = new CandidateData(
        code: 'MAY001',
        name: 'Jane A. Doe',
        alias: 'JANEY',
        position: new PositionData(
            code: 'MAYOR',
            name: 'City Mayor',
            level: Level::LOCAL,
            count: 1
        )
    );

    $updated = Candidate::fromData($updatedData);

    expect($updated->name)->toBe('Jane A. Doe');
    expect($updated->alias)->toBe('JANEY');
    expect($updated->position_code)->toBe('MAYOR');
    expect($updated->position['name'])->toBe('City Mayor'); // name updated in position
});
