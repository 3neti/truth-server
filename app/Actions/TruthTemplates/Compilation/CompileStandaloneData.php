<?php

namespace App\Actions\TruthTemplates\Compilation;

use App\Services\Templates\TemplateResolver;
use LBHurtado\OMRTemplate\Services\HandlebarsCompiler;
use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class CompileStandaloneData
{
    use AsAction;

    public function __construct(
        protected TemplateResolver $resolver,
        protected HandlebarsCompiler $compiler
    ) {}

    public function handle(
        string $templateRef,
        array $data,
        ?string $checksum = null
    ): array {
        // Resolve template from reference
        $templateContent = $this->resolver->resolve($templateRef);

        // Verify checksum if provided
        if ($checksum) {
            $actualChecksum = hash('sha256', $templateContent);
            if ($actualChecksum !== str_replace('sha256:', '', $checksum)) {
                throw new \RuntimeException('Template checksum verification failed');
            }
        }

        // Compile template with data
        return $this->compiler->compile($templateContent, $data);
    }

    public function rules(): array
    {
        return [
            'document' => ['required', 'array'],
            'document.template_ref' => ['required', 'string'],
            'document.template_checksum' => ['nullable', 'string'],
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

            $spec = $this->handle(
                templateRef: $validated['document']['template_ref'],
                data: $validated['data'],
                checksum: $validated['document']['template_checksum'] ?? null
            );

            return response()->json([
                'success' => true,
                'spec' => $spec,
                'template_ref' => $validated['document']['template_ref'],
            ]);
        } catch (\RuntimeException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function asCommand(string $dataPath): int
    {
        $fileData = json_decode(file_get_contents($dataPath), true);

        $spec = $this->handle(
            templateRef: $fileData['document']['template_ref'],
            data: $fileData['data'],
            checksum: $fileData['document']['template_checksum'] ?? null
        );

        $this->info('Standalone data compiled successfully');
        $this->line('Template ref: ' . $fileData['document']['template_ref']);
        $this->line('Sections: ' . count($spec['sections'] ?? []));

        return self::SUCCESS;
    }
}
