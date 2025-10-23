#!/usr/bin/env php
<?php

/**
 * Test Script for TCPDF-based OMR PDF Generation
 * 
 * This script demonstrates and tests the new TCPDF-based PDF generation
 * with fiducial markers, barcodes, and OMR bubbles.
 * 
 * Usage:
 *   php test-tcpdf-generation.php
 */

require __DIR__ . '/vendor/autoload.php';

use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

echo "🧪 TCPDF OMR Template Generation Test\n";
echo str_repeat("=", 50) . "\n\n";

// Initialize generator
$generator = new OMRTemplateGenerator();

// Test 1: Basic PDF with fiducials and barcode
echo "Test 1: Generating basic ballot with fiducials and barcode...\n";
try {
    $data1 = [
        'identifier' => 'TEST-BALLOT-' . date('His'),
        'fiducials' => [
            ['x' => 10, 'y' => 10, 'width' => 10, 'height' => 10],
            ['x' => 190, 'y' => 10, 'width' => 10, 'height' => 10],
            ['x' => 10, 'y' => 277, 'width' => 10, 'height' => 10],
            ['x' => 190, 'y' => 277, 'width' => 10, 'height' => 10],
        ],
        'barcode' => [
            'content' => 'TEST-BALLOT-' . date('His'),
            'type' => 'PDF417',
            'x' => 10,
            'y' => 260,
            'width' => 80,
            'height' => 20,
        ],
        'bubbles' => [
            ['x' => 30, 'y' => 50, 'radius' => 2.5],
            ['x' => 30, 'y' => 60, 'radius' => 2.5],
            ['x' => 30, 'y' => 70, 'radius' => 2.5],
        ],
        'text_elements' => [
            ['x' => 25, 'y' => 30, 'content' => 'Test Ballot', 'font' => 'helvetica', 'style' => 'B', 'size' => 14],
            ['x' => 35, 'y' => 50, 'content' => 'Option A', 'font' => 'helvetica', 'style' => '', 'size' => 10],
            ['x' => 35, 'y' => 60, 'content' => 'Option B', 'font' => 'helvetica', 'style' => '', 'size' => 10],
            ['x' => 35, 'y' => 70, 'content' => 'Option C', 'font' => 'helvetica', 'style' => '', 'size' => 10],
        ],
    ];
    
    $path1 = $generator->generateWithConfig($data1);
    echo "✅ Generated: {$path1}\n";
    echo "   File size: " . filesize($path1) . " bytes\n";
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n";

// Test 2: Load from sample_layout.json
echo "Test 2: Generating from sample_layout.json...\n";
try {
    $sampleLayoutPath = __DIR__ . '/resources/templates/sample_layout.json';
    
    if (!file_exists($sampleLayoutPath)) {
        echo "⚠️  Sample layout file not found, skipping test.\n";
    } else {
        $data2 = json_decode(file_get_contents($sampleLayoutPath), true);
        $data2['identifier'] = 'SAMPLE-' . date('His'); // Update identifier
        
        $path2 = $generator->generateWithConfig($data2);
        echo "✅ Generated: {$path2}\n";
        echo "   File size: " . filesize($path2) . " bytes\n";
    }
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n";

// Test 3: Minimal ballot (fiducials only)
echo "Test 3: Generating minimal ballot (fiducials only)...\n";
try {
    $data3 = [
        'identifier' => 'MINIMAL-' . date('His'),
        'fiducials' => [
            ['x' => 10, 'y' => 10, 'width' => 10, 'height' => 10],
            ['x' => 190, 'y' => 10, 'width' => 10, 'height' => 10],
            ['x' => 10, 'y' => 277, 'width' => 10, 'height' => 10],
            ['x' => 190, 'y' => 277, 'width' => 10, 'height' => 10],
        ],
    ];
    
    $path3 = $generator->generateWithConfig($data3);
    echo "✅ Generated: {$path3}\n";
    echo "   File size: " . filesize($path3) . " bytes\n";
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n";

// Test 4: Full election ballot
echo "Test 4: Generating full election ballot...\n";
try {
    $data4 = [
        'identifier' => 'ELECTION-' . date('His'),
        'fiducials' => [
            ['x' => 10, 'y' => 10, 'width' => 10, 'height' => 10],
            ['x' => 190, 'y' => 10, 'width' => 10, 'height' => 10],
            ['x' => 10, 'y' => 277, 'width' => 10, 'height' => 10],
            ['x' => 190, 'y' => 277, 'width' => 10, 'height' => 10],
        ],
        'barcode' => [
            'content' => 'ELECTION-' . date('His'),
            'type' => 'PDF417',
            'x' => 10,
            'y' => 260,
            'width' => 80,
            'height' => 20,
        ],
        'text_elements' => [
            ['x' => 70, 'y' => 30, 'content' => 'ELECTION BALLOT 2024', 'font' => 'helvetica', 'style' => 'B', 'size' => 16],
            ['x' => 25, 'y' => 45, 'content' => 'President', 'font' => 'helvetica', 'style' => 'B', 'size' => 12],
            ['x' => 25, 'y' => 50, 'content' => 'Vote for one (1)', 'font' => 'helvetica', 'style' => 'I', 'size' => 9],
            ['x' => 40, 'y' => 65, 'content' => 'John Doe', 'font' => 'helvetica', 'style' => '', 'size' => 10],
            ['x' => 40, 'y' => 75, 'content' => 'Jane Smith', 'font' => 'helvetica', 'style' => '', 'size' => 10],
            ['x' => 40, 'y' => 85, 'content' => 'Bob Johnson', 'font' => 'helvetica', 'style' => '', 'size' => 10],
            ['x' => 25, 'y' => 105, 'content' => 'Vice President', 'font' => 'helvetica', 'style' => 'B', 'size' => 12],
            ['x' => 25, 'y' => 110, 'content' => 'Vote for one (1)', 'font' => 'helvetica', 'style' => 'I', 'size' => 9],
            ['x' => 40, 'y' => 125, 'content' => 'Alice Brown', 'font' => 'helvetica', 'style' => '', 'size' => 10],
            ['x' => 40, 'y' => 135, 'content' => 'Charlie Wilson', 'font' => 'helvetica', 'style' => '', 'size' => 10],
        ],
        'bubbles' => [
            // President
            ['x' => 35, 'y' => 65, 'radius' => 2.5, 'label' => 'president_john_doe'],
            ['x' => 35, 'y' => 75, 'radius' => 2.5, 'label' => 'president_jane_smith'],
            ['x' => 35, 'y' => 85, 'radius' => 2.5, 'label' => 'president_bob_johnson'],
            // Vice President
            ['x' => 35, 'y' => 125, 'radius' => 2.5, 'label' => 'vp_alice_brown'],
            ['x' => 35, 'y' => 135, 'radius' => 2.5, 'label' => 'vp_charlie_wilson'],
        ],
    ];
    
    $path4 = $generator->generateWithConfig($data4);
    echo "✅ Generated: {$path4}\n";
    echo "   File size: " . filesize($path4) . " bytes\n";
} catch (Exception $e) {
    echo "❌ Error: {$e->getMessage()}\n";
}

echo "\n";
echo str_repeat("=", 50) . "\n";
echo "🎉 Test complete! Check storage/app/ballots/ for generated PDFs.\n";
echo "\n";
echo "Coordinate Reference:\n";
echo "  - Fiducials: Top-left (10,10), Top-right (190,10)\n";
echo "               Bottom-left (10,277), Bottom-right (190,277)\n";
echo "  - DPI: 300 (1mm ≈ 11.811 pixels)\n";
echo "  - Page: A4 (210mm × 297mm)\n";
echo "\n";
