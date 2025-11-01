<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Tests\Helpers\OMRSimulator;
use Illuminate\Support\Facades\File;

class FillBubblesCommand extends Command
{
    protected $signature = 'simulation:fill-bubbles
                            {blank-ballot : Path to blank ballot PNG}
                            {--bubbles= : Comma-separated bubble IDs to fill}
                            {--coordinates= : Path to coordinates JSON}
                            {--output= : Output filled ballot path}
                            {--intensity=1.0 : Fill intensity (0.0-1.0)}';

    protected $description = 'Fill bubbles on blank ballot using OMRSimulator';

    public function handle(): int
    {
        $blankBallot = $this->argument('blank-ballot');
        $bubblesStr = $this->option('bubbles');
        $coordsFile = $this->option('coordinates');
        $output = $this->option('output');
        $intensity = (float) $this->option('intensity');

        // Validate inputs
        if (!File::exists($blankBallot)) {
            $this->error("Blank ballot not found: {$blankBallot}");
            return 1;
        }

        if (!$coordsFile || !File::exists($coordsFile)) {
            $this->error("Coordinates file not found: {$coordsFile}");
            return 1;
        }

        if (!$bubblesStr) {
            $this->error("No bubbles specified. Use --bubbles=A1,B2,C3");
            return 1;
        }

        // Parse bubble IDs
        $bubbles = array_map('trim', explode(',', $bubblesStr));

        // Load coordinates
        $coordinates = json_decode(File::get($coordsFile), true);
        if (!$coordinates) {
            $this->error("Invalid coordinates JSON");
            return 1;
        }

        try {
            // Use OMRSimulator to fill bubbles
            $filledPath = OMRSimulator::fillBubbles(
                $blankBallot,
                $bubbles,
                $coordinates,
                300, // DPI
                $intensity
            );

            if (!File::exists($filledPath)) {
                $this->error("Bubble filling failed");
                return 1;
            }

            // Copy to output path if specified
            if ($output) {
                $outputDir = dirname($output);
                if (!File::isDirectory($outputDir)) {
                    File::makeDirectory($outputDir, 0755, true);
                }
                File::copy($filledPath, $output);
                $this->info("✓ Filled ballot saved to: {$output}");
                $this->line($output);
            } else {
                $this->info("✓ Filled ballot created: {$filledPath}");
                $this->line($filledPath);
            }

            $this->info("✓ Filled " . count($bubbles) . " bubbles");

            return 0;

        } catch (\Exception $e) {
            $this->error("Error filling bubbles: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
}
