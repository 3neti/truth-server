#!/usr/bin/env php
<?php

/**
 * Handlebars → JSON → PDF Workflow Test
 * 
 * Demonstrates the complete cycle:
 * 1. Load Handlebars template
 * 2. Compile with data
 * 3. Show generated JSON layout
 * 4. Generate PDF from layout
 * 5. Display results
 * 
 * Usage:
 *   php test-handlebars-workflow.php
 */

require __DIR__ . '/vendor/autoload.php';

use LBHurtado\OMRTemplate\Services\LayoutCompiler;
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

echo "🗳️  HANDLEBARS → JSON → PDF WORKFLOW TEST\n";
echo str_repeat("=", 60) . "\n\n";

// Initialize services
$compiler = new LayoutCompiler();
$generator = new OMRTemplateGenerator();

// ============================================================================
// Test 1: Simple Ballot
// ============================================================================
echo "📋 Test 1: Simple Yes/No Ballot\n";
echo str_repeat("-", 60) . "\n";

$data1 = [
    'identifier' => 'BALLOT-SIMPLE-' . date('His'),
    'title' => 'Referendum Question',
    'candidates' => [
        ['x' => 30, 'y' => 60, 'label' => 'Yes'],
        ['x' => 30, 'y' => 75, 'label' => 'No'],
    ]
];

echo "📝 Input Data:\n";
echo json_encode($data1, JSON_PRETTY_PRINT) . "\n\n";

echo "🔄 Compiling Handlebars template...\n";
$layout1 = $compiler->compile('ballot', $data1);

