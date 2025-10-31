<?php

use App\Services\BubbleIdGenerator;
use App\Services\ElectionConfigLoader;

beforeEach(function () {
    // Use simulation config for consistent testing
    putenv('ELECTION_CONFIG_PATH=resources/docs/simulation/config');
});

test('it generates bubble metadata for all marks', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $metadata = $generator->generateBubbleMetadata();

    // Simulation has 56 marks (6 for Punong Barangay + 50 for Sangguniang Barangay)
    expect($metadata)->toHaveCount(56);
});

test('it uses simple grid reference as bubble id', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $metadata = $generator->generateBubbleMetadata();

    // Check that bubble_ids are simple grid references
    expect($metadata)->toHaveKeys(['A1', 'B1', 'B50']);
});

test('it includes correct metadata fields', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $bubble = $generator->getBubbleMetadata('A1');

    expect($bubble)
        ->not->toBeNull()
        ->toHaveKeys(['bubble_id', 'candidate_code', 'position_code', 'candidate_name', 'candidate_alias']);
});

test('it correctly maps bubble to candidate', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $bubble = $generator->getBubbleMetadata('A1');

    expect($bubble['bubble_id'])->toBe('A1')
        ->and($bubble['candidate_code'])->toBe('LD_001')
        ->and($bubble['position_code'])->toBe('PUNONG_BARANGAY-1402702011')
        ->and($bubble['candidate_name'])->toBe('Leonardo DiCaprio')
        ->and($bubble['candidate_alias'])->toBe('LD');
});

test('it correctly maps sangguniang barangay candidates', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    // Test first SB candidate
    $bubble1 = $generator->getBubbleMetadata('B1');
    expect($bubble1['candidate_code'])->toBe('JD_001')
        ->and($bubble1['candidate_name'])->toBe('Johnny Depp')
        ->and($bubble1['position_code'])->toBe('MEMBER_SANGGUNIANG_BARANGAY-1402702011');

    // Test last SB candidate
    $bubble50 = $generator->getBubbleMetadata('B50');
    expect($bubble50['candidate_code'])->toBe('HM_050')
        ->and($bubble50['candidate_name'])->toBe('Helen Mirren')
        ->and($bubble50['position_code'])->toBe('MEMBER_SANGGUNIANG_BARANGAY-1402702011');
});

test('it returns null for nonexistent bubble id', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $bubble = $generator->getBubbleMetadata('Z99');

    expect($bubble)->toBeNull();
});

test('it filters bubbles by position', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    // Get all Punong Barangay bubbles
    $pbBubbles = $generator->getBubblesByPosition('PUNONG_BARANGAY-1402702011');

    expect($pbBubbles)
        ->toHaveCount(6)
        ->toHaveKeys(['A1', 'A6']);

    // Get all Sangguniang Barangay bubbles
    $sbBubbles = $generator->getBubblesByPosition('MEMBER_SANGGUNIANG_BARANGAY-1402702011');

    expect($sbBubbles)
        ->toHaveCount(50)
        ->toHaveKeys(['B1', 'B50']);
});

test('it returns empty array for nonexistent position', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $bubbles = $generator->getBubblesByPosition('NONEXISTENT_POSITION');

    expect($bubbles)
        ->toBeArray()
        ->toHaveCount(0);
});

test('it handles all 56 marks correctly', function () {
    $loader = new ElectionConfigLoader;
    $generator = new BubbleIdGenerator($loader);

    $metadata = $generator->generateBubbleMetadata();

    // Verify all marks have correct structure
    foreach ($metadata as $bubbleId => $bubble) {
        expect($bubble['bubble_id'])->not->toBeEmpty()
            ->and($bubble['candidate_code'])->not->toBeEmpty()
            ->and($bubble['position_code'])->not->toBeEmpty()
            ->and($bubble['candidate_name'])->not->toBeEmpty()
            ->and($bubble['candidate_alias'])->not->toBeEmpty()
            ->and($bubble['bubble_id'])->toBe($bubbleId);
    }
});
