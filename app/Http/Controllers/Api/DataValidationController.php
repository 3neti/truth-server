<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataFile;
use App\Models\OmrTemplate;
use App\Models\TemplateFamily;
use Illuminate\Http\Request;
use LightnCandy\LightnCandy;

class DataValidationController extends Controller
{
    /**
     * Validate a data file against its template reference.
     */
    public function validateDataFile(Request $request, DataFile $dataFile)
    {
        // Extract template_ref from data JSON
        $data = $dataFile->data;
        $templateRef = $data['document']['template_ref'] ?? $dataFile->template_ref ?? null;

        if (!$templateRef) {
            return response()->json([
                'valid' => false,
                'error' => 'No template reference found',
            ], 400);
        }

        // Resolve template
        $template = $this->resolveTemplate($templateRef);

        if (!$template) {
            return response()->json([
                'valid' => false,
                'error' => "Template not found: {$templateRef}",
            ], 404);
        }

        // Extract the actual data payload (merge data.data with root level fields)
        $dataPayload = $this->extractDataPayload($data);

        // Compile template with data - if it compiles successfully, data is valid
        $compilationResult = $this->compileTemplate($template, $dataPayload);

        if (!$compilationResult['success']) {
            return response()->json([
                'valid' => false,
                'error' => 'Template compilation failed - data does not match template structure',
                'compilation_error' => $compilationResult['error'],
                'template_ref' => $templateRef,
                'template_name' => $template['name'] ?? 'Unknown',
            ], 200); // Use 200 so frontend gets the response
        }

        // If compilation succeeded, validate the output is valid JSON
        $spec = $compilationResult['spec'];
        if (!is_array($spec) || empty($spec)) {
            return response()->json([
                'valid' => false,
                'error' => 'Template compiled but produced invalid output',
                'template_ref' => $templateRef,
                'template_name' => $template['name'] ?? 'Unknown',
            ], 200);
        }

        // Success! Template compiled and produced valid spec
        return response()->json([
            'valid' => true,
            'template_ref' => $templateRef,
            'template_name' => $template['name'] ?? 'Unknown',
            'message' => 'Data is valid and successfully compiled',
            'spec_preview' => [
                'document' => $spec['document'] ?? null,
                'sections_count' => isset($spec['sections']) ? count($spec['sections']) : 0,
            ],
        ]);
    }

    /**
     * Validate raw data against a template reference.
     */
    public function validateData(Request $request)
    {
        $validated = $request->validate([
            'template_ref' => 'required|string',
            'data' => 'required|array',
        ]);

        $templateRef = $validated['template_ref'];
        $data = $validated['data'];

        // Resolve template
        $template = $this->resolveTemplate($templateRef);

        if (!$template) {
            return response()->json([
                'valid' => false,
                'error' => "Template not found: {$templateRef}",
            ], 404);
        }

        // Compile template with data
        $compilationResult = $this->compileTemplate($template, $data);

        if (!$compilationResult['success']) {
            return response()->json([
                'valid' => false,
                'error' => 'Template compilation failed',
                'compilation_error' => $compilationResult['error'],
            ], 422);
        }

        // Extract required fields from template
        $requiredFields = $this->extractRequiredFields($template);

        // Validate data against required fields
        $validationResult = $this->validateFields($data, $requiredFields);

        return response()->json([
            'valid' => $validationResult['valid'],
            'template_ref' => $templateRef,
            'template_name' => $template['name'] ?? 'Unknown',
            'required_fields' => $requiredFields,
            'missing_fields' => $validationResult['missing_fields'],
            'extra_fields' => $validationResult['extra_fields'],
            'field_count' => [
                'required' => count($requiredFields),
                'provided' => count(array_keys($data)),
                'missing' => count($validationResult['missing_fields']),
            ],
            'compiled_spec' => $compilationResult['spec'] ?? null,
        ]);
    }

    /**
     * Resolve template reference to actual template.
     */
    private function resolveTemplate(string $templateRef): ?array
    {
        // Parse template_ref
        if (str_starts_with($templateRef, 'local:')) {
            $ref = substr($templateRef, 6); // Remove "local:"
            
            // Check if it's family/variant format
            if (str_contains($ref, '/')) {
                [$familySlug, $variant] = explode('/', $ref, 2);
                
                $family = TemplateFamily::where('slug', $familySlug)->first();
                if (!$family) {
                    return null;
                }
                
                $template = OmrTemplate::where('family_id', $family->id)
                    ->where('layout_variant', $variant)
                    ->first();
                
                if (!$template) {
                    return null;
                }
                
                return [
                    'id' => $template->id,
                    'name' => $template->name,
                    'handlebars_template' => $template->handlebars_template,
                    'sample_data' => $template->sample_data,
                ];
            }
            
            // Direct ID reference
            $template = OmrTemplate::find((int) $ref);
            if (!$template) {
                return null;
            }
            
            return [
                'id' => $template->id,
                'name' => $template->name,
                'handlebars_template' => $template->handlebars_template,
                'sample_data' => $template->sample_data,
            ];
        }
        
        // TODO: Handle github:, http:, etc.
        return null;
    }

