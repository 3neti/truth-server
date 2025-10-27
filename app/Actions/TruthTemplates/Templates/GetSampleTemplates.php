<?php

namespace App\Actions\TruthTemplates\Templates;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class GetSampleTemplates
{
    use AsAction;

    public function handle(): array
    {
        $samplesPath = base_path('packages/omr-template/resources/samples');
        $samples = [];

        if (is_dir($samplesPath)) {
            $files = glob($samplesPath . '/*.json');
            foreach ($files as $file) {
                $content = json_decode(file_get_contents($file), true);
                $samples[] = [
                    'name' => basename($file, '.json'),
                    'filename' => basename($file),
                    'spec' => $content,
                ];
            }
        }

        return $samples;
    }

    /**
     * HTTP Controller invocation.
     * Returns JsonResponse for direct route binding.
     * Updated: 2025-01-27 - Changed return type from array to JsonResponse
     */
    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        $samples = $this->handle();

        return response()->json([
            'samples' => $samples,
        ]);
    }

    public function asCommand(): int
    {
        $samples = $this->handle();
        
        $this->info('Available sample templates:');
        
        foreach ($samples as $sample) {
            $this->line("  - {$sample['name']} ({$sample['filename']})");
        }
        
        return self::SUCCESS;
    }
}
