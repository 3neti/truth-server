<?php

namespace LBHurtado\OMRAppreciation\Commands;

use Illuminate\Console\Command;
use LBHurtado\OMRAppreciation\Services\OMRAppreciator;

class AppreciatePythonCommand extends Command
{
    protected $signature = 'omr:appreciate-python 
                            {image : Path to scanned image file}
                            {template : Path to template JSON file}
                            {--output= : Output file for appreciation results (default: stdout)}
                            {--threshold=0.3 : Fill threshold (0.0-1.0) for mark detection}
                            {--debug : Save debug visualization images}';

    protected $description = 'Detect marks on a scanned OMR document using Python OpenCV';

    public function handle(OMRAppreciator $appreciator): int
    {
        $imagePath = $this->argument('image');
        $templatePath = $this->argument('template');
        $outputPath = $this->option('output');
        $threshold = (float) $this->option('threshold');
        $debug = $this->option('debug');

        // Validate inputs
        if (! file_exists($imagePath)) {
            $this->error("Image file not found: {$imagePath}");

            return self::FAILURE;
        }

        if (! file_exists($templatePath)) {
            $this->error("Template file not found: {$templatePath}");

            return self::FAILURE;
        }

        $this->info("Appreciating document: {$imagePath}");
        $this->info("Using template: {$templatePath}");
        $this->info("Fill threshold: {$threshold}");
        if ($debug) {
            $this->info('Debug mode: ON (will save visualization images)');
        }
        $this->info('Processing with Python OpenCV...');

        try {
            // Run Python appreciation
            if ($debug) {
                $result = $appreciator->runDebug($imagePath, $templatePath, $threshold, $outputPath);
            } else {
                $result = $appreciator->run($imagePath, $templatePath, $threshold);
            }

            // Output results
            $json = json_encode($result, JSON_PRETTY_PRINT);

            if ($outputPath) {
                file_put_contents($outputPath, $json);
                $this->info("✅ Results saved to: {$outputPath}");
            } else {
                $this->line($json);
            }

            // Show summary
            $this->newLine();
            $this->info('Summary:');
            $this->line("  Document ID: {$result['document_id']}");
            $this->line("  Template ID: {$result['template_id']}");
            $this->line('  Total zones: '.count($result['results']));

            $filled = collect($result['results'])->filter(fn ($r) => $r['filled'])->count();
            $this->line("  Filled: {$filled}");
            $this->line('  Unfilled: '.(count($result['results']) - $filled));

            // Show debug image paths if in debug mode
            if ($debug && isset($result['debug'])) {
                $this->newLine();
                $this->info('Debug Visualizations:');
                if (file_exists($result['debug']['original_image'])) {
                    $this->line('  Original: '.$result['debug']['original_image']);
                }
                if (file_exists($result['debug']['aligned_image'])) {
                    $this->line('  Aligned: '.$result['debug']['aligned_image']);
                }
            }

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
