<?php

namespace LBHurtado\OMRAppreciation\Commands;

use Illuminate\Console\Command;
use LBHurtado\OMRAppreciation\Services\AppreciationService;

class AppreciateCommand extends Command
{
    protected $signature = 'omr:appreciate 
                            {image : Path to scanned image file}
                            {template : Path to template JSON file}
                            {--output= : Output file for appreciation results (default: stdout)}
                            {--threshold=0.3 : Fill threshold (0.0-1.0) for mark detection}';

    protected $description = 'Detect marks on a scanned OMR document';

    public function handle(AppreciationService $appreciationService): int
    {
        $imagePath = $this->argument('image');
        $templatePath = $this->argument('template');
        $outputPath = $this->option('output');
        $threshold = (float) $this->option('threshold');

        // Validate inputs
        if (!file_exists($imagePath)) {
            $this->error("Image file not found: {$imagePath}");
            return self::FAILURE;
        }

        if (!file_exists($templatePath)) {
            $this->error("Template file not found: {$templatePath}");
            return self::FAILURE;
        }

        // Load template
        $templateData = json_decode(file_get_contents($templatePath), true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Invalid template JSON: ' . json_last_error_msg());
            return self::FAILURE;
        }

        $this->info("Appreciating document: {$imagePath}");
        $this->info("Using template: {$templatePath}");
        $this->info("Fill threshold: {$threshold}");

        try {
            // Set threshold if provided
            $markDetector = app(\LBHurtado\OMRAppreciation\Services\MarkDetector::class);
            $markDetector->setFillThreshold($threshold);

            // Appreciate
            $result = $appreciationService->appreciate($imagePath, $templateData);

            // Output results
            $json = json_encode($result, JSON_PRETTY_PRINT);

            if ($outputPath) {
                file_put_contents($outputPath, $json);
                $this->info("✅ Results saved to: {$outputPath}");
            } else {
                $this->line($json);
            }

            // Show summary
            $summary = $result['summary'];
            $this->newLine();
            $this->info("Summary:");
            $this->line("  Total zones: {$summary['total_zones']}");
            $this->line("  Filled: {$summary['filled_count']}");
            $this->line("  Unfilled: {$summary['unfilled_count']}");
            $this->line("  Avg confidence: {$summary['average_confidence']}");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("Appreciation failed: {$e->getMessage()}");
            if ($this->getOutput()->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return self::FAILURE;
        }
    }
}
