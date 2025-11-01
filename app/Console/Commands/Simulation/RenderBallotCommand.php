<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Tests\Helpers\OMRSimulator;
use Illuminate\Support\Facades\File;

class RenderBallotCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulation:render-ballot
                            {votes-file : JSON file with votes to fill}
                            {coordinates-file : Coordinates JSON file}
                            {--blank-ballot= : Path to blank ballot PNG}
                            {--output= : Output filled ballot path}
                            {--dpi=300 : DPI for rendering}
                            {--fill-intensity=1.0 : Fill intensity (0.0-1.0, where 1.0 is fully black)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Render ballot with simulated filled bubbles';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $votesFile = $this->argument('votes-file');
        $coordsFile = $this->argument('coordinates-file');
        $blankBallot = $this->option('blank-ballot');
        $output = $this->option('output');
        $dpi = (int) $this->option('dpi');
        $fillIntensity = (float) $this->option('fill-intensity');
        
        // Validate inputs
        if (!File::exists($votesFile)) {
            $this->error("Votes file not found: {$votesFile}");
            return 1;
        }
        
        if (!File::exists($coordsFile)) {
            $this->error("Coordinates file not found: {$coordsFile}");
            return 1;
        }
        
        if (!$blankBallot) {
            $this->error("--blank-ballot option is required");
            return 1;
        }
        
        if (!File::exists($blankBallot)) {
            $this->error("Blank ballot not found: {$blankBallot}");
            return 1;
        }
        
        // Validate fill intensity
        if ($fillIntensity < 0.0 || $fillIntensity > 1.0) {
            $this->error("Fill intensity must be between 0.0 and 1.0");
            return 1;
        }
        
        // Load data
        try {
            $votes = json_decode(File::get($votesFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid votes JSON: " . json_last_error_msg());
                return 1;
            }
            
            $coordinates = json_decode(File::get($coordsFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid coordinates JSON: " . json_last_error_msg());
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error loading files: {$e->getMessage()}");
            return 1;
        }
        
        // Validate votes structure
        if (!isset($votes['votes'])) {
            $this->error("Votes file missing 'votes' object");
            return 1;
        }
        
        // Extract bubble IDs to fill from votes
        $bubblesToFill = $this->extractBubbleIds($votes['votes']);
        
        if (empty($bubblesToFill)) {
            $this->warn("No bubbles to fill - rendering blank ballot");
        } else {
            $this->info("Filling " . count($bubblesToFill) . " bubbles");
        }
        
        // Use fill intensity from votes file if present, otherwise use command option
        $intensity = $votes['fill_intensity'] ?? $fillIntensity;
        
        // Fill bubbles
        try {
            $filledPath = OMRSimulator::fillBubbles(
                $blankBallot,
                $bubblesToFill,
                $coordinates,
                $dpi,
                $intensity
            );
            
            // Copy to output location if specified
            if ($output) {
                // Ensure output directory exists
                $outputDir = dirname($output);
                if (!File::isDirectory($outputDir)) {
                    File::makeDirectory($outputDir, 0755, true);
                }
                
                File::copy($filledPath, $output);
                $this->info("✓ Ballot rendered: {$output}");
            } else {
                $this->info("✓ Ballot rendered: {$filledPath}");
            }
            
            return 0;
        } catch (\Exception $e) {
            $this->error("Error rendering ballot: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
    
    /**
     * Extract bubble IDs from votes structure
     * 
     * Votes format: {"POSITION_CODE": "CANDIDATE_CODE"} or {"POSITION_CODE": ["CANDIDATE_CODE1", "CANDIDATE_CODE2"]}
     * Bubble ID format: "POSITION_CODE_CANDIDATE_CODE"
     */
    protected function extractBubbleIds(array $votes): array
    {
        $bubblesToFill = [];
        
        foreach ($votes as $positionCode => $candidateCodes) {
            // Handle both single string and array of codes
            if (!is_array($candidateCodes)) {
                $candidateCodes = [$candidateCodes];
            }
            
            foreach ($candidateCodes as $candidateCode) {
                // Construct bubble ID: POSITION_CODE_CANDIDATE_CODE
                $bubblesToFill[] = "{$positionCode}_{$candidateCode}";
            }
        }
        
        return $bubblesToFill;
    }
}
