<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer;

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
}
