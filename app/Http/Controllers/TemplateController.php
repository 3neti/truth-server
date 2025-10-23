<?php

namespace App\Http\Controllers;

use App\Models\OmrTemplate;
use App\Models\TemplateInstance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer;
use LBHurtado\OMRTemplate\Services\HandlebarsCompiler;

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
            $spec = $compiler->compile(
                $request->input('template'),
                $request->input('data')
            );

            return response()->json([
                'success' => true,
                'spec' => $spec,
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
        $query = OmrTemplate::query();

        // Filter by category if provided
        if ($request->has('category')) {
            $query->category($request->input('category'));
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
        $template = OmrTemplate::find($id);

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
            $template = OmrTemplate::create([
                'name' => $request->input('name'),
                'description' => $request->input('description'),
                'category' => $request->input('category'),
                'handlebars_template' => $request->input('handlebars_template'),
                'sample_data' => $request->input('sample_data'),
                'schema' => $request->input('schema'),
                'is_public' => $request->input('is_public', false),
                'user_id' => $request->user()?->id,
            ]);

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
        $template = OmrTemplate::find($id);

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
        $template = OmrTemplate::find($id);

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
}
