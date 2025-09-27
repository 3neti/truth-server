<?php


use TruthElection\Support\{ElectionStoreInterface, MappingContext};
use TruthElection\Data\{MappingData, MarkData, PositionData};
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;

beforeEach(function () {
    $this->mappingData = new MappingData(
        code: '0102800000',
        location_name: 'Currimao, Ilocos Norte',
        district: 2,
        marks: new DataCollection(MarkData::class, [
            ['key' => 'A1', 'value' => 'LD_001'],
            ['key' => 'A2', 'value' => 'SJ_002'],
            ['key' => 'C3', 'value' => 'MF_003'],
            ['key' => 'J5', 'value' => 'MS_005'],
            ['key' => 'K3', 'value' => 'THE_DARK_KNIGHT_003']
            ])
    );
});

it('resolves MappingContext and exposes values', function () {
    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getMappings')
//        ->with('0102800000')
        ->andReturn($this->mappingData);

    $context = new MappingContext(app(ElectionStoreInterface::class));

    expect($context->code())->toBe('0102800000');
    expect($context->location())->toBe('Currimao, Ilocos Norte');
    expect($context->district())->toBe('2');
    expect($context->getMarks())->toHaveCount(5);
    expect($context->getMark('K3')->value)->toBe('THE_DARK_KNIGHT_003');;
//    expect($context->getMark('X1')->value)->toBeNull();
});

use TruthElection\Data\CandidateData;

it('resolves candidate from mark key', function () {
    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getMappings')
        ->andReturn($this->mappingData)
        ->shouldReceive('getCandidate')
        ->with('THE_DARK_KNIGHT_003')
        ->andReturn( new CandidateData(
            code: 'THE_DARK_KNIGHT_003',
            name: 'Batman',
            alias: 'Batman',
            position: new PositionData(
                code: 'PRESIDENT',
                name: 'President of the Philippines',
                level: Level::NATIONAL,
                count: 1
            )
        ));

    $context = new MappingContext(app(ElectionStoreInterface::class));

    $candidate = $context->resolveCandidate('K3');

    expect($candidate)->toBeInstanceOf(CandidateData::class);
    expect($candidate->code)->toBe('THE_DARK_KNIGHT_003');
    expect($candidate->name)->toBe('Batman');
    expect($candidate->position->level)->toBe(Level::NATIONAL);
});

it('throws exception for invalid mark key when resolving candidate', function () {
    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getMappings')
        ->andReturn($this->mappingData);

    $context = new MappingContext(app(ElectionStoreInterface::class));

    $context->resolveCandidate('X99'); // Not defined in mapping
})->throws(RuntimeException::class, "Mark 'X99' not found.");

use TruthElection\Data\BallotData;

it('resolves a complete ballot from MappingContext', function () {

    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getMappings')
        ->andReturn($this->mappingData)

        ->shouldReceive('getBallotMarkKeys')
        ->with('BALLOT-ABC-001')
        ->andReturn([
            'A1',
            'A2',
            'C3',
            'J5',
            'K3',
        ])

        ->shouldReceive('getCandidate')->with('LD_001')->andReturn(new CandidateData(
            code: 'LD_001',
            name: 'Lucky Day',
            alias: 'Lucky',
            position: new PositionData('LOCAL_DOG', 'Local Dog Catcher', Level::LOCAL, 1)
        ))
        ->shouldReceive('getCandidate')->with('SJ_002')->andReturn(new CandidateData(
            code: 'SJ_002',
            name: 'San Juan',
            alias: 'SJ',
            position: new PositionData('SENATOR', 'Senator', Level::NATIONAL, 12)
        ))
        ->shouldReceive('getCandidate')->with('MF_003')->andReturn(new CandidateData(
            code: 'MF_003',
            name: 'Manny Fresh',
            alias: 'MF',
            position: new PositionData('SENATOR', 'Senator', Level::NATIONAL, 12)
        ))
        ->shouldReceive('getCandidate')->with('MS_005')->andReturn(new CandidateData(
            code: 'MS_005',
            name: 'Maria Santos',
            alias: 'MS',
            position: new PositionData('SENATOR', 'Senator', Level::NATIONAL, 12)
        ))
        ->shouldReceive('getCandidate')->with('THE_DARK_KNIGHT_003')->andReturn(new CandidateData(
            code: 'THE_DARK_KNIGHT_003',
            name: 'Batman',
            alias: 'Batman',
            position: new PositionData('PRESIDENT', 'President', Level::NATIONAL, 1)
        ));

    $context = new MappingContext(app(ElectionStoreInterface::class));
    $ballot = $context->resolveBallot('BALLOT-ABC-001');

    expect($ballot)->toBeInstanceOf(BallotData::class);
    expect($ballot->code)->toBe('BALLOT-ABC-001');
    expect($ballot->votes)->toHaveCount(3); // PRESIDENT, SENATOR, LOCAL_DOG

    $presidentVote = $ballot->votes->first(fn ($v) => $v->candidates->first()->position->code === 'PRESIDENT');
    expect($presidentVote)->not->toBeNull();
    expect($presidentVote->candidates)->toHaveCount(1);

    $senatorVote = $ballot->votes->first(fn ($v) => $v->candidates->first()->position->code === 'SENATOR');
    expect($senatorVote)->not->toBeNull();
    expect($senatorVote->candidates)->toHaveCount(3);

    $localVote = $ballot->votes->first(fn ($v) => $v->candidates->first()->position->code === 'LOCAL_DOG');
    expect($localVote)->not->toBeNull();
    expect($localVote->candidates)->toHaveCount(1);
});