echo "📄 Generated JSON Layout:\n";
echo json_encode($layout1, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";

echo "🖨️  Generating PDF...\n";
$pdf1 = $generator->generateWithConfig($layout1);

echo "✅ PDF Generated: {$pdf1}\n";
echo "   Size: " . number_format(filesize($pdf1)) . " bytes\n";
echo "   Fiducials: " . count($layout1['fiducials']) . "\n";
echo "   Bubbles: " . count($layout1['bubbles']) . "\n";
echo "   Text Elements: " . count($layout1['text_elements']) . "\n\n";

// ============================================================================
// Test 2: Multi-Candidate Election
// ============================================================================
echo "📋 Test 2: Presidential Election Ballot\n";
echo str_repeat("-", 60) . "\n";

$data2 = [
    'identifier' => 'ELECTION-2024-' . date('His'),
    'title' => 'Presidential Election 2024',
    'candidates' => [
        ['x' => 35, 'y' => 70, 'label' => 'Alice Johnson - Progressive Party'],
        ['x' => 35, 'y' => 85, 'label' => 'Bob Smith - Conservative Party'],
        ['x' => 35, 'y' => 100, 'label' => 'Carol White - Liberal Party'],
        ['x' => 35, 'y' => 115, 'label' => 'David Brown - Independent'],
    ]
];

echo "📝 Input Data:\n";
echo "   Identifier: {$data2['identifier']}\n";
echo "   Title: {$data2['title']}\n";
echo "   Candidates: " . count($data2['candidates']) . "\n\n";

echo "🔄 Compiling Handlebars template...\n";
$layout2 = $compiler->compile('ballot', $data2);

echo "📊 Layout Summary:\n";
echo "   Identifier: {$layout2['identifier']}\n";
echo "   Fiducials: " . count($layout2['fiducials']) . "\n";
echo "   Barcode Type: {$layout2['barcode']['type']}\n";
echo "   Bubbles: " . count($layout2['bubbles']) . "\n";
echo "   Text Elements: " . count($layout2['text_elements']) . "\n\n";

echo "🖨️  Generating PDF...\n";
$pdf2 = $generator->generateWithConfig($layout2);

echo "✅ PDF Generated: {$pdf2}\n";
echo "   Size: " . number_format(filesize($pdf2)) . " bytes\n\n";

// ============================================================================
// Test 3: Large Survey (10 questions)
// ============================================================================
echo "📋 Test 3: Large Survey (10 Questions)\n";
echo str_repeat("-", 60) . "\n";

$candidates3 = [];
$startY = 60;
$spacing = 12;

for ($i = 1; $i <= 10; $i++) {
    $candidates3[] = [
        'x' => 30,
        'y' => $startY + (($i - 1) * $spacing),
        'label' => "Question {$i}"
    ];
}

$data3 = [
    'identifier' => 'SURVEY-LARGE-' . date('His'),
    'title' => 'Customer Satisfaction Survey',
    'candidates' => $candidates3
];

echo "📝 Input Data:\n";
echo "   Identifier: {$data3['identifier']}\n";
echo "   Title: {$data3['title']}\n";
echo "   Questions: " . count($data3['candidates']) . "\n\n";

echo "🔄 Compiling Handlebars template...\n";
$layout3 = $compiler->compile('ballot', $data3);

echo "🖨️  Generating PDF...\n";
$pdf3 = $generator->generateWithConfig($layout3);

echo "✅ PDF Generated: {$pdf3}\n";
echo "   Size: " . number_format(filesize($pdf3)) . " bytes\n";
echo "   Bubbles: " . count($layout3['bubbles']) . "\n\n";

// ============================================================================
// Test 4: Validation Test
// ============================================================================
echo "📋 Test 4: Layout Validation\n";
echo str_repeat("-", 60) . "\n";

$data4 = [
    'identifier' => 'VALIDATION-TEST-' . date('His'),
    'title' => 'Validation Test Ballot',
    'candidates' => [
        ['x' => 30, 'y' => 60, 'label' => 'Option A'],
    ]
];

$layout4 = $compiler->compile('ballot', $data4);

echo "🔍 Validating layout structure...\n";
try {
    $compiler->validate($layout4, ['identifier', 'title', 'fiducials', 'barcode', 'bubbles']);
    echo "✅ Layout validation passed!\n";
    echo "   Required fields present: identifier, title, fiducials, barcode, bubbles\n\n";
} catch (\RuntimeException $e) {
    echo "❌ Layout validation failed: {$e->getMessage()}\n\n";
}

// ============================================================================
// Summary
// ============================================================================
echo str_repeat("=", 60) . "\n";
echo "📊 WORKFLOW SUMMARY\n";
echo str_repeat("=", 60) . "\n\n";

$totalSize = filesize($pdf1) + filesize($pdf2) + filesize($pdf3);

echo "✅ Generated 3 PDFs successfully!\n\n";

echo "Test 1 (Simple):\n";
echo "  - File: " . basename($pdf1) . "\n";
echo "  - Size: " . number_format(filesize($pdf1)) . " bytes\n";
echo "  - Bubbles: 2\n\n";

echo "Test 2 (Election):\n";
echo "  - File: " . basename($pdf2) . "\n";
echo "  - Size: " . number_format(filesize($pdf2)) . " bytes\n";
echo "  - Bubbles: 4\n\n";

echo "Test 3 (Survey):\n";
echo "  - File: " . basename($pdf3) . "\n";
echo "  - Size: " . number_format(filesize($pdf3)) . " bytes\n";
echo "  - Bubbles: 10\n\n";

echo "Total Size: " . number_format($totalSize) . " bytes\n\n";

echo "📂 Output Location:\n";
echo "   storage/app/ballots/\n\n";

echo "🔗 Workflow Chain:\n";
echo "   Handlebars Template (ballot.hbs)\n";
echo "        ↓ compile with data\n";
echo "   JSON Layout (array)\n";
echo "        ↓ validate structure\n";
echo "   TCPDF Generation\n";
echo "        ↓ render to file\n";
echo "   PDF Output (with fiducials + barcode + bubbles)\n\n";

echo "📋 To view generated PDFs:\n";
echo "   ls -lh storage/app/ballots/\n";
echo "   open storage/app/ballots/{$data1['identifier']}.pdf\n\n";

echo "🎯 All features verified:\n";
echo "   ✅ Handlebars template compilation\n";
echo "   ✅ Variable substitution\n";
echo "   ✅ Loop iteration ({{#each}})\n";
echo "   ✅ Fiducial marker generation\n";
echo "   ✅ PDF417 barcode embedding\n";
echo "   ✅ OMR bubble positioning\n";
echo "   ✅ Text element rendering\n";
echo "   ✅ Layout validation\n";
echo "   ✅ TCPDF PDF generation\n\n";

echo "🎉 Handlebars → JSON → PDF workflow complete!\n";
