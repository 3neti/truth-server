<?php

use TruthElection\Data\{CandidateData, PositionData, VoteData};
use Spatie\LaravelData\DataCollection;
use TruthElection\Enums\Level;

dataset('vote_data', function () {
    $president = new PositionData(
        code: 'PRESIDENT',
        name: 'President of the Philippines',
        level: Level::NATIONAL,
        count: 1
    );

    $senator = new PositionData(
        code: 'SENATOR',
        name: 'Senator',
        level: Level::NATIONAL,
        count: 12
    );

    return [
        'president and senator votes' => [
            new DataCollection(VoteData::class, [
                new VoteData(
                    candidates: new DataCollection(CandidateData::class, [
                        new CandidateData(
                            code: 'CANDIDATE-001',
                            name: 'Juan Dela Cruz',
                            alias: 'JUAN',
                            position: $president
                        ),
                    ])
                ),
                new VoteData(
                    candidates: new DataCollection(CandidateData::class, [
                        new CandidateData(
                            code: 'CANDIDATE-002',
                            name: 'Maria Santos',
                            alias: 'MARIA',
                            position: $senator
                        ),
                        new CandidateData(
                            code: 'CANDIDATE-003',
                            name: 'Pedro Reyes',
                            alias: 'PEDRO',
                            position: $senator
                        ),
                    ])
                )
            ])
        ]
    ];
});

test('VoteData structure and contents', function (DataCollection $votes) {

    expect($votes)->toHaveCount(2);

    $vote1 = $votes[0];
    $vote2 = $votes[1];

    expect($vote1)->toBeInstanceOf(VoteData::class)
        ->and($vote1->position?->code)->toBe('PRESIDENT')
        ->and($vote1->candidates)->toHaveCount(1)
        ->and($vote1->candidates[0]->alias)->toBe('JUAN');

    expect($vote2->position?->code)->toBe('SENATOR')
        ->and($vote2->candidates)->toHaveCount(2)
        ->and($vote2->candidates[1]->alias)->toBe('PEDRO');
})->with('vote_data');
