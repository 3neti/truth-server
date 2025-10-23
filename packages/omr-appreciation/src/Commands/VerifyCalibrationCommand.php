<?php

namespace LBHurtado\OMRAppreciation\Commands;

use Illuminate\Console\Command;
use LBHurtado\OMRAppreciation\Services\OMRAppreciator;

class VerifyCalibrationCommand extends Command
{
    protected $signature = 'omr:verify-calibration
                            {scan : Path to the scanned calibration sheet image}
                            {template : Path to the calibration template JSON file}
                            {--threshold=0.25 : Fill threshold for mark detection (0.0-1.0)}
                            {--tolerance=20 : Position tolerance in pixels}
                            {--output= : Optional output file for detailed results JSON}
                            {--debug : Save debug visualization images}';

    protected $description = 'Verify a scanned calibration sheet and report alignment accuracy';

    public function handle(OMRAppreciator $appreciator): int
    {
        $scanPath = $this->argument('scan');
        $templatePath = $this->argument('template');
        $threshold = (float) $this->option('threshold');
        $tolerance = (int) $this->option('tolerance');
        $outputPath = $this->option('output');
        $debug = $this->option('debug');

        // Validate inputs
        if (! file_exists($scanPath)) {
            $this->error("Scan file not found: {$scanPath}");

            return self::FAILURE;
        }

        if (! file_exists($templatePath)) {
            $this->error("Template file not found: {$templatePath}");

            return self::FAILURE;
        }

        $this->info('🔍 Verifying Calibration Sheet');
        $this->info("   Scan: {$scanPath}");
        $this->info("   Template: {$templatePath}");
        $this->info("   Threshold: {$threshold}, Tolerance: {$tolerance}px");
        $this->newLine();

        // Run appreciation
        $this->info('📊 Running OMR appreciation...');

        $result = $appreciator->appreciate(
            $scanPath,
            $templatePath,
            $threshold,
            $debug
        );

        if (! $result['success']) {
            $this->error('❌ Failed to process calibration sheet');
            $this->error($result['error'] ?? 'Unknown error');

            return self::FAILURE;
        }

        // Analyze results
        $analysis = $this->analyzeCalibration($result['results'], $tolerance);

        // Display summary
        $this->displaySummary($analysis);

        // Display detailed results
        if ($this->output->isVerbose()) {
            $this->displayDetailedResults($analysis);
        }

        // Save output if requested
        if ($outputPath) {
            $outputData = [
                'scan' => $scanPath,
                'template' => $templatePath,
                'timestamp' => now()->toIso8601String(),
                'analysis' => $analysis,
                'raw_results' => $result['results'],
            ];

            file_put_contents($outputPath, json_encode($outputData, JSON_PRETTY_PRINT));
            $this->info("📄 Detailed results saved to: {$outputPath}");
        }

        // Show debug info if enabled
        if ($debug && isset($result['debug_image'])) {
            $this->newLine();
            $this->info("🐛 Debug visualization: {$result['debug_image']}");
        }

        // Return success/failure based on detection rate
        $successRate = $analysis['detection_rate'];
        if ($successRate >= 0.95) {
            $this->newLine();
            $this->info('✅ EXCELLENT: Calibration verification passed!');

            return self::SUCCESS;
        } elseif ($successRate >= 0.80) {
            $this->newLine();
            $this->warn('⚠️  GOOD: Most zones detected, but some issues found.');

            return self::SUCCESS;
        } else {
            $this->newLine();
            $this->error('❌ POOR: Significant alignment or detection issues detected.');

            return self::FAILURE;
        }
    }

    private function analyzeCalibration(array $results, int $tolerance): array
    {
        $totalZones = count($results);
        $detectedZones = 0;
        $undetectedZones = [];
        $lowConfidenceZones = [];
        $warnings = [];

        $fillRatios = [];
        $confidences = [];

        foreach ($results as $zone) {
            if ($zone['filled']) {
                $detectedZones++;
                $fillRatios[] = $zone['fill_ratio'];
                $confidences[] = $zone['confidence'];

                // Check for low confidence
                if ($zone['confidence'] < 0.7) {
                    $lowConfidenceZones[] = [
                        'zone' => $zone['id'],
                        'confidence' => $zone['confidence'],
                        'fill_ratio' => $zone['fill_ratio'],
                    ];
                }

                // Collect warnings
                if (! empty($zone['warnings'])) {
                    foreach ($zone['warnings'] as $warning) {
                        $warnings[] = [
                            'zone' => $zone['id'],
                            'warning' => $warning,
                        ];
                    }
                }
            } else {
                $undetectedZones[] = [
                    'zone' => $zone['id'],
                    'fill_ratio' => $zone['fill_ratio'],
                    'confidence' => $zone['confidence'],
                    'candidate' => $zone['candidate'] ?? 'Unknown',
                ];
            }
        }

        $detectionRate = $totalZones > 0 ? $detectedZones / $totalZones : 0;

        return [
            'total_zones' => $totalZones,
            'detected_zones' => $detectedZones,
            'undetected_zones_count' => count($undetectedZones),
            'detection_rate' => $detectionRate,
            'detection_percentage' => round($detectionRate * 100, 1),
            'undetected_zones' => $undetectedZones,
            'low_confidence_zones' => $lowConfidenceZones,
            'warnings_count' => count($warnings),
            'warnings' => $warnings,
            'stats' => [
                'avg_fill_ratio' => ! empty($fillRatios) ? round(array_sum($fillRatios) / count($fillRatios), 3) : 0,
                'min_fill_ratio' => ! empty($fillRatios) ? round(min($fillRatios), 3) : 0,
                'max_fill_ratio' => ! empty($fillRatios) ? round(max($fillRatios), 3) : 0,
                'avg_confidence' => ! empty($confidences) ? round(array_sum($confidences) / count($confidences), 3) : 0,
                'min_confidence' => ! empty($confidences) ? round(min($confidences), 3) : 0,
                'max_confidence' => ! empty($confidences) ? round(max($confidences), 3) : 0,
            ],
        ];
    }

