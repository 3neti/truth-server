<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Template CRUD Controller
 * 
 * Handles database-backed template management (Phase 4 functionality).
 * Core template processing (render, compile, validate) now uses Laravel Actions directly.
 * 
 * @see app/Actions/TruthTemplates/ for core processing actions
 */
class TemplateController extends Controller
{
    /**
     * Display a listing of templates accessible by the current user.
     */
    public function index(Request $request): JsonResponse
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
     * Display the specified template.
     */
    public function show(string $id): JsonResponse
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
     * Store a newly created template in storage.
     */
    public function store(Request $request): JsonResponse
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
     * Update the specified template in storage.
     */
    public function update(Request $request, string $id): JsonResponse
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
     * Remove the specified template from storage.
     */
    public function destroy(Request $request, string $id): JsonResponse
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
