<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Tests\Helpers\OMRSimulator;
use App\Services\QuestionnaireLoader;

class ThresholdTuningCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulation:tune-threshold
                            {--config-dir= : Config directory for ballot generation}
                            {--output-dir= : Output directory (default: storage/app/private/threshold-tuning)}
                            {--dpi=300 : DPI for rendering}
                            {--intensities=1.0,0.85,0.70,0.55,0.40,0.25 : Comma-separated fill intensities to test}
                            {--threshold= : Appreciation threshold to use (default: from config)}
                            {--bubbles= : Comma-separated bubble IDs to fill (default: use test profile)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate ballots with varying fill intensities to tune OMR thresholds';

    /**
     * Execute the console command.
     */
    public function handle(QuestionnaireLoader $questionnaireLoader): int
    {
        $configDir = $this->option('config-dir');
        $outputDir = $this->option('output-dir') ?: storage_path('app/private/threshold-tuning');
        $dpi = (int) $this->option('dpi');
        $threshold = $this->option('threshold')
            ? (float) $this->option('threshold')
            : (float) config('omr-thresholds.detection_threshold', 0.3);
        
        // Parse intensities
        $intensities = array_map('floatval', explode(',', $this->option('intensities')));
        
        // Create timestamped run directory
        $timestamp = now()->format('Y-m-d_His');
        $runDir = "{$outputDir}/runs/{$timestamp}";
        File::ensureDirectoryExists($runDir);
        
        $this->info("Starting threshold tuning test: {$timestamp}");
        $this->info("Output directory: {$runDir}");
        
        // Determine which profile to use based on config-dir
        $profile = $this->determineProfile($configDir);
        $this->info("Using profile: {$profile}");
        
        // Get test configuration
        $profileConfig = config("omr-testing.profiles.{$profile}");
        
        if (!$profileConfig) {
            $this->error("Profile '{$profile}' not found in omr-testing config");
            return 1;
        }
        
        $documentId = $profileConfig['ballot']['document_id'];
        
        // Determine bubbles to fill
        $bubbles = $this->option('bubbles')
            ? explode(',', $this->option('bubbles'))
            : $profileConfig['simulation']['default_bubbles'];
        
        $this->info("Testing with " . count($bubbles) . " filled bubbles");
        $this->info("Fill intensities: " . implode(', ', $intensities));
        
        // Step 1: Seed database from config (if config-dir provided)
        if ($configDir) {
            $this->info("\n[1/5] Seeding database from config...");
            $seedResult = $this->call('simulation:seed-from-config', [
                '--config-dir' => $configDir,
            ]);
            
            if ($seedResult !== 0) {
                $this->error("Failed to seed database");
                return 1;
            }
            
            // Override document ID with the one from seed (SIM-BALLOT-001)
            $documentId = 'SIM-BALLOT-001';
        }
        
        // Step 2: Generate blank ballot
        $this->info("\n[2/5] Generating blank ballot...");
        $ballotResult = $this->call('simulation:generate-ballot', [
            '--document-id' => $documentId,
            '--output-dir' => $runDir,
        ]);
        
        if ($ballotResult !== 0) {
            $this->error("Failed to generate ballot");
            return 1;
        }
        
        // Step 3: Convert to PNG
        $this->info("\n[3/5] Converting ballot to PNG...");
        $pdfPath = "{$runDir}/ballot.pdf";
        if (!File::exists($pdfPath)) {
            $this->error("PDF not found: {$pdfPath}");
            return 1;
        }
        $blankPng = OMRSimulator::pdfToPng($pdfPath, $dpi);
        $this->info("✓ Blank PNG: {$blankPng}");
        
        // Load coordinates
        $coordsPath = "{$runDir}/coordinates.json";
        if (!File::exists($coordsPath)) {
            $this->error("Coordinates not found: {$coordsPath}");
            return 1;
        }
        $coordinates = json_decode(File::get($coordsPath), true);
        
        // Step 4: Generate filled ballots at each intensity
        $this->info("\n[4/5] Generating filled ballots at different intensities...");
        $results = [];
        
        foreach ($intensities as $intensity) {
            $intensityLabel = sprintf('%.0f', $intensity * 100);
            $this->info("\n  Testing intensity: {$intensityLabel}%");
            
            $intensityDir = "{$runDir}/intensity-{$intensityLabel}";
            File::ensureDirectoryExists($intensityDir);
            
            // Fill bubbles at this intensity
            $filledPath = str_replace('.png', "_filled_{$intensityLabel}.png", $blankPng);
            $filledPath = OMRSimulator::fillBubbles($blankPng, $bubbles, $coordinates, $dpi, $intensity);
            
            // Rename to intensity directory
            $targetFilledPath = "{$intensityDir}/ballot_filled.png";
            File::move($filledPath, $targetFilledPath);
            
            // Run appreciation
            $appreciationResult = $this->runAppreciation($targetFilledPath, $coordsPath, $threshold, $intensityDir);
            
            if (!$appreciationResult) {
                $this->warn("    Appreciation failed for {$intensityLabel}%");
                continue;
            }
            
            // Analyze results
            $analysis = $this->analyzeResults($appreciationResult, $bubbles);
            
            $this->line("    Filled bubbles detected: {$analysis['detected']} / {$analysis['expected']}");
            $this->line("    Avg fill_ratio: " . sprintf('%.3f', $analysis['avg_fill_ratio']));
            $this->line("    Min fill_ratio: " . sprintf('%.3f', $analysis['min_fill_ratio']));
            $this->line("    Max fill_ratio: " . sprintf('%.3f', $analysis['max_fill_ratio']));
            
            // Generate overlay
            $this->generateOverlay($targetFilledPath, $appreciationResult, $coordsPath, $intensityDir, $documentId);
            
            // Store results
            $results[] = [
                'intensity' => $intensity,
                'intensity_label' => $intensityLabel,
                'analysis' => $analysis,
                'directory' => $intensityDir,
            ];
        }
        
        // Step 5: Generate summary report
        $this->info("\n[5/5] Generating summary report...");
        $this->generateReport($results, $runDir, $threshold, $bubbles);
        
        // Create symlink to latest
        $latestLink = "{$outputDir}/latest";
        if (File::exists($latestLink)) {
            File::delete($latestLink);
        }
        symlink($runDir, $latestLink);
        
        $this->info("\n✓ Threshold tuning complete!");
        $this->info("Results: {$runDir}");
        $this->info("Summary: {$runDir}/summary.json");
        
        return 0;
    }
    
    /**
     * Determine profile from config directory
     */
    protected function determineProfile(?string $configDir): string
    {
        if (!$configDir) {
            return config('omr-testing.active_profile', 'philippine');
        }
        
        // Check if it contains barangay-related config
        if (str_contains($configDir, 'barangay') || str_contains($configDir, 'bokiawan')) {
            return 'barangay';
        }
        
        return config('omr-testing.active_profile', 'philippine');
    }
    
    /**
     * Run appreciation on filled ballot
     */
    protected function runAppreciation(string $imagePath, string $templatePath, float $threshold, string $outputDir): ?array
    {
        $pythonScript = base_path('packages/omr-appreciation/omr-python/appreciate.py');
        $resultsPath = "{$outputDir}/appreciation_results.json";
        $errorLog = "{$outputDir}/appreciation_errors.log";
        
        $command = sprintf(
            'python3 %s %s %s --threshold %.2f > %s 2> %s',
            escapeshellarg($pythonScript),
            escapeshellarg($imagePath),
            escapeshellarg($templatePath),
            $threshold,
            escapeshellarg($resultsPath),
            escapeshellarg($errorLog)
        );
        
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0 || !File::exists($resultsPath)) {
            return null;
        }
        
        $results = json_decode(File::get($resultsPath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            // JSON parse failed - log error and return null
            File::append($errorLog, "\nJSON parse error: " . json_last_error_msg());
            return null;
        }
        
        return $results;
    }
    
    /**
     * Analyze appreciation results
     */
    protected function analyzeResults(array $results, array $expectedBubbles): array
    {
        $filledResults = [];
        
        // Extract results based on format (handle both 'results' and 'bubbles' keys)
        $bubbleResults = $results['results'] ?? $results['bubbles'] ?? [];
        
        foreach ($bubbleResults as $bubble) {
            $bubbleId = $bubble['id'] ?? $bubble['bubble_id'] ?? null;
            if (!$bubbleId) continue;
            
            if (in_array($bubbleId, $expectedBubbles)) {
                $filledResults[] = $bubble;
            }
        }
        
        $fillRatios = array_map(fn($b) => $b['fill_ratio'] ?? 0, $filledResults);
        $detected = count(array_filter($filledResults, fn($b) => $b['filled'] ?? false));
        
        return [
            'expected' => count($expectedBubbles),
            'detected' => $detected,
            'accuracy' => $detected / max(count($expectedBubbles), 1),
            'avg_fill_ratio' => !empty($fillRatios) ? array_sum($fillRatios) / count($fillRatios) : 0,
            'min_fill_ratio' => !empty($fillRatios) ? min($fillRatios) : 0,
            'max_fill_ratio' => !empty($fillRatios) ? max($fillRatios) : 0,
            'fill_ratios' => $fillRatios,
        ];
    }
    
    /**
     * Generate overlay visualization
     */
    protected function generateOverlay(string $ballotPath, array $results, string $coordsPath, string $outputDir, string $documentId): void
    {
        $resultsPath = "{$outputDir}/appreciation_results.json";
        $overlayPath = "{$outputDir}/overlay.png";
        
        $this->call('simulation:create-overlay', [
            'ballot-image' => $ballotPath,
            'results-file' => $resultsPath,
            'coordinates-file' => $coordsPath,
            'output' => $overlayPath,
            '--document-id' => str_replace('BALLOT', 'QUESTIONNAIRE', $documentId),
            '--show-legend' => true,
        ]);
    }
    
    /**
     * Generate summary report
     */
    protected function generateReport(array $results, string $runDir, float $threshold, array $bubbles): void
    {
        $summary = [
            'timestamp' => now()->toIso8601String(),
            'threshold' => $threshold,
            'bubbles_tested' => $bubbles,
            'intensities' => [],
        ];
        
        foreach ($results as $result) {
            $summary['intensities'][] = [
                'intensity' => $result['intensity'],
                'intensity_label' => $result['intensity_label'],
                'expected_bubbles' => $result['analysis']['expected'],
                'detected_bubbles' => $result['analysis']['detected'],
                'accuracy' => round($result['analysis']['accuracy'], 3),
                'avg_fill_ratio' => round($result['analysis']['avg_fill_ratio'], 3),
                'min_fill_ratio' => round($result['analysis']['min_fill_ratio'], 3),
                'max_fill_ratio' => round($result['analysis']['max_fill_ratio'], 3),
            ];
        }
        
        // Generate recommendations
        $summary['recommendations'] = $this->generateRecommendations($summary['intensities'], $threshold);
        
        File::put("{$runDir}/summary.json", json_encode($summary, JSON_PRETTY_PRINT));
        
        // Also create readable markdown report
        $this->generateMarkdownReport($summary, $runDir);
    }
    
    /**
     * Generate threshold recommendations
     */
    protected function generateRecommendations(array $intensities, float $currentThreshold): array
    {
        $recommendations = [];
        
        // Find lowest intensity with 100% accuracy
        $lowestPerfect = null;
        foreach ($intensities as $intensity) {
            if ($intensity['accuracy'] >= 1.0) {
                $lowestPerfect = $intensity;
                break;
            }
        }
        
        if ($lowestPerfect) {
            $recommendations[] = [
                'type' => 'safe_threshold',
                'message' => "All marks at {$lowestPerfect['intensity_label']}% intensity were detected correctly",
                'suggested_threshold' => round($lowestPerfect['min_fill_ratio'] * 0.9, 2),
                'rationale' => '90% of minimum fill_ratio provides safety margin',
            ];
        }
        
        // Check if current threshold is too high
        $failedIntensities = array_filter($intensities, fn($i) => $i['accuracy'] < 1.0);
        if (!empty($failedIntensities)) {
            $highestFailed = end($failedIntensities);
            $recommendations[] = [
                'type' => 'false_negatives',
                'message' => "Current threshold ({$currentThreshold}) is missing marks at {$highestFailed['intensity_label']}% intensity",
                'suggested_threshold' => round($highestFailed['max_fill_ratio'] * 0.95, 2),
                'rationale' => 'Lower threshold to 95% of max fill_ratio at failed intensity',
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Generate markdown report
     */
    protected function generateMarkdownReport(array $summary, string $runDir): void
    {
        $markdown = "# OMR Threshold Tuning Report\n\n";
        $markdown .= "**Generated:** {$summary['timestamp']}\n\n";
        $markdown .= "**Current Threshold:** {$summary['threshold']}\n\n";
        $markdown .= "**Bubbles Tested:** " . count($summary['bubbles_tested']) . "\n\n";
        
        $markdown .= "## Results by Fill Intensity\n\n";
        $markdown .= "| Intensity | Detected | Accuracy | Avg Fill Ratio | Min Fill Ratio | Max Fill Ratio |\n";
        $markdown .= "|-----------|----------|----------|----------------|----------------|----------------|\n";
        
        foreach ($summary['intensities'] as $intensity) {
            $accuracy = sprintf('%.1f%%', $intensity['accuracy'] * 100);
            $markdown .= sprintf(
                "| %s%% | %d/%d | %s | %.3f | %.3f | %.3f |\n",
                $intensity['intensity_label'],
                $intensity['detected_bubbles'],
                $intensity['expected_bubbles'],
                $accuracy,
                $intensity['avg_fill_ratio'],
                $intensity['min_fill_ratio'],
                $intensity['max_fill_ratio']
            );
        }
        
        if (!empty($summary['recommendations'])) {
            $markdown .= "\n## Recommendations\n\n";
            foreach ($summary['recommendations'] as $rec) {
                $markdown .= "### {$rec['type']}\n\n";
                $markdown .= "{$rec['message']}\n\n";
                $markdown .= "**Suggested Threshold:** `{$rec['suggested_threshold']}`\n\n";
                $markdown .= "*Rationale:* {$rec['rationale']}\n\n";
            }
        }
        
        $markdown .= "\n## Viewing Results\n\n";
        $markdown .= "Each intensity test includes:\n";
        $markdown .= "- `ballot_filled.png` - Filled ballot at this intensity\n";
        $markdown .= "- `appreciation_results.json` - Raw appreciation results\n";
        $markdown .= "- `overlay.png` - Visual overlay with detected marks\n";
        
        File::put("{$runDir}/REPORT.md", $markdown);
    }
}
