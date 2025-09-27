<?php

namespace TruthQr\Http\Controllers;

use TruthQr\Assembly\Contracts\TruthAssemblerContract;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;

final class TruthIngestController extends Controller
{
    public function __construct(
        private readonly TruthAssemblerContract $assembler
    ) {}

    public function ingest(Request $request)
    {
        $line = (string) $request->input('line', '');
        if ($line === '') {
            return response()->json(['error' => 'Missing line'], 422);
        }

        $status = $this->assembler->ingestLine($line);
        return response()->json($status);
    }

    /**
     * Canonical artifact endpoint.
     * Streams the cached artifact using the standardized keys:
     *   - content_type
     *   - content
     */
    public function artifact(string $code)
    {
        if (!$this->assembler->isComplete($code)) {
            return response()->json(['error' => 'Artifact not ready'], 404);
        }

        $art = $this->assembler->artifact($code);
        if (!$art) {
            return response()->json(['error' => 'artifact_not_found'], 404);
        }

        // Accept both legacy and current shapes
        $mime = $art['content_type'] ?? $art['mime'] ?? 'application/octet-stream';
        $body = $art['content']      ?? $art['body'] ?? '';

        return response($body, 200)->header('Content-Type', $mime);
    }
}
