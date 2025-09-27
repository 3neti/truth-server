<?php

namespace TruthRenderer\Http\Controllers;

use Symfony\Component\HttpFoundation\Response;
use TruthRenderer\Template\TemplateRegistry;
use TruthRenderer\DTO\RenderRequest;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use TruthRenderer\Renderer;

/**
 * Class PdfRenderController
 *
 * Handles rendering of templates into PDF (or other supported formats) via a POST endpoint.
 * Validates incoming payload, resolves templates (by name or raw string), then invokes the renderer.
 *
 * Supported formats: pdf (default), html, md
 *
 * Example payload:
 * {
 *   "template": "core:invoice/basic/template", // or raw template string
 *   "data": { ... },                           // JSON data for handlebars
 *   "partials": { ... },                       // Optional partials (inline)
 *   "schema": { ... },                         // Optional JSON schema
 *   "engineFlags": { ... },                    // Optional flags like helpers
 *   "format": "pdf",                           // Optional format
 *   "paperSize": "A4",                         // Optional paper size
 *   "orientation": "portrait",                 // Optional orientation
 *   "assetsBaseUrl": "https://example.com"     // Optional asset prefix
 * }
 * @deprecated
 */
class PdfRenderController extends Controller
{
    /**
     * Handle the render request and return a generated PDF (or other format).
     *
     * @param  Request           $request   Incoming HTTP request containing template and data
     * @param  Renderer          $renderer  The renderer service that compiles and outputs documents
     * @param  TemplateRegistry  $registry  Registry that maps named templates to file contents
     * @return Response                     HTTP response with generated document as binary stream
     *
     * @throws \Illuminate\Validation\ValidationException
     * @throws \TruthRenderer\Exceptions\RenderException
     */
    public function __invoke(Request $request, Renderer $renderer, TemplateRegistry $registry): Response
    {
        $validated = $request->validate([
            'template'      => ['required', 'string'],
            'data'          => ['required', 'array'],
            'partials'      => ['nullable', 'array'],
            'schema'        => ['nullable', 'array'],
            'engineFlags'   => ['nullable', 'array'],
            'format'        => ['nullable', 'in:pdf,html,md'],
            'paperSize'     => ['nullable', 'string'],
            'orientation'   => ['nullable', 'in:portrait,landscape'],
            'assetsBaseUrl' => ['nullable', 'string'],
        ]);

        // Resolve named template from registry or fallback to raw template string
        $template = $registry->has($validated['template'])
            ? $registry->get($validated['template'])
            : $validated['template'];

        // 🪵 Optional debug log for inspection
        logger()->debug('Resolved template', [
            'key' => $validated['template'],
            'content_preview' => mb_substr($template, 0, 200),
        ]);

        $renderRequest = new RenderRequest(
            template:       $template,
            data:           $validated['data'],
            schema:         $validated['schema'] ?? null,
            partials:       $validated['partials'] ?? null,
            engineFlags:    $validated['engineFlags'] ?? [],
            format:         $validated['format'] ?? 'pdf',
            paperSize:      $validated['paperSize'] ?? config('truth-renderer.paper_size', 'A4'),
            orientation:    $validated['orientation'] ?? config('truth-renderer.orientation', 'portrait'),
            assetsBaseUrl:  $validated['assetsBaseUrl'] ?? null,
        );

        $result = $renderer->render($renderRequest);

        return response($result->content)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="truth-output.pdf"');
    }
}
