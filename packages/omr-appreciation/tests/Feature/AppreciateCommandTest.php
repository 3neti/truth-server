<?php

test('appreciate command works with valid inputs', function () {
    // Create a test image
    $image = imagecreatetruecolor(600, 800);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 600, 800, $white);
    
    // Fiducials
    imagefilledrectangle($image, 20, 20, 60, 60, $black);
    imagefilledrectangle($image, 540, 20, 580, 60, $black);
    imagefilledrectangle($image, 20, 740, 60, 780, $black);
    imagefilledrectangle($image, 540, 740, 580, 780, $black);
    
    // Some filled marks
    imagefilledrectangle($image, 100, 150, 130, 180, $black);
    
    $imagePath = __DIR__ . '/../fixtures/cmd-test.png';
    imagepng($image, $imagePath);
    imagedestroy($image);
    
    // Create template JSON
    $templateData = [
        'document_id' => 'CMD-TEST-001',
        'template_id' => 'test',
        'zones' => [
            ['id' => 'Q1', 'x' => 100, 'y' => 150, 'width' => 30, 'height' => 30],
            ['id' => 'Q2', 'x' => 100, 'y' => 200, 'width' => 30, 'height' => 30],
        ],
    ];
    
    $templatePath = __DIR__ . '/../fixtures/cmd-template.json';
    file_put_contents($templatePath, json_encode($templateData));
    
    // Run command
    $this->artisan('omr:appreciate', [
        'image' => $imagePath,
        'template' => $templatePath,
    ])->assertSuccessful();
    
    // Cleanup
    unlink($imagePath);
    unlink($templatePath);
});

test('appreciate command fails with missing image', function () {
    $templateData = ['document_id' => 'TEST', 'template_id' => 'test', 'zones' => []];
    $templatePath = __DIR__ . '/../fixtures/cmd-template-2.json';
    file_put_contents($templatePath, json_encode($templateData));
    
    $this->artisan('omr:appreciate', [
        'image' => '/non/existent/image.jpg',
        'template' => $templatePath,
    ])->assertFailed();
    
    unlink($templatePath);
});

test('appreciate command can save output to file', function () {
    // Create test image
    $image = imagecreatetruecolor(600, 800);
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    imagefilledrectangle($image, 0, 0, 600, 800, $white);
    
    // Fiducials
    imagefilledrectangle($image, 20, 20, 60, 60, $black);
    imagefilledrectangle($image, 540, 20, 580, 60, $black);
    imagefilledrectangle($image, 20, 740, 60, 780, $black);
    imagefilledrectangle($image, 540, 740, 580, 780, $black);
    
    $imagePath = __DIR__ . '/../fixtures/cmd-test-output.png';
    imagepng($image, $imagePath);
    imagedestroy($image);
    
    $templateData = [
        'document_id' => 'OUTPUT-TEST',
        'template_id' => 'test',
        'zones' => [['id' => 'Q1', 'x' => 100, 'y' => 150, 'width' => 30, 'height' => 30]],
    ];
    
    $templatePath = __DIR__ . '/../fixtures/cmd-template-output.json';
    file_put_contents($templatePath, json_encode($templateData));
    
    $outputPath = __DIR__ . '/../fixtures/appreciation-result.json';
    
    $this->artisan('omr:appreciate', [
        'image' => $imagePath,
        'template' => $templatePath,
        '--output' => $outputPath,
    ])->assertSuccessful();
    
    // Assert output file was created
    expect(file_exists($outputPath))->toBeTrue();
    
    // Assert output is valid JSON
    $result = json_decode(file_get_contents($outputPath), true);
    expect($result)->toHaveKeys(['document_id', 'template_id', 'marks', 'summary'])
        ->and($result['document_id'])->toBe('OUTPUT-TEST');
    
    // Cleanup
    unlink($imagePath);
    unlink($templatePath);
    unlink($outputPath);
});
