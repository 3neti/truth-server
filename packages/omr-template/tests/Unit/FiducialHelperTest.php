<?php

use LBHurtado\OMRTemplate\Services\FiducialHelper;

test('generates four fiducial markers', function () {
    $helper = new FiducialHelper;
    
    $fiducials = $helper->generateFiducials('A4', 300);
    
    expect($fiducials)->toHaveCount(4);
    expect($fiducials[0])->toHaveKeys(['id', 'x', 'y', 'width', 'height']);
});

test('fiducial ids are correct', function () {
    $helper = new FiducialHelper;
    
    $fiducials = $helper->generateFiducials('A4', 300);
    
    expect($fiducials[0]['id'])->toBe('top_left');
    expect($fiducials[1]['id'])->toBe('top_right');
    expect($fiducials[2]['id'])->toBe('bottom_left');
    expect($fiducials[3]['id'])->toBe('bottom_right');
});

test('fiducials are positioned at corners', function () {
    $helper = new FiducialHelper;
    
    $fiducials = $helper->generateFiducials('A4', 300);
    
    // Top left should be near origin
    expect($fiducials[0]['x'])->toBe(118);
    expect($fiducials[0]['y'])->toBe(118);
    
    // Top right should be near right edge
    expect($fiducials[1]['x'])->toBeGreaterThan(2000);
    expect($fiducials[1]['y'])->toBe(118);
    
    // Bottom left should be near bottom
    expect($fiducials[2]['x'])->toBe(118);
    expect($fiducials[2]['y'])->toBeGreaterThan(3000);
    
    // Bottom right should be at bottom right
    expect($fiducials[3]['x'])->toBeGreaterThan(2000);
    expect($fiducials[3]['y'])->toBeGreaterThan(3000);
});

test('fiducials scale with DPI', function () {
    $helper = new FiducialHelper;
    
    $fiducials300 = $helper->generateFiducials('A4', 300);
    $fiducials600 = $helper->generateFiducials('A4', 600);
    
    // At 600 DPI, coordinates should be roughly double
    expect($fiducials600[0]['width'])->toBe($fiducials300[0]['width'] * 2);
    expect($fiducials600[0]['height'])->toBe($fiducials300[0]['height'] * 2);
});

test('can convert pixels to millimeters', function () {
    $helper = new FiducialHelper;
    
    // 118 pixels at 300 DPI should be approximately 10mm
    $mm = $helper->pixelsToMm(118, 300);
    
    expect($mm)->toBeGreaterThan(9.5);
    expect($mm)->toBeLessThan(10.5);
});

test('can convert millimeters to pixels', function () {
    $helper = new FiducialHelper;
    
    // 10mm at 300 DPI should be approximately 118 pixels
    $pixels = $helper->mmToPixels(10, 300);
    
    expect($pixels)->toBeGreaterThan(115);
    expect($pixels)->toBeLessThan(120);
});

test('throws exception for unsupported page size', function () {
    $helper = new FiducialHelper;
    
    $helper->generateFiducials('B5', 300);
})->throws(\InvalidArgumentException::class);
