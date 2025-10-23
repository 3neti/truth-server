<?php

use LBHurtado\OMRAppreciation\Services\OMRAppreciator;

test('detects filled marks with high accuracy', function () {
    // Use the confidence test fixtures
    $imagePath = base_path('storage/omr-test-confidence/ballot-marked.jpg');
    $templatePath = base_path('storage/omr-test-confidence/template.json');
    
    // Skip if test fixtures don't exist
    if (! file_exists($imagePath) || ! file_exists($templatePath)) {
        $this->markTestSkipped('Test fixtures not found. Run: ./test-omr-confidence.sh');
    }
    
    $appreciator = app(OMRAppreciator::class);
    $result = $appreciator->run($imagePath, $templatePath, 0.25);
    
    // Expected results based on test setup
    // HIGH_CONF_1, HIGH_CONF_2, MEDIUM_CONF_1, MEDIUM_CONF_2 should be filled
    // LOW_CONF_1 might be detected depending on threshold
    // UNFILLED should not be filled
    
    expect($result)->toHaveKeys(['document_id', 'template_id', 'results']);
    expect($result['document_id'])->toBe('CONFIDENCE-TEST-001');
    expect($result['results'])->toHaveCount(6);
    
    // Check specific marks
    $results = collect($result['results'])->keyBy('id');
    
    // High confidence marks should be detected as filled
    expect($results['HIGH_CONF_1']['filled'])->toBeTrue();
    expect($results['HIGH_CONF_2']['filled'])->toBeTrue();
    expect($results['HIGH_CONF_1']['confidence'])->toBeGreaterThan(0.7);
    expect($results['HIGH_CONF_2']['confidence'])->toBeGreaterThan(0.7);
    
    // Unfilled mark should not be detected
    expect($results['UNFILLED']['filled'])->toBeFalse();
    expect($results['UNFILLED']['confidence'])->toBeGreaterThan(0.6);
    
    // Overall accuracy: at least 4 correct detections out of 6
    $correctDetections = 0;
    $expectedFilled = ['HIGH_CONF_1', 'HIGH_CONF_2', 'MEDIUM_CONF_1', 'MEDIUM_CONF_2'];
    $expectedUnfilled = ['UNFILLED'];
    
    foreach ($expectedFilled as $id) {
        if ($results[$id]['filled'] === true) {
            $correctDetections++;
        }
    }
    
    foreach ($expectedUnfilled as $id) {
        if ($results[$id]['filled'] === false) {
            $correctDetections++;
        }
    }
    
    $accuracy = $correctDetections / 5; // 5 clearly expected results
    expect($accuracy)->toBeGreaterThanOrEqual(0.8); // 80% accuracy minimum
});

test('returns proper result structure with metrics', function () {
    $imagePath = base_path('storage/omr-test-confidence/ballot-marked.jpg');
    $templatePath = base_path('storage/omr-test-confidence/template.json');
    
    if (! file_exists($imagePath) || ! file_exists($templatePath)) {
        $this->markTestSkipped('Test fixtures not found');
    }
    
    $appreciator = app(OMRAppreciator::class);
    $result = $appreciator->run($imagePath, $templatePath);
    
    // Check result structure
    expect($result)->toHaveKeys(['document_id', 'template_id', 'results']);
    
    // Check each mark has required fields
    foreach ($result['results'] as $mark) {
        expect($mark)->toHaveKeys([
            'id',
            'contest',
            'code',
            'candidate',
            'filled',
            'fill_ratio',
            'confidence',
            'quality',
        ]);
        
        expect($mark['quality'])->toHaveKeys([
            'uniformity',
            'mean_darkness',
            'std_dev',
        ]);
        
        // Validate metric ranges
        expect($mark['fill_ratio'])->toBeBetween(0, 1);
        expect($mark['confidence'])->toBeBetween(0, 1);
        expect($mark['quality']['uniformity'])->toBeBetween(0, 1);
        expect($mark['quality']['mean_darkness'])->toBeBetween(0, 1);
    }
});

test('applies threshold correctly', function () {
    $imagePath = base_path('storage/omr-test-confidence/ballot-marked.jpg');
    $templatePath = base_path('storage/omr-test-confidence/template.json');
    
    if (! file_exists($imagePath) || ! file_exists($templatePath)) {
        $this->markTestSkipped('Test fixtures not found');
    }
    
    $appreciator = app(OMRAppreciator::class);
    
    // Test with strict threshold
    $strictResult = $appreciator->run($imagePath, $templatePath, 0.40);
    $strictFilled = collect($strictResult['results'])->filter(fn ($r) => $r['filled'])->count();
    
    // Test with lenient threshold
    $lenientResult = $appreciator->run($imagePath, $templatePath, 0.20);
    $lenientFilled = collect($lenientResult['results'])->filter(fn ($r) => $r['filled'])->count();
    
    // Lenient should detect same or more marks than strict
    expect($lenientFilled)->toBeGreaterThanOrEqual($strictFilled);
});
