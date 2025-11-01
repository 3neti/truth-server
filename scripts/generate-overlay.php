#!/usr/bin/env php
<?php
/**
 * Generate overlay with candidate names
 * Usage: php scripts/generate-overlay.php <image> <results> <coords> <output> [config-dir]
 */

require __DIR__ . '/../vendor/autoload.php';

use Tests\Helpers\OMRSimulator;
use App\Services\QuestionnaireLoader;
use Illuminate\Foundation\Application;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// Parse arguments
if ($argc < 5) {
    fwrite(STDERR, "Usage: php generate-overlay.php <image> <results.json> <coordinates.json> <output.png> [config-dir]\n");
    exit(1);
}

$imagePath = $argv[1];
$resultsPath = $argv[2];
$coordsPath = $argv[3];
$outputPath = $argv[4];
$configPath = $argv[5] ?? null; // Optional 5th argument for config directory

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

// Handle both 'results' and 'bubbles' formats (Laravel command uses 'bubbles')
if (!$results) {
    fwrite(STDERR, "Error: Invalid results JSON\n");
    exit(1);
}

if (!isset($results['results']) && !isset($results['bubbles'])) {
    fwrite(STDERR, "Results file missing 'results' array\n");
    exit(1);
}

// Normalize to 'results' key for consistent handling
if (isset($results['bubbles']) && !isset($results['results'])) {
    $results['results'] = $results['bubbles'];
}

if (!$coordinates) {
    fwrite(STDERR, "Error: Invalid coordinates JSON\n");
    exit(1);
}

// Load questionnaire data for candidate names (dual-source: file or database)
$questionnaireData = null;
try {
    $questionnaireLoader = app(QuestionnaireLoader::class);
    
    // Try loading from file first (if config path provided)
    if ($configPath) {
        $questionnaireData = $questionnaireLoader->load($configPath, null);
    }
    
    // Fall back to database if file loading failed or no config path provided
    if ($questionnaireData === null) {
        $questionnaireData = $questionnaireLoader->load(null, 'PH-2025-QUESTIONNAIRE-CURRIMAO-001');
    }
    
    if ($questionnaireData === null) {
        fwrite(STDERR, "Warning: Could not load questionnaire data from file or database\n");
    }
} catch (Exception $e) {
    // If loading fails, continue without candidate names
    fwrite(STDERR, "Warning: Error loading questionnaire: {$e->getMessage()}\n");
}

// Generate overlay
try {
    // Extract barcode info if available
    $barcodeInfo = null;
    if (isset($results['barcode'])) {
        $barcodeInfo = [
            'document_id' => $results['document_id'] ?? 'UNKNOWN',
            'decoded' => $results['barcode']['decoded'] ?? false,
            'decoder' => $results['barcode']['decoder'] ?? 'none',
            'confidence' => $results['barcode']['confidence'] ?? 0,
            'source' => $results['barcode']['source'] ?? 'none',
        ];
    }
    
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
            'barcode_info' => $barcodeInfo,
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
