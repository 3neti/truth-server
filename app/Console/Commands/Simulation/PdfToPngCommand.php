<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Tests\Helpers\OMRSimulator;
use Illuminate\Support\Facades\File;

class PdfToPngCommand extends Command
{
    protected $signature = 'simulation:pdf-to-png
                            {pdf-file : Path to PDF file}
                            {--output= : Output PNG path (optional)}';

    protected $description = 'Convert PDF to PNG using OMRSimulator';

    public function handle(): int
    {
        $pdfFile = $this->argument('pdf-file');
        $output = $this->option('output');

        if (!File::exists($pdfFile)) {
            $this->error("PDF file not found: {$pdfFile}");
            return 1;
        }

        try {
            // Use OMRSimulator to convert PDF to PNG
            $pngPath = OMRSimulator::pdfToPng($pdfFile);

            if (!File::exists($pngPath)) {
                $this->error("PNG conversion failed");
                return 1;
            }

            // Copy to output path if specified
            if ($output) {
                $outputDir = dirname($output);
                if (!File::isDirectory($outputDir)) {
                    File::makeDirectory($outputDir, 0755, true);
                }
                File::copy($pngPath, $output);
                $this->info("✓ PNG saved to: {$output}");
                $this->line($output);
            } else {
                $this->info("✓ PNG created: {$pngPath}");
                $this->line($pngPath);
            }

            return 0;

        } catch (\Exception $e) {
            $this->error("Error converting PDF to PNG: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
}
