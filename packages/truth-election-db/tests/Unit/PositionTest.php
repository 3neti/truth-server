<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use TruthElection\Data\PositionData;
use TruthElectionDb\Models\Position;
use TruthElection\Enums\Level;

uses(RefreshDatabase::class);

test('it can create a position model via factory', function () {
    $position = Position::factory()->create([
        'code' => 'MAYOR',
        'name' => 'City Mayor',
        'level' => Level::LOCAL,
        'count' => 1,
    ]);

    expect($position)->toBeInstanceOf(Position::class)
        ->and($position->code)->toBe('MAYOR')
        ->and($position->name)->toBe('City Mayor')
        ->and($position->level)->toBe(Level::LOCAL)
        ->and($position->count)->toBe(1);
});

test('it uses string as non-incrementing primary key', function () {
    $position = Position::factory()->make(['code' => 'GOVERNOR']);

    expect($position->getKeyName())->toBe('code')
        ->and($position->getKey())->toBe('GOVERNOR')
        ->and($position->incrementing)->toBeFalse()
        ->and($position->getKeyType())->toBe('string');
});

test('it casts level to Level enum', function () {
    $position = new Position([
        'code' => 'COUNCILOR',
        'name' => 'City Councilor',
        'level' => 'local',
        'count' => 8,
    ]);

    expect($position->level)->toBeInstanceOf(Level::class)
        ->and($position->level)->toEqual(Level::LOCAL);
});

test('it transforms to PositionData via getData', function () {
    $position = Position::factory()->create([
        'code' => 'REP',
        'name' => 'Representative',
        'level' => Level::LOCAL,
        'count' => 1,
    ]);

    $data = $position->getData();

    expect($data)->toBeInstanceOf(\TruthElection\Data\PositionData::class)
        ->and($data->code)->toBe('REP')
        ->and($data->name)->toBe('Representative')
        ->and($data->level)->toBe(Level::LOCAL)
        ->and($data->count)->toBe(1);
});

it('can create a position from PositionData DTO', function () {
    $data = new PositionData(
        code: 'PRESIDENT',
        name: 'President of the Philippines',
        level: Level::NATIONAL,
        count: 1
    );

    $position = Position::fromData($data);

    expect($position)->toBeInstanceOf(Position::class);
    expect($position->code)->toBe('PRESIDENT');
    expect($position->name)->toBe('President of the Philippines');
    expect($position->level)->toBe(Level::NATIONAL);
    expect($position->count)->toBe(1);
});

it('updates an existing position from PositionData DTO', function () {
    // Create initial
    Position::create([
        'code' => 'MAYOR',
        'name' => 'Municipal Mayor',
        'level' => Level::LOCAL,
        'count' => 1,
    ]);

    // Update using DTO
    $data = new PositionData(
        code: 'MAYOR',
        name: 'City Mayor',
        level: Level::LOCAL,
        count: 2
    );

    $position = Position::fromData($data);

    expect($position->name)->toBe('City Mayor');
    expect($position->count)->toBe(2);
});
