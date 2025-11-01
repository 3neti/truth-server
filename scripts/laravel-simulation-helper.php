#!/usr/bin/env php
<?php
/**
 * Laravel Simulation Helper
 * Provides Laravel test infrastructure to simulation scripts
 * Usage: php scripts/laravel-simulation-helper.php <command> [args]
 */

require __DIR__ . '/../vendor/autoload.php';

use Tests\Helpers\OMRSimulator;
use TruthElection\Factories\TemplateData;
use App\Services\QuestionnaireLoader;

// Bootstrap Laravel
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$command = $argv[1] ?? 'help';

switch ($command) {
    case 'generate-template':
        // Args: config_dir, output_dir
        $configDir = $argv[2] ?? 'resources/docs/simulation/config';
        $outputDir = $argv[3] ?? 'storage/app/private/simulation/template';
        
        generateTemplate($configDir, $outputDir);
        break;
        
    case 'generate-overlay':
        // Args: ballot_image, results_json, coords_json, output_path, config_dir
        $ballotImage = $argv[2] ?? null;
        $resultsJson = $argv[3] ?? null;
        $coordsJson = $argv[4] ?? null;
        $outputPath = $argv[5] ?? null;
        $configDir = $argv[6] ?? null;
        
        if (!$ballotImage || !$resultsJson || !$coordsJson || !$outputPath) {
            fwrite(STDERR, "Usage: generate-overlay <ballot> <results> <coords> <output> [config]\n");
            exit(1);
        }
        
        generateOverlay($ballotImage, $resultsJson, $coordsJson, $outputPath, $configDir);
        break;
        
    case 'fill-bubbles':
        // Args: blank_image, bubbles_json, coords_json, output_path
        $blankImage = $argv[2] ?? null;
        $bubblesJson = $argv[3] ?? null;
        $coordsJson = $argv[4] ?? null;
        $outputPath = $argv[5] ?? null;
        
        if (!$blankImage || !$bubblesJson || !$coordsJson || !$outputPath) {
            fwrite(STDERR, "Usage: fill-bubbles <blank> <bubbles> <coords> <output>\n");
            exit(1);
        }
        
        fillBubbles($blankImage, $bubblesJson, $coordsJson, $outputPath);
        break;
        
    case 'help':
    default:
        showHelp();
        break;
}

function generateTemplate(string $configDir, string $outputDir): void
{
    try {
        // Load questionnaire from config
        $loader = app(QuestionnaireLoader::class);
        $questionnaire = $loader->load($configDir, null);
        
        if (!$questionnaire) {
            throw new Exception("Could not load questionnaire from: $configDir");
        }
        
        // Generate template
        $templateFactory = new TemplateData();
        $template = $templateFactory->fromQuestionnaire($questionnaire);
        
        // Ensure output directory exists
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0755, true);
        }
        
        // Generate ballot PDF
        $ballotPdfPath = "$outputDir/ballot.pdf";
        $template->generateBallotPdf($ballotPdfPath);
        echo "Generated: $ballotPdfPath\n";
        
        // Generate questionnaire PDF
        $questionnairePdfPath = "$outputDir/questionnaire.pdf";
        $template->generateQuestionnairePdf($questionnairePdfPath);
        echo "Generated: $questionnairePdfPath\n";
        
        // Generate coordinates JSON
        $coordinatesPath = "$outputDir/coordinates.json";
        $coordinates = $template->getCoordinates();
        file_put_contents($coordinatesPath, json_encode($coordinates, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "Generated: $coordinatesPath\n";
        
        // Convert ballot PDF to PNG for use in scenarios
        $ballotPngPath = "$outputDir/ballot.png";
        OMRSimulator::pdfToPng($ballotPdfPath, 300);
        if (file_exists($ballotPngPath)) {
            echo "Generated: $ballotPngPath\n";
        }
        
        echo "Template generation complete!\n";
        exit(0);
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        fwrite(STDERR, $e->getTraceAsString() . "\n");
        exit(1);
    }
}

function generateOverlay(string $ballotImage, string $resultsJson, string $coordsJson, string $outputPath, ?string $configDir): void
{
    try {
        if (!file_exists($ballotImage)) {
            throw new Exception("Ballot image not found: $ballotImage");
        }
        
        if (!file_exists($resultsJson)) {
            throw new Exception("Results JSON not found: $resultsJson");
        }
        
        if (!file_exists($coordsJson)) {
            throw new Exception("Coordinates JSON not found: $coordsJson");
        }
        
        // Load data
        $results = json_decode(file_get_contents($resultsJson), true);
        $coordinates = json_decode(file_get_contents($coordsJson), true);
        
        // Handle both 'results' and 'bubbles' formats
        if (isset($results['bubbles'])) {
            $detectedMarks = $results['bubbles'];
        } elseif (isset($results['results'])) {
            $detectedMarks = is_array($results['results']) ? $results['results'] : array_values($results['results']);
        } else {
            $detectedMarks = $results;
        }
        
        // Load questionnaire for candidate names if config provided
        $questionnaireData = null;
        if ($configDir) {
            $loader = app(QuestionnaireLoader::class);
            $questionnaireData = $loader->load($configDir, null);
        }
        
        // Generate overlay
        $overlayPath = OMRSimulator::createOverlay(
            $ballotImage,
            $detectedMarks,
            $coordinates,
            [
                'output_path' => $outputPath,
                'show_legend' => true,
                'questionnaire' => $questionnaireData,
            ]
        );
        
        echo "Overlay created: $overlayPath\n";
        exit(0);
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

function fillBubbles(string $blankImage, string $bubblesJson, string $coordsJson, string $outputPath): void
{
    try {
        if (!file_exists($blankImage)) {
            throw new Exception("Blank image not found: $blankImage");
        }
        
        $bubbles = json_decode(file_get_contents($bubblesJson), true);
        $coordinates = json_decode(file_get_contents($coordsJson), true);
        
        // Extract bubble IDs to fill
        $bubblesToFill = [];
        foreach ($bubbles as $bubbleId => $data) {
            if ($data['filled'] ?? false) {
                $bubblesToFill[] = $bubbleId;
            }
        }
        
        $fillIntensity = 0.7; // Match deprecated script's fill intensity
        
        $filledPath = OMRSimulator::fillBubbles(
            $blankImage,
            $bubblesToFill,
            $coordinates,
            300,
            $fillIntensity
        );
        
        // Rename to expected output path
        if ($filledPath !== $outputPath) {
            rename($filledPath, $outputPath);
        }
        
        echo "Filled ballot created: $outputPath\n";
        exit(0);
    } catch (Exception $e) {
        fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
        exit(1);
    }
}

function showHelp(): void
{
    echo <<<HELP
Laravel Simulation Helper

Usage: php scripts/laravel-simulation-helper.php <command> [args]

Commands:
    generate-template <config_dir> <output_dir>
        Generate ballot.pdf, questionnaire.pdf, and coordinates.json
        
    generate-overlay <ballot> <results> <coords> <output> [config]
        Generate overlay.png with detected marks
        
    fill-bubbles <blank> <bubbles> <coords> <output>
        Fill bubbles on blank ballot image
        
    help
        Show this help message

HELP;
    exit(0);
}
