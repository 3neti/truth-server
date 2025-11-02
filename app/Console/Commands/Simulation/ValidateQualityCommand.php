<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ValidateQualityCommand extends Command
{
    protected $signature = 'simulation:validate-quality
                            {results-file : Path to appreciation results JSON}
                            {--fail-on-amber : Fail on amber verdicts (not just red)}
                            {--output= : Output validation report path}';

    protected $description = 'Validate quality gates from appreciation results';

    public function handle(): int
    {
        $resultsFile = $this->argument('results-file');
        $failOnAmber = $this->option('fail-on-amber');
        $outputPath = $this->option('output');

        if (!File::exists($resultsFile)) {
            $this->error("Results file not found: {$resultsFile}");
            return 1;
        }

        $results = json_decode(File::get($resultsFile), true);
        if (!$results) {
            $this->error("Invalid JSON in results file");
            return 1;
        }

        // Check if quality metrics are present
        if (!isset($results['quality'])) {
            $this->error("No quality metrics found in results");
            $this->line("Quality metrics require fiducial alignment to be enabled");
            return 1;
        }

        $quality = $results['quality'];
        $metrics = $quality['metrics'] ?? [];
        $verdicts = $quality['verdicts'] ?? [];
        $overall = $quality['overall'] ?? 'unknown';

        // Display quality report
        $this->info("Quality Metrics Validation");
        $this->line(str_repeat('=', 60));
        
        $this->displayMetric('Rotation', $metrics['rotation_deg'] ?? 0, '°', $verdicts['theta'] ?? 'unknown');
        $this->displayMetric('Shear', $metrics['shear_deg'] ?? 0, '°', $verdicts['shear'] ?? 'unknown');
        $this->displayMetric('Aspect Ratio (TB)', $metrics['aspect_ratio_tb'] ?? 0, '', $verdicts['aspect_ratio'] ?? 'unknown');
        $this->displayMetric('Aspect Ratio (LR)', $metrics['aspect_ratio_lr'] ?? 0, '', $verdicts['aspect_ratio'] ?? 'unknown');
        $this->displayMetric('Reproj Error', $metrics['reprojection_error_px'] ?? 0, 'px', $verdicts['reproj_error'] ?? 'unknown');
        
        $this->line(str_repeat('=', 60));
        
        // Overall verdict
        $overallIcon = $this->getVerdictIcon($overall);
        $overallColor = $this->getVerdictColor($overall);
        $this->line("<{$overallColor}>{$overallIcon} Overall: " . strtoupper($overall) . "</{$overallColor}>");

        // Determine pass/fail
        $passed = false;
        if ($failOnAmber) {
            $passed = ($overall === 'green');
        } else {
            $passed = ($overall !== 'red');
        }

        if ($passed) {
            $this->info("\n✓ Quality gates: PASS");
        } else {
            $this->error("\n✗ Quality gates: FAIL");
        }

        // Save validation report if output specified
        if ($outputPath) {
            $report = [
                'timestamp' => now()->toIso8601String(),
                'results_file' => $resultsFile,
                'quality' => $quality,
                'validation' => [
                    'passed' => $passed,
                    'fail_on_amber' => $failOnAmber,
                    'overall_verdict' => $overall,
                ],
            ];

            File::put($outputPath, json_encode($report, JSON_PRETTY_PRINT));
            $this->line("Report saved to: {$outputPath}");
        }

        return $passed ? 0 : 1;
    }

    protected function displayMetric(string $label, float $value, string $unit, string $verdict): void
    {
        $icon = $this->getVerdictIcon($verdict);
        $color = $this->getVerdictColor($verdict);
        
        $formatted = is_int($value) ? $value : sprintf('%.2f', $value);
        $this->line(sprintf(
            "<%s>%s %-20s %8s%s (%s)</%s>",
            $color,
            $icon,
            $label . ':',
            $formatted,
            $unit,
            strtoupper($verdict),
            $color
        ));
    }

    protected function getVerdictIcon(string $verdict): string
    {
        return match($verdict) {
            'green' => '✅',
            'amber' => '⚠️',
            'red' => '❌',
            default => '❓',
        };
    }

    protected function getVerdictColor(string $verdict): string
    {
        return match($verdict) {
            'green' => 'info',
            'amber' => 'comment',
            'red' => 'error',
            default => 'fg=gray',
        };
    }
}