    /**
     * Compile template with data directly using LightnCandy.
     */
    private function compileTemplate(array $template, array $data): array
    {
        try {
            // Compile the Handlebars template
            $phpStr = LightnCandy::compile($template['handlebars_template'], [
                'flags' => LightnCandy::FLAG_HANDLEBARSJS | LightnCandy::FLAG_ERROR_EXCEPTION,
            ]);
            
            $renderer = LightnCandy::prepare($phpStr);
            
            // Render the template with data
            $rendered = $renderer($data);
            
            // Parse the rendered JSON
            $spec = json_decode($rendered, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON output from template: ' . json_last_error_msg(),
                ];
            }
            
            return [
                'success' => true,
                'spec' => $spec,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Extract complete schema including nested structures.
     */
    private function extractRequiredSchema(array $template): array
    {
        $handlebars = $template['handlebars_template'];
        $schema = [];
        
        // Parse root-level fields and each blocks
        $schema = $this->parseHandlebarsSchema($handlebars, 0);
        
        return $schema;
    }
    
    /**
     * Recursively parse Handlebars template to extract schema.
     */
    private function parseHandlebarsSchema(string $template, int $depth = 0): array
    {
        $schema = [];
        
        // Extract simple variables {{varName}}
        preg_match_all('/\{\{([^#\/}]+)\}\}/', $template, $matches);
        foreach ($matches[1] as $field) {
            $field = trim($field);
            if (!in_array($field, ['each', 'if', 'unless', 'with', '@last', '@first', '@index', 'else'])) {
                $rootField = explode('.', $field)[0];
                if (!isset($schema[$rootField])) {
                    $schema[$rootField] = 'scalar';
                }
            }
        }
        
        // Extract {{#each arrayName}} blocks
        preg_match_all('/\{\{#each\s+([^}]+)\}\}(.*?)\{\{\/each\}\}/s', $template, $eachBlocks, PREG_SET_ORDER);
        
        foreach ($eachBlocks as $block) {
            $arrayName = trim($block[1]);
            $blockContent = $block[2];
            
            // Parse the content inside the each block
            $itemSchema = $this->parseHandlebarsSchema($blockContent, $depth + 1);
            
            $schema[$arrayName] = [
                'type' => 'array',
                'item_schema' => $itemSchema,
            ];
            
            // Remove the each block from template to avoid re-parsing
            $template = str_replace($block[0], '', $template);
        }
        
        return $schema;
    }
    
    /**
     * Validate data against schema.
     */
    private function validateSchema(array $data, array $schema, string $path = ''): array
    {
        $errors = [];
        
        foreach ($schema as $field => $fieldSchema) {
            $fieldPath = $path ? "{$path}.{$field}" : $field;
            
            // Check if field exists
            if (!isset($data[$field])) {
                $errors[] = "Missing field: {$fieldPath}";
                continue;
            }
            
            // If it's an array schema, validate each item
            if (is_array($fieldSchema) && isset($fieldSchema['type']) && $fieldSchema['type'] === 'array') {
                if (!is_array($data[$field])) {
                    $errors[] = "Field {$fieldPath} should be an array";
                    continue;
                }
                
                // Validate each item in the array
                foreach ($data[$field] as $index => $item) {
                    if (!is_array($item)) {
                        $errors[] = "Item {$fieldPath}[{$index}] should be an object";
                        continue;
                    }
                    
                    $itemErrors = $this->validateSchema($item, $fieldSchema['item_schema'], "{$fieldPath}[{$index}]");
                    $errors = array_merge($errors, $itemErrors['errors']);
                }
            }
        }
        
        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
        ];
    }

    /**
     * Extract required root-level fields from Handlebars template (legacy).
     * Only extracts fields that appear outside of {{#each}} blocks.
     */
    private function extractRequiredFields(array $template): array
    {
        $handlebars = $template['handlebars_template'];
        
        // Remove content inside {{#each}}...{{/each}} blocks to avoid capturing nested fields
        $withoutEachBlocks = preg_replace('/\{\{#each [^}]+\}\}.*?\{\{\/each\}\}/s', '', $handlebars);
        
        // Extract {{variable}} patterns from the cleaned template
        preg_match_all('/\{\{([^}#\/]+)\}\}/', $withoutEachBlocks, $matches);
        
        $fields = [];
        foreach ($matches[1] as $field) {
            $field = trim($field);
            
            // Skip Handlebars helpers and block expressions
            if (in_array($field, ['each', 'if', 'unless', 'with', '@last', '@first', '@index', 'else'])) {
                continue;
            }
            
            // Extract root field name (before first dot)
            $rootField = explode('.', $field)[0];
            
            if (!in_array($rootField, $fields) && !empty($rootField)) {
                $fields[] = $rootField;
            }
        }
        
        // Also extract fields referenced in {{#each fieldName}} but only at root level
        // We need to track nesting depth to avoid nested each blocks
        $lines = explode("\n", $handlebars);
        $depth = 0;
        foreach ($lines as $line) {
            // Count opening blocks
            if (preg_match('/\{\{#each\s+([^}\s]+)\}\}/', $line, $match)) {
                if ($depth === 0) {
                    // This is a root-level {{#each}}
                    $eachField = trim($match[1]);
                    $rootField = explode('.', $eachField)[0];
                    if (!in_array($rootField, $fields) && !empty($rootField)) {
                        $fields[] = $rootField;
                    }
                }
                $depth++;
            }
            // Count closing blocks
            if (preg_match('/\{\{\/each\}\}/', $line)) {
                $depth--;
            }
        }
        
        return array_values(array_unique($fields));
    }

    /**
     * Validate provided data against required fields.
     */
    private function validateFields(array $data, array $requiredFields): array
    {
        $providedFields = array_keys($data);
        
        $missingFields = array_diff($requiredFields, $providedFields);
        $extraFields = array_diff($providedFields, $requiredFields);
        
        return [
            'valid' => count($missingFields) === 0,
            'missing_fields' => array_values($missingFields),
            'extra_fields' => array_values($extraFields),
        ];
    }

    /**
     * Extract the actual data payload from the portable data structure.
     * Handles both old format (flat) and new format (with data.data nesting).
     */
    private function extractDataPayload(array $data): array
    {
        // New format: {document: {...}, data: {...}, positions: [...]}
        // We need to merge data.data with root-level fields (excluding document)
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