    private function displaySummary(array $analysis): void
    {
        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('📊 CALIBRATION VERIFICATION SUMMARY');
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();

        // Detection stats
        $detected = $analysis['detected_zones'];
        $total = $analysis['total_zones'];
        $percentage = $analysis['detection_percentage'];

        $statusIcon = match (true) {
            $percentage >= 95 => '✅',
            $percentage >= 80 => '⚠️ ',
            default => '❌',
        };

        $this->info("Total Zones: {$total}");
        $this->info("Detected: {$detected} / {$total} ({$percentage}%) {$statusIcon}");
        $this->info("Undetected: {$analysis['undetected_zones_count']}");

        // Quality stats
        $this->newLine();
        $this->info('Quality Metrics:');
        $stats = $analysis['stats'];
        $this->info("  Fill Ratio:  avg={$stats['avg_fill_ratio']}, min={$stats['min_fill_ratio']}, max={$stats['max_fill_ratio']}");
        $this->info("  Confidence:  avg={$stats['avg_confidence']}, min={$stats['min_confidence']}, max={$stats['max_confidence']}");

        // Issues
        if (count($analysis['low_confidence_zones']) > 0) {
            $this->newLine();
            $this->warn("⚠️  {$analysis['low_confidence_zones']} zones with low confidence (<0.7)");
        }

        if ($analysis['warnings_count'] > 0) {
            $this->warn("⚠️  {$analysis['warnings_count']} warnings detected");
        }

        $this->newLine();
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
    }

    private function displayDetailedResults(array $analysis): void
    {
        $this->newLine();
        $this->info('📋 DETAILED RESULTS');
        $this->newLine();

        // Undetected zones
        if (! empty($analysis['undetected_zones'])) {
            $this->error('❌ Undetected Zones:');
            foreach ($analysis['undetected_zones'] as $zone) {
                $this->line("   {$zone['zone']} ({$zone['candidate']}): fill={$zone['fill_ratio']}, conf={$zone['confidence']}");
            }
            $this->newLine();
        }

        // Low confidence zones
        if (! empty($analysis['low_confidence_zones'])) {
            $this->warn('⚠️  Low Confidence Zones:');
            foreach ($analysis['low_confidence_zones'] as $zone) {
                $this->line("   {$zone['zone']}: conf={$zone['confidence']}, fill={$zone['fill_ratio']}");
            }
            $this->newLine();
        }

        // Warnings
        if (! empty($analysis['warnings'])) {
            $this->warn('⚠️  Warnings:');
            foreach ($analysis['warnings'] as $warning) {
                $this->line("   {$warning['zone']}: {$warning['warning']}");
            }
            $this->newLine();
        }

        // Recommendations
        $this->info('💡 Recommendations:');
        if ($analysis['detection_rate'] < 0.95) {
            if ($analysis['stats']['avg_fill_ratio'] < 0.30) {
                $this->line('   • Use a darker pen or pencil');
                $this->line('   • Fill circles more completely');
            }
            if ($analysis['stats']['avg_confidence'] < 0.70) {
                $this->line('   • Ensure scan quality is at least 200 DPI');
                $this->line('   • Avoid shadows and glare when scanning');
                $this->line('   • Ensure page is flat during scanning');
            }
            if ($analysis['undetected_zones_count'] > 2) {
                $this->line('   • Verify print scale was 100% (not scaled to fit)');
                $this->line('   • Check fiducial markers are visible and clear');
                $this->line('   • Try adjusting --threshold parameter');
            }
        } else {
            $this->line('   ✓ System is well calibrated!');
            $this->line('   ✓ Print and scan settings are optimal');
        }
    }
}
