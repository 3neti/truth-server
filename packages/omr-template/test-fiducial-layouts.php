#!/usr/bin/env php
<?php

/**
 * Fiducial Layout Test Script
 * 
 * Demonstrates the 3 different fiducial marker layouts for orientation detection.
 * 
 * Usage:
 *   php test-fiducial-layouts.php
 */

require __DIR__ . '/vendor/autoload.php';

use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;
use LBHurtado\OMRTemplate\Services\FiducialOrientationHelper;
use LBHurtado\OMRTemplate\Services\LayoutCompiler;

echo "🎯 FIDUCIAL ORIENTATION LAYOUTS TEST\n";
echo str_repeat("=", 70) . "\n\n";

$generator = new OMRTemplateGenerator();
$helper = new FiducialOrientationHelper();
$compiler = new LayoutCompiler();

// Test each fiducial layout
$layouts = [
    'default' => 'Symmetrical (basic alignment)',
    'asymmetrical_right' => 'Right side offset (orientation detection)',
    'asymmetrical_diagonal' => 'Diagonal pattern (robust detection)',
];

foreach ($layouts as $layout => $description) {
    echo "📋 Testing Layout: {$layout}\n";
    echo "   Description: {$description}\n";
    echo str_repeat("-", 70) . "\n";
    
    // Get fiducials for this layout
    $fiducials = $generator->getFiducialsForLayout($layout);
    
    echo "📍 Fiducial Positions (mm):\n";
    foreach ($fiducials as $fiducial) {
        printf("   %-15s: (%3d, %3d)\n", 
            ucfirst(str_replace('_', ' ', $fiducial['position'])),
            $fiducial['x'],
            $fiducial['y']
        );
    }
    echo "\n";
    
    // Convert to pixels
    $pixelFiducials = $helper->getFiducialsInPixels($fiducials, 300);
    echo "📍 Fiducial Positions (pixels @ 300 DPI):\n";
    foreach ($pixelFiducials as $fiducial) {
        printf("   %-15s: (%4d, %4d)\n",
            ucfirst(str_replace('_', ' ', $fiducial['position'])),
            $fiducial['x'],
            $fiducial['y']
        );
    }
    echo "\n";
    
    // Check if asymmetric
    $isAsymmetric = $helper->isAsymmetricPattern($fiducials);
    echo "🔍 Pattern: " . ($isAsymmetric ? "✅ Asymmetric (orientation detection enabled)" : "⚪ Symmetric (basic alignment only)") . "\n\n";
    
    // Generate PDF
    $data = [
        'identifier' => strtoupper($layout) . '-' . date('His'),
        'bubbles' => [
            ['x' => 30, 'y' => 60],
            ['x' => 30, 'y' => 75],
        ],
    ];
    
    $path = $generator->generateWithFiducialLayout($data, $layout);
    echo "✅ PDF Generated: " . basename($path) . "\n";
    echo "   Size: " . number_format(filesize($path)) . " bytes\n\n";
}

// Generate with Handlebars asymmetrical template
echo str_repeat("=", 70) . "\n";
echo "📋 Testing Handlebars Asymmetrical Template\n";
echo str_repeat("-", 70) . "\n\n";

$data = [
    'identifier' => 'HANDLEBARS-ASYM-' . date('His'),
    'title' => 'Asymmetrical Ballot (Handlebars)',
    'candidates' => [
        ['x' => 30, 'y' => 60, 'label' => 'Candidate A'],
        ['x' => 30, 'y' => 75, 'label' => 'Candidate B'],
    ]
];

$layout = $compiler->compile('ballot-asymmetrical', $data);
echo "📄 Compiled Layout:\n";
echo "   Fiducials: " . count($layout['fiducials']) . "\n";
foreach ($layout['fiducials'] as $idx => $fid) {
    echo "   [{$idx}]: ({$fid['x']}, {$fid['y']})\n";
}
echo "\n";

$path = $generator->generateWithConfig($layout);
echo "✅ PDF Generated: " . basename($path) . "\n";
echo "   Size: " . number_format(filesize($path)) . " bytes\n\n";

// Export calibration data
echo str_repeat("=", 70) . "\n";
echo "📤 Exporting Calibration Data\n";
echo str_repeat("-", 70) . "\n\n";

$asymFiducials = $generator->getFiducialsForLayout('asymmetrical_right');
$calibrationJson = $helper->exportCalibrationJson($asymFiducials, 300);

$calibrationPath = __DIR__ . '/storage/fiducial-calibration.json';
$calibrationDir = dirname($calibrationPath);
if (!is_dir($calibrationDir)) {
    mkdir($calibrationDir, 0755, true);
}
file_put_contents($calibrationPath, $calibrationJson);

echo "✅ Calibration data exported: fiducial-calibration.json\n\n";
echo "Preview:\n";
$calibration = json_decode($calibrationJson, true);
echo "DPI: {$calibration['dpi']}\n";
echo "Conversion Factor: {$calibration['conversion_factor']}\n\n";

echo "Fiducial Positions (for Python OpenCV):\n";
foreach ($calibration['fiducials_px'] as $position => $coords) {
    printf("  %-15s: px(%4d, %4d) = mm(%3d, %3d)\n",
        $position,
        $coords['x'],
        $coords['y'],
        $calibration['fiducials_mm'][$position]['x'],
        $calibration['fiducials_mm'][$position]['y']
    );
}
echo "\n";

// Summary
echo str_repeat("=", 70) . "\n";
echo "📊 SUMMARY\n";
echo str_repeat("=", 70) . "\n\n";

echo "Generated PDFs:\n";
echo "  1. DEFAULT-*.pdf         - Symmetrical (4 corners)\n";
echo "  2. ASYMMETRICAL_RIGHT-*.pdf - Right offset (orientation detection)\n";
echo "  3. ASYMMETRICAL_DIAGONAL-*.pdf - Diagonal (robust detection)\n";
echo "  4. HANDLEBARS-ASYM-*.pdf - From Handlebars template\n\n";

echo "📂 Output Location: storage/app/ballots/\n\n";

echo "🔍 Orientation Detection:\n";
echo "  - Symmetrical: Basic alignment only\n";
echo "  - Asymmetrical: Enables automatic rotation correction\n\n";

echo "📤 Calibration Data: storage/fiducial-calibration.json\n";
echo "   Use this file in Python OpenCV for orientation detection\n\n";

echo "🎯 Recommendation:\n";
echo "   Use 'asymmetrical_right' or 'asymmetrical_diagonal' for production\n";
echo "   ballots to enable robust orientation detection!\n\n";

echo "✅ Test complete!\n";
