<?php

namespace App\Console\Commands;

use App\Services\BubbleIdGenerator;
use App\Services\ElectionConfigLoader;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateSimulationTemplateCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'election:generate-template
        {--config-path= : Path to config directory containing election.json and mapping.yaml}
        {--output= : Output path for coordinates.json (default: resources/docs/simulation/coordinates.json)}';

    /**
     * The console command description.
     */
    protected $description = 'Generate coordinates.json template with simple bubble IDs from election configs';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Determine config path
        $configPath = $this->option('config-path');
        
        if ($configPath) {
            // Temporarily set environment variable for ElectionConfigLoader
            putenv('ELECTION_CONFIG_PATH=' . $configPath);
        }
        
        $this->info('🔧 Loading election configuration...');
        
        try {
            $loader = new ElectionConfigLoader;
            $generator = new BubbleIdGenerator($loader);
            
            $election = $loader->loadElection();
            $mapping = $loader->loadMapping();
            
            $this->info("✓ Config loaded from: {$loader->getConfigPath()}");
            
        } catch (\Exception $e) {
            $this->error("✗ Failed to load config: {$e->getMessage()}");
            return self::FAILURE;
        }
        
        // Generate bubble coordinates
        $this->info('📐 Generating bubble coordinates...');
        
        $bubbles = $this->generateBubbleCoordinates($generator);
        
        $count = count($bubbles);
        $this->info("✓ Generated {$count} bubble coordinates");
        
        // Generate fiducial markers
        $fiducials = $this->generateFiducialMarkers();
        
        // Generate barcode area
        $barcode = $this->generateBarcodeArea();
        
        // Build template structure
        $template = [
            'document_id' => $mapping['code'] ?? 'SIMULATION-BALLOT',
            'template_id' => 'simulation-barangay-v1',
            'version' => '1.0.0',
            'description' => 'Simulation template with simple bubble IDs (A1-A6, B1-B50)',
            'ballot_size' => [
                'width_mm' => 210,  // A4 width
                'height_mm' => 297, // A4 height
            ],
            'bubble' => $bubbles,
            'fiducial' => $fiducials,
            'barcode' => $barcode,
        ];
        
        // Determine output path
        $outputPath = $this->option('output') 
            ?? base_path('resources/docs/simulation/coordinates.json');
        
        // Ensure directory exists
        $outputDir = dirname($outputPath);
        if (!File::exists($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }
        
        // Write JSON file
        $json = json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        File::put($outputPath, $json);
        
        $this->info("✅ Template generated: {$outputPath}");
        
        // Show summary
        $this->newLine();
        $this->table(
            ['Property', 'Value'],
            [
                ['Document ID', $template['document_id']],
                ['Template ID', $template['template_id']],
                ['Bubbles', count($bubbles)],
                ['Fiducials', count($fiducials)],
                ['Output Path', $outputPath],
            ]
        );
        
        return self::SUCCESS;
    }
    
    /**
     * Generate bubble coordinates with simple IDs using row-based layout
     */
    protected function generateBubbleCoordinates(BubbleIdGenerator $generator): array
    {
        $bubbles = [];
        $metadata = $generator->generateBubbleMetadata();
        
        // Layout parameters (in mm)
        $startX = 30.0;        // Left margin
        $startY = 80.0;        // Top margin for Row A
        $spacingX = 25.0;      // Horizontal spacing between bubbles
        $spacingY = 15.0;      // Vertical spacing between rows
        $diameter = 5.0;       // Bubble diameter
        $bubblesPerRow = 6;    // Standard bubbles per row
        
        foreach ($metadata as $bubbleId => $meta) {
            // Parse bubble ID (e.g., A1, B2, J1)
            preg_match('/^([A-Z])(\d+)$/', $bubbleId, $matches);
            if (count($matches) !== 3) {
                continue; // Skip invalid IDs
            }
            
            $rowLetter = $matches[1];
            $colNumber = (int) $matches[2];
            
            // Calculate row index (A=0, B=1, C=2, etc.)
            $rowIndex = ord($rowLetter) - ord('A');
            
            // Calculate column index (1-based to 0-based)
            $colIndex = $colNumber - 1;
            
            // Calculate position
            $x = $startX + ($colIndex * $spacingX);
            $y = $startY + ($rowIndex * $spacingY);
            
            $bubbles[$bubbleId] = [
                'center_x' => round($x, 2),
                'center_y' => round($y, 2),
                'diameter' => $diameter,
            ];
        }
        
        return $bubbles;
    }
    
    /**
     * Generate fiducial marker coordinates
     */
    protected function generateFiducialMarkers(): array
    {
        return [
            'tl' => [
                'x' => 8.5,
                'y' => 8.5,
                'marker_id' => 101,
                'type' => 'aruco',
                'dict' => 'DICT_4X4_100',
            ],
            'tr' => [
                'x' => 201.5,
                'y' => 8.5,
                'marker_id' => 102,
                'type' => 'aruco',
                'dict' => 'DICT_4X4_100',
            ],
            'br' => [
                'x' => 201.5,
                'y' => 288.5,
                'marker_id' => 103,
                'type' => 'aruco',
                'dict' => 'DICT_4X4_100',
            ],
            'bl' => [
                'x' => 8.5,
                'y' => 288.5,
                'marker_id' => 104,
                'type' => 'aruco',
                'dict' => 'DICT_4X4_100',
            ],
        ];
    }
    
    /**
     * Generate barcode area coordinates
     */
    protected function generateBarcodeArea(): array
    {
        return [
            'document_barcode' => [
                'x' => 70.0,
                'y' => 270.0,
                'width' => 70.0,
                'height' => 15.0,
                'type' => 'qr',
                'data' => 'SIMULATION-001',
            ],
        ];
    }
}
