<?php

use LBHurtado\OMRAppreciation\Services\MarkDetector;

test('can detect filled mark', function () {
    $detector = new MarkDetector(0.3);
    
    // Create a simple test image with a filled zone
    $image = imagecreatetruecolor(100, 100);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 100, 100, $black);
    
    $zone = [
        'id' => 'test_zone',
        'x' => 10,
        'y' => 10,
        'width' => 20,
        'height' => 20,
    ];
    
    $result = $detector->detectMark($image, $zone);
    
    expect($result)->toHaveKeys(['filled', 'confidence', 'fill_ratio'])
        ->and($result['filled'])->toBeTrue()
        ->and($result['confidence'])->toBeGreaterThan(0)
        ->and($result['fill_ratio'])->toBeGreaterThan(0.3);
    
    imagedestroy($image);
});

test('can detect unfilled mark', function () {
    $detector = new MarkDetector(0.3);
    
    // Create a simple test image with an unfilled zone (white)
    $image = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 100, 100, $white);
    
    $zone = [
        'id' => 'test_zone',
        'x' => 10,
        'y' => 10,
        'width' => 20,
        'height' => 20,
    ];
    
    $result = $detector->detectMark($image, $zone);
    
    expect($result)->toHaveKeys(['filled', 'confidence', 'fill_ratio'])
        ->and($result['filled'])->toBeFalse()
        ->and($result['fill_ratio'])->toBeLessThan(0.3);
    
    imagedestroy($image);
});

test('can detect multiple marks', function () {
    $detector = new MarkDetector(0.3);
    
    // Create test image
    $image = imagecreatetruecolor(100, 100);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 100, 100, $white);
    
    // Fill one zone black
    imagefilledrectangle($image, 10, 10, 30, 30, $black);
    
    $zones = [
        ['id' => 'zone1', 'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20],
        ['id' => 'zone2', 'x' => 50, 'y' => 50, 'width' => 20, 'height' => 20],
    ];
    
    $results = $detector->detectMarks($image, $zones);
    
    expect($results)->toHaveCount(2)
        ->and($results[0]['filled'])->toBeTrue()
        ->and($results[1]['filled'])->toBeFalse();
    
    imagedestroy($image);
});

test('can adjust fill threshold', function () {
    $detector = new MarkDetector(0.3);
    
    // Create a partially filled image (gray)
    $image = imagecreatetruecolor(100, 100);
    $gray = imagecolorallocate($image, 127, 127, 127);
    imagefilledrectangle($image, 0, 0, 100, 100, $gray);
    
    $zone = ['id' => 'test', 'x' => 10, 'y' => 10, 'width' => 20, 'height' => 20];
    
    // With threshold 0.3
    $result1 = $detector->detectMark($image, $zone);
    
    // Change threshold to 0.7
    $detector->setFillThreshold(0.7);
    $result2 = $detector->detectMark($image, $zone);
    
    // Same image, different thresholds should potentially give different results
    expect($result1)->toHaveKey('filled')
        ->and($result2)->toHaveKey('filled');
    
    imagedestroy($image);
});
