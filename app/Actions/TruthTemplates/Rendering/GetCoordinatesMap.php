<?php

namespace App\Actions\TruthTemplates\Rendering;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetCoordinatesMap
{
    use AsAction;

    public function handle(string $documentId): array
    {
        $coordsConfig = config('omr-template.coords');
        $coordsPath = $coordsConfig['path'] ?? storage_path('app/omr/coords');
        $coordsFile = $coordsPath . '/' . $documentId . '.json';

        if (!file_exists($coordsFile)) {
            throw new \RuntimeException('Coordinates file not found');
        }

        return json_decode(file_get_contents($coordsFile), true);
    }

    /**
     * HTTP Controller invocation.
     * Returns JsonResponse for direct route binding.
     * Updated: 2025-01-27 - Changed return type from array to JsonResponse
     */
    public function asController(ActionRequest $request, string $documentId): \Illuminate\Http\JsonResponse
    {
        try {
            $coords = $this->handle($documentId);

            return response()->json([
                'document_id' => $documentId,
                'coordinates' => $coords,
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 404);
        }
    }

    public function asCommand(string $documentId): int
    {
        $coords = $this->handle($documentId);

        $this->info("Coordinates for document: {$documentId}");
        $this->line(json_encode($coords, JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
