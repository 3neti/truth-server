<?php

use LBHurtado\OMRAppreciation\Services\AppreciationService;

test('can appreciate a complete document', function () {
    // Create a mock scanned image with fiducials and marks
    $image = imagecreatetruecolor(600, 800);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 600, 800, $white);
    
    // Draw fiducials in corners
    imagefilledrectangle($image, 20, 20, 60, 60, $black);
    imagefilledrectangle($image, 540, 20, 580, 60, $black);
    imagefilledrectangle($image, 20, 740, 60, 780, $black);
    imagefilledrectangle($image, 540, 740, 580, 780, $black);
    
    // Draw some filled marks
    imagefilledrectangle($image, 100, 150, 130, 180, $black); // Zone 1 - filled
    // Zone 2 - leave unfilled (white)
    imagefilledrectangle($image, 100, 250, 130, 280, $black); // Zone 3 - filled
    
    // Save test image
    $imagePath = __DIR__ . '/../fixtures/test-scan.png';
    imagepng($image, $imagePath);
    imagedestroy($image);
    
    // Create template data (using array format like omr:generate outputs)
    $templateData = [
        'document_id' => 'TEST-001',
        'template_id' => 'test-ballot',
        'size' => 'A4',
        'dpi' => 300,
        'fiducials' => [
            ['id' => 'top_left', 'x' => 40, 'y' => 40, 'width' => 30, 'height' => 30],
            ['id' => 'top_right', 'x' => 560, 'y' => 40, 'width' => 30, 'height' => 30],
            ['id' => 'bottom_left', 'x' => 40, 'y' => 760, 'width' => 30, 'height' => 30],
            ['id' => 'bottom_right', 'x' => 560, 'y' => 760, 'width' => 30, 'height' => 30],
        ],
        'zones' => [
            ['id' => 'OPTION_A', 'x' => 100, 'y' => 150, 'width' => 30, 'height' => 30],
            ['id' => 'OPTION_B', 'x' => 100, 'y' => 200, 'width' => 30, 'height' => 30],
            ['id' => 'OPTION_C', 'x' => 100, 'y' => 250, 'width' => 30, 'height' => 30],
        ],
    ];
    
    $service = app(AppreciationService::class);
    $result = $service->appreciate($imagePath, $templateData);
    
    // Assert result structure
    expect($result)->toHaveKeys(['document_id', 'template_id', 'fiducials_detected', 'marks', 'summary'])
        ->and($result['document_id'])->toBe('TEST-001')
        ->and($result['template_id'])->toBe('test-ballot')
        ->and($result['marks'])->toHaveCount(3)
        ->and($result['summary']['total_zones'])->toBe(3);
    
    // Assert fiducials were detected
    expect($result['fiducials_detected'])->toHaveKeys(['top_left', 'top_right', 'bottom_left', 'bottom_right']);
    
    // Assert marks have required fields
    expect($result['marks'][0])->toHaveKeys(['id', 'filled', 'confidence', 'fill_ratio']);
    
    // Cleanup
    unlink($imagePath);
});

test('appreciation service throws exception for invalid image', function () {
    $service = app(AppreciationService::class);
    $templateData = ['document_id' => 'TEST', 'template_id' => 'test', 'zones' => []];
    
    $service->appreciate('/non/existent/image.jpg', $templateData);
})->throws(ErrorException::class);

test('appreciation service summary is accurate', function () {
    // Create image with known filled marks
    $image = imagecreatetruecolor(600, 800);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 600, 800, $white);
    
    // Fiducials
    imagefilledrectangle($image, 20, 20, 60, 60, $black);
    imagefilledrectangle($image, 540, 20, 580, 60, $black);
    imagefilledrectangle($image, 20, 740, 60, 780, $black);
    imagefilledrectangle($image, 540, 740, 580, 780, $black);
    
    // Fill exactly 2 out of 4 zones
    imagefilledrectangle($image, 100, 150, 130, 180, $black);
    imagefilledrectangle($image, 100, 250, 130, 280, $black);
    
    $imagePath = __DIR__ . '/../fixtures/test-scan-summary.png';
    imagepng($image, $imagePath);
    imagedestroy($image);
    
    $templateData = [
        'document_id' => 'TEST-002',
        'template_id' => 'test',
        'zones' => [
            ['id' => 'Q1', 'x' => 100, 'y' => 150, 'width' => 30, 'height' => 30],
            ['id' => 'Q2', 'x' => 100, 'y' => 200, 'width' => 30, 'height' => 30],
            ['id' => 'Q3', 'x' => 100, 'y' => 250, 'width' => 30, 'height' => 30],
            ['id' => 'Q4', 'x' => 100, 'y' => 300, 'width' => 30, 'height' => 30],
        ],
    ];
    
    $service = app(AppreciationService::class);
    $result = $service->appreciate($imagePath, $templateData);
    
    expect($result['summary']['total_zones'])->toBe(4)
        ->and($result['summary'])->toHaveKeys(['filled_count', 'unfilled_count', 'average_confidence']);
    
    unlink($imagePath);
});
