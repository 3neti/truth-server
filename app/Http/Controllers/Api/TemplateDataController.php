<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TemplateData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplateDataController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = TemplateData::with(['user', 'template.family'])
            ->orderBy('created_at', 'desc');

        // Filter by template_id if provided
        if ($request->has('template_id')) {
            $query->where('template_id', $request->template_id);
        }

        // Filter by template_ref if provided
        if ($request->has('template_ref')) {
            $query->where('template_ref', $request->template_ref);
        }

        // Filter by search term
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('document_id', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%");
            });
        }

        // Filter by user if authenticated
        if (Auth::check()) {
            $query->where('user_id', Auth::id());
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'document_id' => 'required|string|max:255|unique:template_data',
            'name' => 'nullable|string|max:255',
            'template_id' => 'required|exists:templates,id',
            'template_ref' => 'nullable|string|max:255',
            'portable_format' => 'boolean',
            'json_data' => 'required|array',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['portable_format'] = $validated['portable_format'] ?? false;

        $dataFile = TemplateData::create($validated);

        return response()->json($dataFile->load(['user', 'template.family']), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(TemplateData $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return response()->json($dataFile->load(['user', 'template.family']));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, TemplateData $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'document_id' => 'sometimes|required|string|max:255|unique:template_data,document_id,' . $dataFile->id,
            'name' => 'nullable|string|max:255',
            'template_id' => 'sometimes|required|exists:templates,id',
            'template_ref' => 'nullable|string|max:255',
            'portable_format' => 'boolean',
            'json_data' => 'sometimes|required|array',
        ]);

        $dataFile->update($validated);

        return response()->json($dataFile->load(['user', 'template.family']));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(TemplateData $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $dataFile->delete();

        return response()->json(['message' => 'Data file deleted successfully']);
    }

    /**
     * Validate template data against schema.
     */
    public function validate(TemplateData $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // TODO: Implement schema validation
        // For now, return a simple valid response
        return response()->json([
            'valid' => true,
            'errors' => [],
        ]);
    }

    /**
     * Compile template with data.
     */
    public function compile(TemplateData $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // TODO: Implement template compilation
        // For now, return the json_data as the compiled spec
        return response()->json([
            'compiled_spec' => $dataFile->json_data,
        ]);
    }

    /**
     * Render template data to PDF.
     */
    public function render(TemplateData $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        // TODO: Implement PDF rendering
        // For now, return a placeholder response
        return response()->json([
            'pdf_url' => null,
            'message' => 'PDF rendering not yet implemented',
        ]);
    }
}
