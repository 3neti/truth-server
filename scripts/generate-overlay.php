#!/usr/bin/env php
<?php
/**
 * Generate overlay with candidate names
 * Usage: php scripts/generate-overlay.php <image> <results> <coords> <output>
 */

require __DIR__ . '/../vendor/autoload.php';

use Tests\Helpers\OMRSimulator;
use App\Models\TemplateData;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Parse arguments
if ($argc < 5) {
    fwrite(STDERR, "Usage: php generate-overlay.php <image> <results.json> <coordinates.json> <output.png>\n");
    exit(1);
}

$imagePath = $argv[1];
$resultsPath = $argv[2];
$coordsPath = $argv[3];
$outputPath = $argv[4];

// Validate files exist
if (!file_exists($imagePath)) {
    fwrite(STDERR, "Error: Image not found: {$imagePath}\n");
    exit(1);
}

if (!file_exists($resultsPath)) {
    fwrite(STDERR, "Error: Results not found: {$resultsPath}\n");
    exit(1);
}

if (!file_exists($coordsPath)) {
    fwrite(STDERR, "Error: Coordinates not found: {$coordsPath}\n");
    exit(1);
}

// Load data
$results = json_decode(file_get_contents($resultsPath), true);
$coordinates = json_decode(file_get_contents($coordsPath), true);

if (!$results || !isset($results['results'])) {
    fwrite(STDERR, "Error: Invalid results JSON\n");
    exit(1);
}

if (!$coordinates) {
    fwrite(STDERR, "Error: Invalid coordinates JSON\n");
    exit(1);
}

// Load questionnaire data for candidate names
$questionnaireData = null;
try {
    $data = TemplateData::where('document_id', 'PH-2025-QUESTIONNAIRE-CURRIMAO-001')->first();
    if ($data) {
        $questionnaireData = $data->json_data;
    }
} catch (Exception $e) {
    // If database not available, continue without candidate names
    fwrite(STDERR, "Warning: Could not load questionnaire data: {$e->getMessage()}\n");
}

// Generate overlay
try {
    $overlayPath = OMRSimulator::createOverlay(
        $imagePath,
        $results['results'],
        $coordinates,
        [
            'scenario' => 'rotation',
            'show_legend' => true,
            'show_unfilled' => false,
            'output_path' => $outputPath,
            'questionnaire' => $questionnaireData,
        ]
    );
    
    if ($overlayPath && file_exists($overlayPath)) {
        fwrite(STDERR, "Overlay created: {$overlayPath}\n");
        exit(0);
    } else {
        fwrite(STDERR, "Error: Overlay generation failed\n");
        exit(1);
    }
} catch (Exception $e) {
    fwrite(STDERR, "Error: {$e->getMessage()}\n");
    exit(1);
}
