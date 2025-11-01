<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use Tests\Helpers\OMRSimulator;
use App\Services\QuestionnaireLoader;
use Illuminate\Support\Facades\File;

class CreateOverlayCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simulation:create-overlay
                            {ballot-image : Path to ballot image}
                            {results-file : Appreciation results JSON file}
                            {coordinates-file : Coordinates JSON file}
                            {output : Output overlay image path}
                            {--config-dir= : Config directory for questionnaire data}
                            {--document-id= : Database document ID (fallback)}
                            {--show-legend : Show legend in overlay}
                            {--show-unfilled : Show unfilled bubbles}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create visual overlay from ballot appreciation results';

    /**
     * Execute the console command.
     */
    public function handle(QuestionnaireLoader $questionnaireLoader): int
    {
        $ballotImage = $this->argument('ballot-image');
        $resultsFile = $this->argument('results-file');
        $coordsFile = $this->argument('coordinates-file');
        $output = $this->argument('output');
        
        // Validate input files
        if (!File::exists($ballotImage)) {
            $this->error("Ballot image not found: {$ballotImage}");
            return 1;
        }
        
        if (!File::exists($resultsFile)) {
            $this->error("Results file not found: {$resultsFile}");
            return 1;
        }
        
        if (!File::exists($coordsFile)) {
            $this->error("Coordinates file not found: {$coordsFile}");
            return 1;
        }
        
        // Load results and coordinates
        try {
            $results = json_decode(File::get($resultsFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error("Invalid results JSON: " . json_last_error_msg());
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
        
        // Validate results structure - handle both 'results' and 'bubbles' keys
        $bubbleResults = null;
        if (isset($results['results'])) {
            $bubbleResults = $results['results'];
        } elseif (isset($results['bubbles'])) {
            // Convert bubbles array to results dict format
            $bubbleResults = [];
            foreach ($results['bubbles'] as $bubble) {
                $bubbleResults[$bubble['bubble_id']] = $bubble;
            }
        } else {
            $this->error("Results file missing 'results' or 'bubbles' array");
            return 1;
        }
        
        // Load questionnaire data (dual-source: file or database)
        $questionnaireData = $questionnaireLoader->load(
            $this->option('config-dir'),
            $this->option('document-id')
        );
        
        if ($questionnaireData === null && ($this->option('config-dir') || $this->option('document-id'))) {
            $this->warn('Questionnaire data not found - overlay will not include candidate names');
        }
        
        // Extract barcode info if available
        $barcodeInfo = null;
        if (isset($results['barcode'])) {
            $barcodeInfo = [
                'document_id' => $results['document_id'] ?? 'UNKNOWN',
                'decoded' => $results['barcode']['decoded'] ?? false,
                'decoder' => $results['barcode']['decoder'] ?? 'none',
                'confidence' => $results['barcode']['confidence'] ?? 0,
                'source' => $results['barcode']['source'] ?? 'none',
            ];
        }
        
        // Generate overlay
        try {
            $overlayPath = OMRSimulator::createOverlay(
                $ballotImage,
                $bubbleResults,
                $coordinates,
                [
                    'scenario' => 'simulation',
                    'show_legend' => $this->option('show-legend'),
                    'show_unfilled' => $this->option('show-unfilled'),
                    'output_path' => $output,
                    'questionnaire' => $questionnaireData,
                    'barcode_info' => $barcodeInfo,
                ]
            );
            
            if ($overlayPath && File::exists($overlayPath)) {
                $this->info("✓ Overlay created: {$overlayPath}");
                return 0;
            } else {
                $this->error("Overlay generation failed");
                return 1;
            }
        } catch (\Exception $e) {
            $this->error("Error generating overlay: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
}
