<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TemplateFamily;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class TemplateFamilyController extends Controller
{
    /**
     * Display a listing of template families.
     */
    public function index(Request $request)
    {
        $query = TemplateFamily::query()
            ->with(['templates', 'user'])
            ->accessibleBy(Auth::id());

        // Filter by category
        if ($request->has('category') && $request->category !== 'all') {
            $query->category($request->category);
        }

        // Search by name or description
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $families = $query->latest()->get();

        // Add variant count to each family
        $families->each(function ($family) {
            $family->variants_count = $family->templates->count();
            $family->layout_variants = $family->layoutVariants();
        });

        return response()->json($families);
    }

    /**
     * Store a newly created template family.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => ['required', 'string', Rule::in(['ballot', 'survey', 'test', 'questionnaire'])],
            'repo_url' => 'nullable|url',
            'version' => 'nullable|string|regex:/^\d+\.\d+\.\d+$/',
            'is_public' => 'boolean',
        ]);

        $validated['slug'] = Str::slug($validated['name']);
        $validated['user_id'] = Auth::id();
        $validated['version'] = $validated['version'] ?? '1.0.0';

        // Ensure unique slug
        $originalSlug = $validated['slug'];
        $counter = 1;
        while (TemplateFamily::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $originalSlug . '-' . $counter;
            $counter++;
        }

        $family = TemplateFamily::create($validated);
        $family->load('templates');

        return response()->json($family, 201);
    }

    /**
     * Display the specified template family.
     */
    public function show(string $id)
    {
        $family = TemplateFamily::with(['templates', 'user'])
            ->findOrFail($id);

        // Check access
        if (!$family->is_public && $family->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $family->variants_count = $family->templates->count();
        $family->layout_variants = $family->layoutVariants();

        return response()->json($family);
    }

    /**
     * Update the specified template family.
     */
    public function update(Request $request, string $id)
    {
        $family = TemplateFamily::findOrFail($id);

        // Check ownership
        if ($family->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'category' => ['sometimes', 'required', 'string', Rule::in(['ballot', 'survey', 'test', 'questionnaire'])],
            'repo_url' => 'nullable|url',
            'version' => 'nullable|string|regex:/^\d+\.\d+\.\d+$/',
            'is_public' => 'boolean',
        ]);

        // Update slug if name changed
        if (isset($validated['name']) && $validated['name'] !== $family->name) {
            $validated['slug'] = Str::slug($validated['name']);
            
            // Ensure unique slug
            $originalSlug = $validated['slug'];
            $counter = 1;
            while (TemplateFamily::where('slug', $validated['slug'])
                ->where('id', '!=', $family->id)
                ->exists()) {
                $validated['slug'] = $originalSlug . '-' . $counter;
                $counter++;
            }
        }

        $family->update($validated);
        $family->load('templates');

        return response()->json($family);
    }

    /**
     * Remove the specified template family.
     */
    public function destroy(string $id)
    {
        $family = TemplateFamily::findOrFail($id);

        // Check ownership
        if ($family->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        // Delete the family (templates will have family_id set to null)
        $family->delete();

        return response()->json(['message' => 'Template family deleted successfully']);
    }

    /**
     * Get layout variants for a specific family.
     */
    public function variants(string $id)
    {
        $family = TemplateFamily::with('templates')->findOrFail($id);

        // Check access
        if (!$family->is_public && $family->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'family' => $family,
            'variants' => $family->templates,
        ]);
    }
}
