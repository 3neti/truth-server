<?php

use LBHurtado\OMRTemplate\Services\ZoneGenerator;

test('generates zones for candidates', function () {
    $generator = new ZoneGenerator();
    
    $contests = [
        [
            'title' => 'President',
            'candidates' => [
                ['name' => 'Alice Johnson'],
                ['name' => 'Bob Smith'],
            ],
        ],
    ];
    
    $zones = $generator->generateZones($contests);
    
    expect($zones)->toHaveCount(2)
        ->and($zones[0])->toHaveKeys(['id', 'x', 'y', 'width', 'height', 'contest', 'candidate'])
        ->and($zones[0]['contest'])->toBe('President')
        ->and($zones[0]['candidate'])->toBe('Alice Johnson')
        ->and($zones[1]['candidate'])->toBe('Bob Smith');
});

test('generates unique zone IDs from candidate names', function () {
    $generator = new ZoneGenerator();
    
    $contests = [
        [
            'title' => 'President',
            'candidates' => [
                ['name' => 'Alice Johnson'],
                ['name' => 'Bob Smith'],
            ],
        ],
    ];
    
    $zones = $generator->generateZones($contests);
    
    expect($zones[0]['id'])->toBe('PRESIDENT_ALICE_JOHNSON')
        ->and($zones[1]['id'])->toBe('PRESIDENT_BOB_SMITH');
});

test('uses candidate code if provided', function () {
    $generator = new ZoneGenerator();
    
    $contests = [
        [
            'title' => 'President',
            'candidates' => [
                ['name' => 'Alice Johnson', 'code' => 'PRES_A'],
                ['name' => 'Bob Smith', 'code' => 'PRES_B'],
            ],
        ],
    ];
    
    $zones = $generator->generateZones($contests);
    
    expect($zones[0]['id'])->toBe('PRES_A')
        ->and($zones[1]['id'])->toBe('PRES_B');
});

test('generates zones for multiple contests', function () {
    $generator = new ZoneGenerator();
    
    $contests = [
        [
            'title' => 'President',
            'candidates' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ],
        [
            'title' => 'Vice President',
            'candidates' => [
                ['name' => 'Carol'],
            ],
        ],
    ];
    
    $zones = $generator->generateZones($contests);
    
    expect($zones)->toHaveCount(3)
        ->and($zones[0]['contest'])->toBe('President')
        ->and($zones[1]['contest'])->toBe('President')
        ->and($zones[2]['contest'])->toBe('Vice President');
});

test('zones have proper spacing between candidates', function () {
    $generator = new ZoneGenerator();
    
    $contests = [
        [
            'title' => 'President',
            'candidates' => [
                ['name' => 'Alice'],
                ['name' => 'Bob'],
            ],
        ],
    ];
    
    $zones = $generator->generateZones($contests, 'A4', 300);
    
    // Second candidate should be below first
    expect($zones[1]['y'])->toBeGreaterThan($zones[0]['y'])
        ->and($zones[0]['x'])->toBe($zones[1]['x']); // Same X position
});

test('zones have different spacing between contests', function () {
    $generator = new ZoneGenerator();
    
    $contests = [
        [
            'title' => 'President',
            'candidates' => [['name' => 'Alice']],
        ],
        [
            'title' => 'Vice President',
            'candidates' => [['name' => 'Bob']],
        ],
    ];
    
    $zones = $generator->generateZones($contests, 'A4', 300);
    
    // Spacing between contests should be larger than between candidates
    $contestSpacing = $zones[1]['y'] - ($zones[0]['y'] + $zones[0]['height']);
    expect($contestSpacing)->toBeGreaterThan(60); // Should have extra spacing
});

test('returns empty array for empty contests', function () {
    $generator = new ZoneGenerator();
    
    $zones = $generator->generateZones([]);
    
    expect($zones)->toBeEmpty();
});

test('zones scale with different DPI', function () {
    $generator = new ZoneGenerator();
    
    $contests = [[
        'title' => 'President',
        'candidates' => [['name' => 'Alice']],
    ]];
    
    $zones300 = $generator->generateZones($contests, 'A4', 300);
    $zones600 = $generator->generateZones($contests, 'A4', 600);
    
    // At 600 DPI, coordinates should be roughly double
    expect($zones600[0]['x'])->toBeGreaterThan($zones300[0]['x'])
        ->and($zones600[0]['y'])->toBeGreaterThan($zones300[0]['y'])
        ->and($zones600[0]['width'])->toBeGreaterThan($zones300[0]['width']);
});

test('handles candidates without names gracefully', function () {
    $generator = new ZoneGenerator();
    
    $contests = [[
        'title' => 'President',
        'candidates' => [
            [],  // No name
            ['name' => 'Bob'],
        ],
    ]];
    
    $zones = $generator->generateZones($contests);
    
    expect($zones)->toHaveCount(2)
        ->and($zones[0]['id'])->toContain('PRESIDENT')
        ->and($zones[1]['candidate'])->toBe('Bob');
});
