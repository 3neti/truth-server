<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DataFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DataFileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = DataFile::with('user')
            ->orderBy('created_at', 'desc');

        // Filter by template_ref if provided
        if ($request->has('template_ref')) {
            $query->where('template_ref', $request->template_ref);
        }

        // Filter by category if provided
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by search term
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Show only public or user's own files
        if (Auth::check()) {
            $query->where(function ($q) {
                $q->where('is_public', true)
                  ->orWhere('user_id', Auth::id());
            });
        } else {
            $query->where('is_public', true);
        }

        return response()->json($query->get());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'template_ref' => 'nullable|string|max:255',
            'data' => 'required|array',
            'is_public' => 'boolean',
            'category' => 'string|max:255',
        ]);

        $validated['user_id'] = Auth::id();

        $dataFile = DataFile::create($validated);

        return response()->json($dataFile->load('user'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(DataFile $dataFile)
    {
        // Check permissions
        if (!$dataFile->is_public && $dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        return response()->json($dataFile->load('user'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, DataFile $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'template_ref' => 'nullable|string|max:255',
            'data' => 'sometimes|required|array',
            'is_public' => 'boolean',
            'category' => 'string|max:255',
        ]);

        $dataFile->update($validated);

        return response()->json($dataFile->load('user'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(DataFile $dataFile)
    {
        // Check permissions
        if ($dataFile->user_id !== Auth::id()) {
            abort(403, 'Unauthorized');
        }

        $dataFile->delete();

        return response()->json(['message' => 'Data file deleted successfully']);
    }
}
