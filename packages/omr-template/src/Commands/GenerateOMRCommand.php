<?php

namespace LBHurtado\OMRTemplate\Commands;

use Illuminate\Console\Command;
use LBHurtado\OMRTemplate\Data\TemplateData;
use LBHurtado\OMRTemplate\Data\ZoneMapData;
use LBHurtado\OMRTemplate\Services\DocumentIdGenerator;
use LBHurtado\OMRTemplate\Services\FiducialHelper;
use LBHurtado\OMRTemplate\Services\TemplateExporter;
use LBHurtado\OMRTemplate\Services\TemplateRenderer;

class GenerateOMRCommand extends Command
{
    protected $signature = 'omr:generate 
                            {template : The template ID to use (e.g., ballot-v1)}
                            {identifier : The document identifier (e.g., ABC-001)}
                            {--data= : JSON data file path for template variables}';

    protected $description = 'Generate an OMR document (PDF + JSON zone map)';

    public function handle(
        TemplateRenderer $renderer,
        TemplateExporter $exporter,
        FiducialHelper $fiducialHelper,
        DocumentIdGenerator $idGenerator
    ): int {
        $templateId = $this->argument('template');
        $identifier = $this->argument('identifier');
        $dataFile = $this->option('data');

        $this->info("Generating OMR document: {$identifier} using template: {$templateId}");

        // Load data from file or use empty array
        $data = [];
        if ($dataFile && file_exists($dataFile)) {
            $data = json_decode(file_get_contents($dataFile), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('Invalid JSON data file');

                return self::FAILURE;
            }
        }

        // Determine layout and DPI
        $layout = $data['layout'] ?? 'A4';
        $dpi = $data['dpi'] ?? 300;

        // Generate fiducials if not provided
        $fiducials = $data['fiducials'] ?? $fiducialHelper->generateFiducials($layout, $dpi);

        // Generate document ID if not provided
        $documentType = $data['document_type'] ?? 'document';
        $documentId = $data['document_id'] ?? $idGenerator->fromIdentifier(
            $identifier,
            strtoupper($documentType)
        );

        // Create template data
        $templateData = new TemplateData(
            template_id: $templateId,
            document_type: $documentType,
            contests_or_sections: $data['contests_or_sections'] ?? [],
            document_id: $documentId,
            layout: $layout,
            dpi: $dpi,
            qr: $data['qr'] ?? null,
            metadata: $data['metadata'] ?? null,
            fiducials: $fiducials,
        );

        // Render HTML
        try {
            $html = $renderer->render($templateData);
        } catch (\Exception $e) {
            $this->error("Failed to render template: {$e->getMessage()}");

            return self::FAILURE;
        }

        // Create zone map with fiducials and document ID
        $zoneMap = new ZoneMapData(
            template_id: $templateId,
            document_type: $templateData->document_type,
            zones: $data['zones'] ?? [],
            document_id: $documentId,
            fiducials: $fiducials,
            size: $layout,
            dpi: $dpi,
        );

        // Export to PDF and JSON
        $outputPath = config('omr-template.output_path', storage_path('omr-output'));
        $basePath = "{$outputPath}/{$identifier}";

        $metadata = [
            'template_id' => $templateId,
            'document_id' => $documentId,
            'identifier' => $identifier,
            'generated_at' => now()->toIso8601String(),
            'hash' => hash('sha256', $html),
        ];

        $output = $exporter->export($html, $zoneMap, $metadata);
        $output->saveAll($basePath);

        $this->info("✅ PDF saved to: {$basePath}.pdf");
        $this->info("✅ Zone map saved to: {$basePath}.json");
        $this->info("✅ Metadata saved to: {$basePath}.meta.json");

        return self::SUCCESS;
    }
}