it('flags overvoted positions in the ballot', function () {

    $this->mock(ElectionStoreInterface::class)
        ->shouldReceive('getMappings')
        ->andReturn($this->mappingData)

        ->shouldReceive('getBallotMarkKeys')
        ->with('BALLOT-ABC-002')
        ->andReturn([
            'A1', // LD_001 - LOCAL_DOG
            'A2', // SJ_002 - SENATOR
            'C3', // MF_003 - SENATOR
            'J5', // MS_005 - SENATOR ← third senator (overvote)
            'K3', // THE_DARK_KNIGHT_003 - PRESIDENT
        ])

        ->shouldReceive('getCandidate')->with('LD_001')->andReturn(new CandidateData(
            code: 'LD_001',
            name: 'Lucky Day',
            alias: 'Lucky',
            position: new PositionData('LOCAL_DOG', 'Local Dog Catcher', Level::LOCAL, 1)
        ))
        ->shouldReceive('getCandidate')->with('SJ_002')->andReturn(new CandidateData(
            code: 'SJ_002',
            name: 'San Juan',
            alias: 'SJ',
            position: new PositionData('SENATOR', 'Senator', Level::NATIONAL, 2) // max = 2
        ))
        ->shouldReceive('getCandidate')->with('MF_003')->andReturn(new CandidateData(
            code: 'MF_003',
            name: 'Manny Fresh',
            alias: 'MF',
            position: new PositionData('SENATOR', 'Senator', Level::NATIONAL, 2)
        ))
        ->shouldReceive('getCandidate')->with('MS_005')->andReturn(new CandidateData(
            code: 'MS_005',
            name: 'Maria Santos',
            alias: 'MS',
            position: new PositionData('SENATOR', 'Senator', Level::NATIONAL, 2)
        ))
        ->shouldReceive('getCandidate')->with('THE_DARK_KNIGHT_003')->andReturn(new CandidateData(
            code: 'THE_DARK_KNIGHT_003',
            name: 'Batman',
            alias: 'Batman',
            position: new PositionData('PRESIDENT', 'President', Level::NATIONAL, 1)
        ));

    $context = new MappingContext(app(ElectionStoreInterface::class));
    $ballot = $context->resolveBallot('BALLOT-ABC-002');

    expect($ballot)->toBeInstanceOf(BallotData::class);
    expect($ballot->code)->toBe('BALLOT-ABC-002');

    $senatorVote = $ballot->votes->toCollection()->first(fn ($v) => $v->candidates->first()->position->code === 'SENATOR');
    expect($senatorVote)->toBeNull();

    // Others remain normal
    $presidentVote = $ballot->votes->toCollection()->first(fn ($v) => $v->candidates->first()->position->code === 'PRESIDENT');
    expect($presidentVote->candidates)->toHaveCount(1);

    $localVote = $ballot->votes->toCollection()->first(fn ($v) => $v->candidates->first()->position->code === 'LOCAL_DOG');
    expect($localVote->candidates)->toHaveCount(1);
});
