<?php

namespace App\Http\Controllers;

use App\Models\Template;
use App\Models\TemplateInstance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer;
use LBHurtado\OMRTemplate\Services\HandlebarsCompiler;
use Illuminate\Support\Facades\Auth;

class TemplateController extends Controller
{
    /**
     * Render a template from JSON spec and return PDF + coordinates.
     */
    public function render(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'spec' => 'required|array',
            'spec.document' => 'required|array',
            'spec.document.title' => 'required|string',
            'spec.document.unique_id' => 'required|string',
            'spec.sections' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $spec = $request->input('spec');
            $renderer = new SmartLayoutRenderer();
            $result = $renderer->render($spec);

            // Generate public URLs for the PDF and coordinates
            $documentId = $result['document_id'];
            $pdfUrl = url("/api/templates/download/{$documentId}");
            $coordsUrl = url("/api/templates/coords/{$documentId}");

            return response()->json([
                'success' => true,
                'document_id' => $documentId,
                'pdf_url' => $pdfUrl,
                'coords_url' => $coordsUrl,
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
     * Validate a template spec without rendering.
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'spec' => 'required|array',
            'spec.document' => 'required|array',
            'spec.document.title' => 'required|string',
            'spec.document.unique_id' => 'required|string',
            'spec.sections' => 'required|array|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'valid' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        // Additional validation logic
        $spec = $request->input('spec');
        $errors = [];

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

        if (!empty($errors)) {
            return response()->json([
                'valid' => false,
                'errors' => $errors,
            ], 422);
        }

        return response()->json([
            'valid' => true,
            'message' => 'Template specification is valid',
        ]);
    }

    /**
     * Get available layout presets from config.
     */
    public function layouts(): JsonResponse
    {
        $layouts = config('omr-template.layouts', []);
        
        return response()->json([
            'layouts' => $layouts,
        ]);
    }

    /**
     * Get sample JSON templates.
     */
    public function samples(): JsonResponse
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

        return response()->json([
            'samples' => $samples,
        ]);
    }

    /**
     * Download generated PDF.
     */
    public function download(string $documentId)
    {
        $outputPath = config('omr-template.output_path', storage_path('omr-output'));
        $pdfPath = $outputPath . '/' . $documentId . '.pdf';

        if (!file_exists($pdfPath)) {
            return response()->json([
                'error' => 'PDF not found',
            ], 404);
        }

        return response()->file($pdfPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $documentId . '.pdf"',
        ]);
    }

    /**
     * Get coordinates JSON for a document.
     */
    public function coords(string $documentId): JsonResponse
    {
        $coordsConfig = config('omr-template.coords');
        $coordsPath = $coordsConfig['path'] ?? storage_path('app/omr/coords');
        $coordsFile = $coordsPath . '/' . $documentId . '.json';

        if (!file_exists($coordsFile)) {
            return response()->json([
                'error' => 'Coordinates file not found',
            ], 404);
        }

        $coords = json_decode(file_get_contents($coordsFile), true);

        return response()->json([
            'document_id' => $documentId,
            'coordinates' => $coords,
        ]);
    }

    /**
     * Compile a Handlebars template with data to produce JSON specification.
     */
    public function compile(Request $request, HandlebarsCompiler $compiler): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'template' => 'required|string',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = $request->input('template');
            $rawData = $request->input('data');
            
            // Extract data payload same way as validation endpoint
            // Merge data.data with root-level fields (excluding document and data wrapper)
            $data = $this->extractDataPayload($rawData);
            
            \Log::info('Compiling template', [
                'template_length' => strlen($template),
                'raw_data_keys' => array_keys($rawData),
                'extracted_data_keys' => array_keys($data),
                'extracted_data' => json_encode($data, JSON_PRETTY_PRINT),
            ]);
            
            $spec = $compiler->compile($template, $data);

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

    /**
     * Compile from standalone JSON with template reference.
     * Supports portable data files with template_ref pointers.
     */
    public function compileStandalone(Request $request, HandlebarsCompiler $compiler): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'document' => 'required|array',
            'document.template_ref' => 'required|string',
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $templateRef = $request->input('document.template_ref');
            $data = $request->input('data');
            $checksum = $request->input('document.template_checksum');

            // Resolve template from reference
            $resolver = app(\App\Services\Templates\TemplateResolver::class);
            $templateContent = $resolver->resolve($templateRef);

            // Verify checksum if provided
            if ($checksum) {
                $actualChecksum = hash('sha256', $templateContent);
                if ($actualChecksum !== str_replace('sha256:', '', $checksum)) {
                    return response()->json([
                        'success' => false,
                        'error' => 'Template checksum verification failed',
                    ], 400);
                }
            }

            // Compile template with data
            $spec = $compiler->compile($templateContent, $data);

