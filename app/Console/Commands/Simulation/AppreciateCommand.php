<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AppreciateCommand extends Command
{
    protected $signature = 'simulation:appreciate
                            {ballot-image : Path to filled ballot PNG}
                            {coordinates-file : Path to coordinates JSON}
                            {--output= : Output JSON path (optional)}
                            {--threshold= : Fill threshold (default: from config)}
                            {--no-align : Skip fiducial alignment}';

    protected $description = 'Run OMR appreciation on ballot image using Python script';

    public function handle(): int
    {
        $ballotImage = $this->argument('ballot-image');
        $coordsFile = $this->argument('coordinates-file');
        $output = $this->option('output');
        $threshold = $this->option('threshold') 
            ?? config('omr-thresholds.detection_threshold', 0.3);
        $noAlign = $this->option('no-align');

        // Validate inputs
        if (!File::exists($ballotImage)) {
            $this->error("Ballot image not found: {$ballotImage}");
            return 1;
        }

        if (!File::exists($coordsFile)) {
            $this->error("Coordinates file not found: {$coordsFile}");
            return 1;
        }

        // Locate appreciate.py script
        $appreciateScript = base_path('packages/omr-appreciation/omr-python/appreciate.py');
        if (!File::exists($appreciateScript)) {
            $this->error("Appreciate script not found: {$appreciateScript}");
            return 1;
        }

        // Build command
        $command = sprintf(
            'python3 %s %s %s --threshold %s %s 2>&1',
            escapeshellarg($appreciateScript),
            escapeshellarg($ballotImage),
            escapeshellarg($coordsFile),
            escapeshellarg($threshold),
            $noAlign ? '--no-align' : ''
        );

        // Run appreciation
        $this->info("Running appreciation...");
        $outputText = shell_exec($command);

        // Extract JSON from output (Python script may output debug lines before JSON)
        $jsonStart = strpos($outputText, '{');
        if ($jsonStart === false) {
            $this->error("No JSON found in appreciation output");
            $this->line("Raw output:");
            $this->line($outputText);
            return 1;
        }
        
        $jsonText = substr($outputText, $jsonStart);
        $result = json_decode($jsonText, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("Appreciation script returned invalid JSON");
            $this->line("Raw output:");
            $this->line($outputText);
            return 1;
        }

        // Save to file if output specified
        if ($output) {
            $outputDir = dirname($output);
            if (!File::isDirectory($outputDir)) {
                File::makeDirectory($outputDir, 0755, true);
            }
            File::put($output, json_encode($result, JSON_PRETTY_PRINT));
            $this->info("✓ Results saved to: {$output}");
        }

        // Output JSON to stdout for shell script consumption
        $this->line(json_encode($result));

        // Show summary
        if (isset($result['results'])) {
            $filled = collect($result['results'])->filter(fn($r) => $r['filled'] === true)->count();
            $total = count($result['results']);
            $this->info("✓ Detected {$filled} filled bubbles out of {$total} total");
        }

        return 0;
    }
}
