<?php

namespace App\Actions\TruthTemplates\Rendering;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DownloadRenderedPdf
{
    use AsAction;

    public function handle(string $documentId): string
    {
        $outputPath = config('omr-template.output_path', storage_path('omr-output'));
        $pdfPath = $outputPath . '/' . $documentId . '.pdf';

        if (!file_exists($pdfPath)) {
            throw new \RuntimeException('PDF not found');
        }

        return $pdfPath;
    }

    /**
     * HTTP Controller invocation.
     * Returns BinaryFileResponse for direct route binding.
     * Updated: 2025-01-27 - Added error handling for missing PDFs
     */
    public function asController(ActionRequest $request, string $documentId): BinaryFileResponse|\Illuminate\Http\JsonResponse
    {
        try {
            $pdfPath = $this->handle($documentId);

            return response()->file($pdfPath, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $documentId . '.pdf"',
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    // Ensure Laravel Actions doesn't wrap the response
    public function htmlResponse($response): BinaryFileResponse
    {
        return $response;
    }

    public function asCommand(string $documentId, ?string $destination = null): int
    {
        $pdfPath = $this->handle($documentId);

        if ($destination) {
            copy($pdfPath, $destination);
            $this->info("PDF copied to: {$destination}");
        } else {
            $this->info("PDF location: {$pdfPath}");
        }

        return self::SUCCESS;
    }
}
