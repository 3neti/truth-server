<?php

use LBHurtado\OMRAppreciation\Services\FiducialDetector;

test('can detect fiducial markers in corners', function () {
    $detector = new FiducialDetector();
    
    // Create test image with 4 black squares in corners
    $image = imagecreatetruecolor(300, 400);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 300, 400, $white);
    
    // Draw fiducials (black squares) in corners
    imagefilledrectangle($image, 10, 10, 40, 40, $black);       // Top-left
    imagefilledrectangle($image, 260, 10, 290, 40, $black);     // Top-right
    imagefilledrectangle($image, 10, 360, 40, 390, $black);     // Bottom-left
    imagefilledrectangle($image, 260, 360, 290, 390, $black);   // Bottom-right
    
    $fiducials = $detector->detectFiducials($image);
    
    expect($fiducials)->toHaveKeys(['top_left', 'top_right', 'bottom_left', 'bottom_right'])
        ->and($fiducials['top_left'])->not->toBeNull()
        ->and($fiducials['top_right'])->not->toBeNull()
        ->and($fiducials['bottom_left'])->not->toBeNull()
        ->and($fiducials['bottom_right'])->not->toBeNull();
    
    // Check that top-left fiducial is in the correct region
    expect($fiducials['top_left']['center_x'])->toBeLessThan(150)
        ->and($fiducials['top_left']['center_y'])->toBeLessThan(200);
    
    // Check that bottom-right fiducial is in the correct region
    expect($fiducials['bottom_right']['center_x'])->toBeGreaterThan(150)
        ->and($fiducials['bottom_right']['center_y'])->toBeGreaterThan(200);
    
    imagedestroy($image);
});

test('throws exception when fiducials are missing', function () {
    $detector = new FiducialDetector();
    
    // Create image with NO fiducials
    $image = imagecreatetruecolor(300, 400);
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefilledrectangle($image, 0, 0, 300, 400, $white);
    
    $detector->detectFiducials($image);
    
    imagedestroy($image);
})->throws(RuntimeException::class, 'Could not detect all 4 fiducial markers');

test('throws exception when only some fiducials are present', function () {
    $detector = new FiducialDetector();
    
    // Create image with only 2 fiducials
    $image = imagecreatetruecolor(300, 400);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 300, 400, $white);
    
    // Draw only 2 fiducials
    imagefilledrectangle($image, 10, 10, 40, 40, $black);       // Top-left
    imagefilledrectangle($image, 260, 10, 290, 40, $black);     // Top-right
    
    $detector->detectFiducials($image);
    
    imagedestroy($image);
})->throws(RuntimeException::class);
