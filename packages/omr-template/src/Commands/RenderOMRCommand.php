<?php

namespace LBHurtado\OMRTemplate\Commands;

use Illuminate\Console\Command;
use LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer;

class RenderOMRCommand extends Command
{
    protected $signature = 'omr:render 
                            {spec : Path to the JSON specification file}
                            {--output= : Override output path for the generated PDF}';

    protected $description = 'Render an OMR document (ballot/survey) from a JSON specification';

    public function handle(): int
    {
        $specPath = $this->argument('spec');
        
        // Check if file exists
        if (!file_exists($specPath)) {
            $this->error("Specification file not found: {$specPath}");
            return self::FAILURE;
        }

        // Load and parse JSON
        $json = file_get_contents($specPath);
        $spec = json_decode($json, true);

        if ($spec === null) {
            $this->error("Invalid JSON in specification file");
            return self::FAILURE;
        }

        $this->info("Rendering OMR document...");
        $this->info("Document: " . ($spec['document']['title'] ?? 'Untitled'));
        $this->info("ID: " . ($spec['document']['unique_id'] ?? 'UNKNOWN'));

        try {
            // Render the document
            $renderer = new SmartLayoutRenderer();
            $result = $renderer->render($spec);

            $this->newLine();
            $this->info("✓ PDF generated: {$result['pdf']}");
            $this->info("✓ Coordinates exported: {$result['coords']}");
            $this->info("✓ Document ID: {$result['document_id']}");
            
            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to render document: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
