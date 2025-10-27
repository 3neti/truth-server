<?php

namespace App\Actions\TruthTemplates\Rendering;

use Lorisleiva\Actions\ActionRequest;
use Lorisleiva\Actions\Concerns\AsAction;

class ValidateTemplateSpec
{
    use AsAction;

    public function handle(array $spec): array
    {
        $errors = [];

        // Validate sections
        foreach ($spec['sections'] ?? [] as $index => $section) {
            if (!isset($section['type'])) {
                $errors["sections.{$index}.type"] = ['Section type is required'];
            }
            if (!isset($section['code'])) {
                $errors["sections.{$index}.code"] = ['Section code is required'];
            }
            if (!isset($section['title'])) {
                $errors["sections.{$index}.title"] = ['Section title is required'];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
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
     * Updated: 2025-01-27 - Changed to return JsonResponse directly
     */
    public function asController(ActionRequest $request): \Illuminate\Http\JsonResponse
    {
        $validated = $request->validated();
        $result = $this->handle($validated['spec']);

        return response()->json([
            ...$result,
            'message' => $result['valid'] ? 'Template specification is valid' : null,
        ], $result['valid'] ? 200 : 422);
    }

    public function asCommand(string $specPath): int
    {
        $spec = json_decode(file_get_contents($specPath), true);
        $result = $this->handle($spec);

        if ($result['valid']) {
            $this->info('✓ Template specification is valid');
            return self::SUCCESS;
        }

        $this->error('✗ Template specification has errors:');
        foreach ($result['errors'] as $field => $messages) {
            $this->line("  - {$field}: " . implode(', ', $messages));
        }

        return self::FAILURE;
    }
}
