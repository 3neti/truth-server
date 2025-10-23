<?php

namespace LBHurtado\OMRAppreciation\Commands;

use Illuminate\Console\Command;
use LBHurtado\OMRTemplate\Data\TemplateData;
use LBHurtado\OMRTemplate\Services\BarcodeGenerator;
use LBHurtado\OMRTemplate\Services\DocumentIdGenerator;
use LBHurtado\OMRTemplate\Services\FiducialHelper;
use LBHurtado\OMRTemplate\Services\TemplateExporter;
use LBHurtado\OMRTemplate\Services\TemplateRenderer;
use LBHurtado\OMRTemplate\Services\ZoneGenerator;

class GenerateCalibrationCommand extends Command
{
    protected $signature = 'omr:generate-calibration
                            {identifier? : The document identifier (default: CAL-YYYYMMDD-HHMMSS)}
                            {--layout=A4 : Page layout (A4, Letter)}
                            {--dpi=300 : Output DPI}
                            {--zones=20 : Number of calibration zones to generate}
                            {--pattern=grid : Zone pattern (grid, horizontal, vertical, corners)}';

    protected $description = 'Generate a calibration sheet for testing OMR alignment and detection';

    public function handle(
        TemplateRenderer $renderer,
        TemplateExporter $exporter,
        FiducialHelper $fiducialHelper,
        DocumentIdGenerator $idGenerator,
        BarcodeGenerator $barcodeGenerator,
        ZoneGenerator $zoneGenerator
    ): int {
        $identifier = $this->argument('identifier') ?? 'CAL-'.date('Ymd-His');
        $layout = $this->option('layout');
        $dpi = (int) $this->option('dpi');
        $zoneCount = (int) $this->option('zones');
        $pattern = $this->option('pattern');

        $this->info("🔧 Generating calibration sheet: {$identifier}");
        $this->info("   Layout: {$layout}, DPI: {$dpi}, Zones: {$zoneCount}, Pattern: {$pattern}");

        // Generate fiducials
        $fiducials = $fiducialHelper->generateFiducials($layout, $dpi);

        // Generate document ID
        $documentId = $idGenerator->fromIdentifier($identifier, 'CALIBRATION');

        // Generate barcode
        $barcodeBase64 = config('omr-template.barcode.enabled', true)
            ? $barcodeGenerator->generate($documentId)
            : null;

        // Generate calibration zones based on pattern
        $zones = $this->generateCalibrationZones($zoneCount, $pattern, $layout, $dpi);

        // Create contests/sections for rendering
        $contestsOrSections = $this->createCalibrationContests($zones);

        // Create template data
        $templateData = new TemplateData(
            template_id: 'ballot-v1',
            document_type: 'Calibration Sheet',
            contests_or_sections: $contestsOrSections,
            document_id: $documentId,
            layout: $layout,
            dpi: $dpi,
            qr: null,
            metadata: [
                'purpose' => 'OMR Calibration and Verification',
                'pattern' => $pattern,
                'zone_count' => $zoneCount,
                'instructions' => 'Fill in ALL circles completely with a dark pen or pencil',
            ],
            fiducials: $fiducials,
            barcode_base64: $barcodeBase64,
        );

        $templateData->zones = $zones;

        $this->info("   Rendering template...");
        
        // Render HTML
        try {
            $html = $renderer->render($templateData);
            $this->info("   ✓ Template rendered successfully");
        } catch (\Exception $e) {
            $this->error("Failed to render template: {$e->getMessage()}");
            $this->error("Stack trace:");
            $this->error($e->getTraceAsString());

            return self::FAILURE;
        }

        // Create zone map
        $zoneMap = new \LBHurtado\OMRTemplate\Data\ZoneMapData(
            template_id: 'calibration-v1',
            document_type: 'Calibration Sheet',
            zones: $zones,
            document_id: $documentId,
            fiducials: $fiducials,
            size: $layout,
            dpi: $dpi,
        );

        // Export to PDF and JSON
        $outputPath = config('omr-template.output_path', storage_path('omr-output'));
        $basePath = "{$outputPath}/{$identifier}";
        
        $this->info("   Output path: {$basePath}");

        $metadata = [
            'template_id' => 'calibration-v1',
            'document_id' => $documentId,
            'identifier' => $identifier,
            'pattern' => $pattern,
            'zone_count' => $zoneCount,
            'layout' => $layout,
            'dpi' => $dpi,
            'generated_at' => now()->toIso8601String(),
            'hash' => hash('sha256', $html),
        ];

        $this->info("   Exporting to PDF...");
        try {
            $output = $exporter->export($html, $zoneMap, $metadata);
            $this->info("   ✓ Export successful");
        } catch (\Throwable $e) {
            $this->error("Failed to export PDF: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return self::FAILURE;
        }
        
        $this->info("   Saving files...");
        try {
            // Ensure directory exists
            $outputDir = dirname($basePath);
            if (!is_dir($outputDir)) {
                $this->info("   Creating directory: {$outputDir}");
                mkdir($outputDir, 0755, true);
            }
            
            $output->saveAll($basePath);
            $this->info("   ✓ Files saved");
        } catch (\Throwable $e) {
            $this->error("Failed to save files: {$e->getMessage()}");
            $this->error("File: {$e->getFile()}:{$e->getLine()}");
            if ($this->output->isVerbose()) {
                $this->error($e->getTraceAsString());
            }
            return self::FAILURE;
        }

        $this->newLine();
        $this->info("✅ Calibration sheet generated!");
        $this->info("   PDF: {$basePath}.pdf");
        $this->info("   Zone map: {$basePath}.json");
        $this->info("   Metadata: {$basePath}.meta.json");
        $this->newLine();
        $this->info("📋 Next steps:");
        $this->info("   1. Print the PDF at 100% scale (no scaling!)");
        $this->info("   2. Fill in ALL circles completely with a dark pen");
        $this->info("   3. Scan or photograph at 300 DPI");
        $this->info("   4. Run: php artisan omr:verify-calibration <scan-file> {$basePath}.json");

        return self::SUCCESS;
    }

    private function generateCalibrationZones(int $count, string $pattern, string $layout, int $dpi): array
    {
        $zones = [];

        // Get page dimensions in pixels
        $dimensions = $this->getPageDimensions($layout, $dpi);
        $pageWidth = $dimensions['width'];
        $pageHeight = $dimensions['height'];

        // Define margins and usable area
        $margin = (int) ($dpi * 0.75); // 0.75 inch margin
        $usableWidth = $pageWidth - (2 * $margin);
        $usableHeight = $pageHeight - (2 * $margin) - (int) ($dpi * 1.5); // Extra space for header

        $boxSize = (int) ($dpi * 0.25); // 0.25 inch boxes

        switch ($pattern) {
            case 'grid':
                $zones = $this->generateGridPattern($count, $margin, $usableWidth, $usableHeight, $boxSize);
                break;
            case 'horizontal':
                $zones = $this->generateHorizontalPattern($count, $margin, $usableWidth, $usableHeight, $boxSize);
                break;
            case 'vertical':
                $zones = $this->generateVerticalPattern($count, $margin, $usableWidth, $usableHeight, $boxSize);
                break;
            case 'corners':
                $zones = $this->generateCornersPattern($count, $pageWidth, $pageHeight, $margin, $boxSize);
                break;
            default:
                $zones = $this->generateGridPattern($count, $margin, $usableWidth, $usableHeight, $boxSize);
        }

        return $zones;
    }

    private function generateGridPattern(int $count, int $marginX, int $usableWidth, int $usableHeight, int $boxSize): array
    {
        $zones = [];
        $cols = (int) ceil(sqrt($count));
        $rows = (int) ceil($count / $cols);

        $spacingX = $usableWidth / ($cols + 1);
        $spacingY = $usableHeight / ($rows + 1);

        $zoneId = 0;
        for ($row = 0; $row < $rows && $zoneId < $count; $row++) {
            for ($col = 0; $col < $cols && $zoneId < $count; $col++) {
                $x = (int) ($marginX + ($col + 1) * $spacingX);
                $y = (int) ($marginX + 150 + ($row + 1) * $spacingY); // 150px for header

                $zones[] = [
                    'id' => "zone_{$zoneId}",
                    'x' => $x,
                    'y' => $y,
                    'width' => $boxSize,
                    'height' => $boxSize,
                    'contest' => 'Calibration Grid',
                    'candidate' => "Zone {$zoneId}",
                    'render' => [
                        'show_box' => true,
                        'box_type' => 'circle',
                        'stroke_color' => '#000000',
                        'stroke_width' => 2,
                        'show_label' => true,
                        'label_text' => (string) $zoneId,
                        'label_position' => 'center',
                    ],
                ];
                $zoneId++;
            }
        }

        return $zones;
    }

    private function generateHorizontalPattern(int $count, int $marginX, int $usableWidth, int $usableHeight, int $boxSize): array
    {
        $zones = [];
        $spacing = $usableWidth / ($count + 1);
        $y = $marginX + 150 + ($usableHeight / 2);

        for ($i = 0; $i < $count; $i++) {
            $x = (int) ($marginX + ($i + 1) * $spacing);
            $zones[] = [
                'id' => "zone_{$i}",
                'x' => $x,
                'y' => (int) $y,
                'width' => $boxSize,
                'height' => $boxSize,
                'contest' => 'Calibration Horizontal',
                'candidate' => "Zone {$i}",
                'render' => [
                    'show_box' => true,
                    'box_type' => 'circle',
                    'stroke_color' => '#000000',
                    'stroke_width' => 2,
                    'show_label' => true,
                    'label_text' => (string) $i,
                    'label_position' => 'center',
                ],
            ];
        }

        return $zones;
    }

    private function generateVerticalPattern(int $count, int $marginX, int $usableWidth, int $usableHeight, int $boxSize): array
    {
        $zones = [];
        $spacing = $usableHeight / ($count + 1);
        $x = $marginX + ($usableWidth / 2);

        for ($i = 0; $i < $count; $i++) {
            $y = (int) ($marginX + 150 + ($i + 1) * $spacing);
            $zones[] = [
                'id' => "zone_{$i}",
                'x' => (int) $x,
                'y' => $y,
                'width' => $boxSize,
                'height' => $boxSize,
                'contest' => 'Calibration Vertical',
                'candidate' => "Zone {$i}",
                'render' => [
                    'show_box' => true,
                    'box_type' => 'circle',
                    'stroke_color' => '#000000',
                    'stroke_width' => 2,
                    'show_label' => true,
                    'label_text' => (string) $i,
                    'label_position' => 'center',
                ],
            ];
        }

        return $zones;
    }

    private function generateCornersPattern(int $count, int $pageWidth, int $pageHeight, int $margin, int $boxSize): array
    {
        $zones = [];

        // Define corner positions
        $corners = [
            ['x' => $margin + 100, 'y' => $margin + 200, 'name' => 'Top-Left'],
            ['x' => $pageWidth - $margin - $boxSize - 100, 'y' => $margin + 200, 'name' => 'Top-Right'],
            ['x' => $margin + 100, 'y' => $pageHeight - $margin - $boxSize - 100, 'name' => 'Bottom-Left'],
            ['x' => $pageWidth - $margin - $boxSize - 100, 'y' => $pageHeight - $margin - $boxSize - 100, 'name' => 'Bottom-Right'],
        ];

        // Add center
        $corners[] = [
            'x' => (int) ($pageWidth / 2),
            'y' => (int) ($pageHeight / 2),
            'name' => 'Center',
        ];

        $perCorner = (int) ceil($count / count($corners));

        $zoneId = 0;
        foreach ($corners as $corner) {
            for ($i = 0; $i < $perCorner && $zoneId < $count; $i++) {
                $zones[] = [
                    'id' => "zone_{$zoneId}",
                    'x' => $corner['x'] + ($i * 30),
                    'y' => $corner['y'] + ($i * 30),
                    'width' => $boxSize,
                    'height' => $boxSize,
                    'contest' => "Calibration {$corner['name']}",
                    'candidate' => "Zone {$zoneId}",
                    'render' => [
                        'show_box' => true,
                        'box_type' => 'circle',
                        'stroke_color' => '#000000',
                        'stroke_width' => 2,
                        'show_label' => true,
                        'label_text' => (string) $zoneId,
                        'label_position' => 'center',
                    ],
                ];
                $zoneId++;
            }
        }

        return $zones;
    }

    private function createCalibrationContests(array $zones): array
    {
        $contests = [];
        $contestMap = [];

        foreach ($zones as $zone) {
            $contestName = $zone['contest'];
            if (! isset($contestMap[$contestName])) {
                $contestMap[$contestName] = [
                    'title' => $contestName,
                    'instruction' => 'Fill ALL circles completely',
                    'candidates' => [],
                ];
            }
            $contestMap[$contestName]['candidates'][] = ['name' => $zone['candidate']];
        }

        return array_values($contestMap);
    }

    private function getPageDimensions(string $layout, int $dpi): array
    {
        return match ($layout) {
            'Letter' => ['width' => (int) (8.5 * $dpi), 'height' => (int) (11 * $dpi)],
            default => ['width' => (int) (8.27 * $dpi), 'height' => (int) (11.69 * $dpi)], // A4
        };
    }
}
