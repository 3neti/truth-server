<?php

namespace App\Actions\TruthTemplates\Rendering;

use App\Models\RenderingJob;
use LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class RenderTemplateSpec
{
    use AsAction;

    public string $jobQueue = 'rendering';

    public function __construct(
        protected SmartLayoutRenderer $renderer
    ) {}

    public function handle(array $spec): array
    {
        return $this->renderer->render($spec);
    }

    public function rules(): array
    {
        return [
            'spec' => ['required', 'array'],
            'spec.document' => ['required', 'array'],
            'spec.document.title' => ['required', 'string'],
            'spec.document.unique_id' => ['required', 'string'],
            'spec.sections' => ['required', 'array', 'min:1'],
        ];
    }

    /**
     * HTTP Controller invocation.
     * Returns JsonResponse for direct route binding.
     * Updated: 2025-01-27 - Changed to return JsonResponse directly with error handling
     */
    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        try {
            $validated = $request->validated();

            $result = $this->handle($validated['spec']);

            // Generate public URLs for the PDF and coordinates
            $documentId = $result['document_id'];

            return response()->json([
                'success' => true,
                'document_id' => $documentId,
                'pdf_url' => url("/api/truth-templates/download/{$documentId}"),
                'coords_url' => url("/api/truth-templates/coords/{$documentId}"),
                'pdf_path' => $result['pdf'],
                'coords_path' => $result['coords'],
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Execute as queued job with RenderingJob tracking.
     * 
     * @param array $spec The template spec to render
     * @param int|null $jobId The RenderingJob ID to track
     */
    public function asJob(array $spec, ?int $jobId = null): void
    {
        $job = null;
        
        // Load the job if ID provided
        if ($jobId) {
            $job = RenderingJob::find($jobId);
            if ($job) {
                $job->markAsProcessing();
            }
        }
        
        try {
            // Update progress at start
            if ($job) {
                $job->updateProgress(10);
            }
            
            // Render the template
            $result = $this->handle($spec);
            
            // Update progress
            if ($job) {
                $job->updateProgress(80);
            }
            
            // Generate public URL for the PDF
            $documentId = $result['document_id'];
            $pdfUrl = url("/api/truth-templates/download/{$documentId}");
            
            // Mark as completed
            if ($job) {
                $job->markAsCompleted($pdfUrl);
                // Store additional metadata
                $job->update([
                    'metadata' => array_merge($job->metadata ?? [], [
                        'document_id' => $documentId,
                        'pdf_path' => $result['pdf'],
                        'coords_path' => $result['coords'],
                        'coords_url' => url("/api/truth-templates/coords/{$documentId}"),
                    ]),
                ]);
            }
        } catch (\Exception $e) {
            // Mark as failed
            if ($job) {
                $job->markAsFailed($e->getMessage());
            }
            throw $e;
        }
    }

    public function asCommand(string $specPath, ?string $outputPath = null): int
    {
        $spec = json_decode(file_get_contents($specPath), true);
        $result = $this->handle($spec);

        $this->info('✓ Template rendered successfully');
        $this->line("  Document ID: {$result['document_id']}");
        $this->line("  PDF: {$result['pdf']}");
        $this->line("  Coordinates: {$result['coords']}");

        if ($outputPath) {
            copy($result['pdf'], $outputPath);
            $this->info("  Copied to: {$outputPath}");
        }

        return self::SUCCESS;
    }
}
