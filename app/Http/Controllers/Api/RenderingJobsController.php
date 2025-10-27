<?php

namespace App\Http\Controllers\Api;

use App\Actions\TruthTemplates\Rendering\RenderTemplateSpec;
use App\Http\Controllers\Controller;
use App\Models\RenderingJob;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RenderingJobsController extends Controller
{
    /**
     * Display a listing of rendering jobs for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $query = RenderingJob::with(['templateData', 'user'])
            ->byUser(Auth::id())
            ->latest();

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Paginate
        $perPage = $request->input('per_page', 15);
        $jobs = $query->paginate($perPage);

        return response()->json($jobs);
    }

    /**
     * Display the specified rendering job.
     */
    public function show(RenderingJob $job): JsonResponse
    {
        // Check ownership
        if ($job->user_id !== Auth::id()) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $job->load(['templateData', 'user']);

        return response()->json($job);
    }

    /**
     * Create and dispatch a new rendering job.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'template_data_id' => 'nullable|exists:template_data,id',
            'spec' => 'required|array',
        ]);

        // Create the job record
        $job = RenderingJob::create([
            'template_data_id' => $validated['template_data_id'] ?? null,
            'user_id' => Auth::id(),
            'status' => 'pending',
            'metadata' => [
                'spec' => $validated['spec'],
            ],
        ]);

        // Dispatch the rendering job
        RenderTemplateSpec::dispatch($validated['spec'], $job->id)
            ->onQueue('rendering');

        return response()->json($job, 201);
    }

    /**
     * Retry a failed rendering job.
     */
    public function retry(RenderingJob $job): JsonResponse
    {
        // Check ownership
        if ($job->user_id !== Auth::id()) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        // Check if can retry
        if (!$job->canRetry()) {
            return response()->json([
                'error' => 'Job cannot be retried'
            ], 422);
        }

        // Reset job status
        $job->update([
            'status' => 'pending',
            'error_message' => null,
            'progress' => 0,
            'started_at' => null,
            'completed_at' => null,
        ]);

        // Re-dispatch the job
        $spec = $job->metadata['spec'] ?? null;
        if ($spec) {
            RenderTemplateSpec::dispatch($spec, $job->id)
                ->onQueue('rendering');
        }

        return response()->json($job->fresh());
    }

    /**
     * Cancel a pending or processing rendering job.
     */
    public function cancel(RenderingJob $job): JsonResponse
    {
        // Check ownership
        if ($job->user_id !== Auth::id()) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        // Can only cancel pending or processing jobs
        if (!in_array($job->status, ['pending', 'processing'])) {
            return response()->json([
                'error' => 'Job cannot be cancelled'
            ], 422);
        }

        $job->update([
            'status' => 'cancelled',
            'completed_at' => now(),
        ]);

        return response()->json($job);
    }

    /**
     * Delete a rendering job.
     */
    public function destroy(RenderingJob $job): JsonResponse
    {
        // Check ownership
        if ($job->user_id !== Auth::id()) {
            return response()->json([
                'error' => 'Unauthorized'
            ], 403);
        }

        $job->delete();

        return response()->json([
            'message' => 'Rendering job deleted successfully'
        ]);
    }
}