            return response()->json([
                'success' => true,
                'spec' => $spec,
                'template_ref' => $templateRef,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List all templates accessible by the current user.
     */
    public function listTemplates(Request $request): JsonResponse
    {
        $query = Template::query();

        // Include family relationships if requested
        if ($request->has('with_families') && $request->input('with_families')) {
            $query->with('family');
        }

        // Filter by category if provided
        if ($request->has('category')) {
            $query->category($request->input('category'));
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Get templates accessible by user (public + owned)
        $userId = $request->user()?->id;
        $query->accessibleBy($userId);

        $templates = $query->latest()->get();

        return response()->json([
            'success' => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Get a specific template by ID.
     */
    public function getTemplate(string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'template' => $template,
        ]);
    }

    /**
     * Save a new template.
     */
    public function saveTemplate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|string|max:255',
            'handlebars_template' => 'required|string',
            'sample_data' => 'nullable|array',
            'schema' => 'nullable|array',
            'is_public' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $template = Template::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'category' => $request->input('category'),
                'handlebars_template' => $request->input('handlebars_template'),
                'sample_data' => $request->input('sample_data'),
                'schema' => $request->input('schema'),
                'is_public' => $request->input('is_public', false),
                'user_id' => $request->user()?->id,
                'version' => '1.0.0', // Initial version
            ]);

            // Create initial version snapshot
            $template->createVersion('Initial version', $request->user()?->id);

            return response()->json([
                'success' => true,
                'template' => $template,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing template.
     */
    public function updateTemplate(Request $request, string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        // Check if user owns the template
        if ($template->user_id !== $request->user()?->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'category' => 'sometimes|string|max:255',
            'handlebars_template' => 'sometimes|string',
            'sample_data' => 'nullable|array',
            'schema' => 'nullable|array',
            'is_public' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Check if template content changed
            $contentChanged = $request->has('handlebars_template') && 
                $request->input('handlebars_template') !== $template->handlebars_template;

            // Create version before updating if content changed
            if ($contentChanged) {
                $template->createVersion(
                    $request->input('changelog', 'Template updated'),
                    $request->user()?->id
                );
                // Auto-increment patch version
                $template->incrementVersion('patch');
            }

            $template->update($request->only([
                'name',
                'description',
                'category',
                'handlebars_template',
                'sample_data',
                'schema',
                'is_public',
            ]));

            return response()->json([
                'success' => true,
                'template' => $template->fresh(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a template.
     */
    public function deleteTemplate(Request $request, string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        // Check if user owns the template
        if ($template->user_id !== $request->user()?->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        try {
            $template->delete();

            return response()->json([
                'success' => true,
                'message' => 'Template deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get version history for a template.
     */
    public function getVersionHistory(Request $request, string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        $versions = $template->getVersionHistory();

        return response()->json([
            'success' => true,
            'versions' => $versions,
        ]);
    }

    /**
     * Rollback template to a specific version.
     */
    public function rollbackToVersion(Request $request, string $templateId, string $versionId): JsonResponse
    {
        $template = Template::find($templateId);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        // Check if user owns the template
        if ($template->user_id !== $request->user()?->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        try {
            $success = $template->rollbackToVersion((int)$versionId);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'error' => 'Version not found',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'template' => $template->fresh(),
                'message' => 'Successfully rolled back to previous version',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Validate template data against JSON schema.
     */
    public function validateData(Request $request, string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'data' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $request->input('data');
        $result = $template->validateData($data);

        return response()->json([
            'success' => true,
            'valid' => $result['valid'],
            'errors' => $result['errors'],
            'has_schema' => !empty($template->json_schema),
        ]);
    }

    /**
     * Sign a template (generate SHA256 checksum).
     */
    public function signTemplate(Request $request, string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        // Check if user owns the template
        if ($template->user_id !== $request->user()?->id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized',
            ], 403);
        }

        try {
            $template->sign($request->user()?->id);

            return response()->json([
                'success' => true,
                'checksum' => $template->checksum_sha256,
                'verified_at' => $template->verified_at,
                'message' => 'Template signed successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Verify template signature.
     */
    public function verifyTemplate(Request $request, string $id): JsonResponse
    {
        $template = Template::find($id);

        if (!$template) {
            return response()->json([
                'success' => false,
                'error' => 'Template not found',
            ], 404);
        }

        $isValid = $template->verifyChecksum();
        $isSigned = $template->isSigned();
        $isModified = $template->isModified();

        return response()->json([
            'success' => true,
            'is_signed' => $isSigned,
            'is_valid' => $isValid,
            'is_modified' => $isModified,
            'checksum' => $template->checksum_sha256,
            'verified_at' => $template->verified_at,
            'verified_by' => $template->verified_by,
        ]);
    }

    /**
     * Extract the actual data payload from the portable data structure.
     * Handles both old format (flat) and new format (with data.data nesting).
     * Same logic as DataValidationController.
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
