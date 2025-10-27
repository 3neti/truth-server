<?php

namespace App\Actions\TruthTemplates\Compilation;

use LBHurtado\OMRTemplate\Services\HandlebarsCompiler;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CompileHandlebarsTemplate
{
    use AsAction;

    public function __construct(
        protected HandlebarsCompiler $compiler
    ) {}

    public function handle(string $template, array $data): array
    {
        // Extract the actual data payload
        $payload = $this->extractDataPayload($data);

        return $this->compiler->compile($template, $payload);
    }

    public function rules(): array
    {
        return [
            'template' => ['required', 'string'],
            'data' => ['required', 'array'],
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

            \Log::info('Compiling template', [
                'template_length' => strlen($validated['template']),
                'raw_data_keys' => array_keys($validated['data']),
            ]);

            $spec = $this->handle(
                template: $validated['template'],
                data: $validated['data']
            );

            return response()->json([
                'success' => true,
                'spec' => $spec,
            ]);
        } catch (\Exception $e) {
            \Log::error('Compilation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
                'details' => config('app.debug') ? $e->getTraceAsString() : null,
            ], 500);
        }
    }

    public function asCommand(string $templatePath, string $dataPath): int
    {
        $template = file_get_contents($templatePath);
        $data = json_decode(file_get_contents($dataPath), true);

        $spec = $this->handle($template, $data);

        $this->info('Template compiled successfully');
        $this->line('Sections: ' . count($spec['sections'] ?? []));

        return self::SUCCESS;
    }

    /**
     * Extract the actual data payload from the portable data structure.
     * Handles both old format (flat) and new format (with data.data nesting).
     */
    protected function extractDataPayload(array $data): array
    {
        $payload = [];

        // Add fields from data.data if it exists
        if (isset($data['data']) && is_array($data['data'])) {
            $payload = array_merge($payload, $data['data']);
        }

        // Add root-level fields (except 'document' and 'data')
        foreach ($data as $key => $value) {
            if (!in_array($key, ['document', 'data'])) {
                $payload[$key] = $value;
            }
        }

        return $payload;
    }
}
