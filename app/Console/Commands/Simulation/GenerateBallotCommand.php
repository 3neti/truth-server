<?php

namespace App\Console\Commands\Simulation;

use Illuminate\Console\Command;
use App\Models\Template;
use App\Models\TemplateData;
use App\Actions\TruthTemplates\Compilation\CompileHandlebarsTemplate;
use App\Actions\TruthTemplates\Rendering\RenderTemplateSpec;
use Illuminate\Support\Facades\File;

class GenerateBallotCommand extends Command
{
    protected $signature = 'simulation:generate-ballot
                            {--profile= : Test profile to use (default: from config)}
                            {--template-variant=answer-sheet-grid : Template layout variant}
                            {--document-id=SIM-BALLOT-001 : Document ID for template data}
                            {--output-dir= : Output directory for PDF and coordinates}
                            {--questionnaire-variant=questionnaire : Questionnaire template variant}
                            {--questionnaire-id=SIM-QUESTIONNAIRE-001 : Questionnaire document ID}';

    protected $description = 'Generate ballot PDF and coordinates using Laravel template system';

    public function handle(): int
    {
        $outputDir = $this->option('output-dir');
        
        if (!$outputDir) {
            $this->error('Missing required option: --output-dir');
            return 1;
        }
        
        // Load from profile or use explicit options
        $profile = $this->option('profile') ?: config('omr-testing.active_profile');
        $profileConfig = config("omr-testing.profiles.{$profile}");
        
        if (!$profileConfig && !$this->option('template-variant')) {
            $this->error("Profile '{$profile}' not found and no explicit options provided");
            return 1;
        }
        
        // Allow explicit options to override profile
        $templateVariant = $this->option('template-variant') ?: $profileConfig['ballot']['template_variant'];
        $documentId = $this->option('document-id') ?: $profileConfig['ballot']['document_id'];
        $questionnaireVariant = $this->option('questionnaire-variant') ?: ($profileConfig['questionnaire']['template_variant'] ?? null);
        $questionnaireId = $this->option('questionnaire-id') ?: ($profileConfig['questionnaire']['document_id'] ?? null);
        
        $this->info("Using profile: {$profile}");

        // Ensure output directory exists
        if (!File::isDirectory($outputDir)) {
            File::makeDirectory($outputDir, 0755, true);
        }

        // 1. Load ballot template and data from database
        $template = Template::where('layout_variant', $templateVariant)->first();
        $data = TemplateData::where('document_id', $documentId)->first();

        if (!$template) {
            $this->error("Template with layout_variant '{$templateVariant}' not found");
            return 1;
        }

        if (!$data) {
            $this->error("Template data with document_id '{$documentId}' not found");
            return 1;
        }

        $this->info("✓ Loaded template: {$templateVariant}");
        $this->info("✓ Loaded data: {$documentId}");

        // 2. Compile and render ballot
        try {
            $spec = CompileHandlebarsTemplate::run(
                $template->handlebars_template,
                $data->json_data
            );

            $result = RenderTemplateSpec::run($spec);

            // 3. Copy files to output directory
            $ballotPdf = "{$outputDir}/ballot.pdf";
            $coordinatesJson = "{$outputDir}/coordinates.json";

            File::copy($result['pdf'], $ballotPdf);
            
            // Only copy coordinates if it doesn't already exist (may be from mapping.yaml)
            if (!File::exists($coordinatesJson)) {
                File::copy($result['coords'], $coordinatesJson);
                $this->info("✓ Coordinates: {$coordinatesJson}");
            } else {
                $this->info("✓ Using existing coordinates (not overwriting)");
            }

            $this->info("✓ Ballot PDF: {$ballotPdf}");

            // 4. Generate questionnaire if requested
            if ($questionnaireVariant && $questionnaireId) {
                $questionnaireTemplate = Template::where('layout_variant', $questionnaireVariant)->first();
                $questionnaireData = TemplateData::where('document_id', $questionnaireId)->first();

                if ($questionnaireTemplate && $questionnaireData) {
                    $questionnaireSpec = CompileHandlebarsTemplate::run(
                        $questionnaireTemplate->handlebars_template,
                        $questionnaireData->json_data
                    );

                    $questionnaireResult = RenderTemplateSpec::run($questionnaireSpec);
                    $questionnairePdf = "{$outputDir}/questionnaire.pdf";

                    File::copy($questionnaireResult['pdf'], $questionnairePdf);
                    $this->info("✓ Questionnaire PDF: {$questionnairePdf}");
                }
            }

            // 5. Output JSON for shell script consumption
            $this->line(json_encode([
                'ballot_pdf' => $ballotPdf,
                'coordinates_json' => $coordinatesJson,
                'questionnaire_pdf' => isset($questionnairePdf) ? $questionnairePdf : null,
            ]));

            return 0;

        } catch (\Exception $e) {
            $this->error("Error generating ballot: {$e->getMessage()}");
            if ($this->output->isVerbose()) {
                $this->line($e->getTraceAsString());
            }
            return 1;
        }
    }
}
