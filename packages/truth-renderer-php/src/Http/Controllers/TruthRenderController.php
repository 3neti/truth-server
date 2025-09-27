<?php

namespace TruthRenderer\Http\Controllers;

use TruthRenderer\Exceptions\TemplateNotFoundException;
use TruthRenderer\Contracts\TemplateRegistryInterface;
use TruthRenderer\Actions\RenderDocument;
use Illuminate\Http\{Request, Response};
use Illuminate\Support\Facades\Log;
use Illuminate\Routing\Controller;

/**
 * Controller for exposing template listing and rendering endpoints.
 *
 * Provides two endpoints:
 *  - listTemplates(): Returns available templates from the registry.
 *  - render(): Accepts a render request (via JSON payload) and streams HTML, PDF, or Markdown.
 */
class TruthRenderController extends Controller
{
    /**
     * @param TemplateRegistryInterface $registry Template registry for resolving templates by name.
     */
    public function __construct(
        private readonly TemplateRegistryInterface $registry,
    ) {}

    /**
     * List all available templates from the registry.
     *
     * @return Response JSON response with `templates` key containing an array of template names.
     */
    public function listTemplates(): Response
    {
        return response([
            'templates' => $this->registry->list(),
        ]);
    }

    /**
     * Render a template to the requested format.
     *
     * Input can provide either:
     *  - `templateName`: name of a registered template (e.g. "core:hello")
     *  - `template`: raw template source string
     *
     * Other supported inputs:
     *  - `data` (array|object): Render context data
     *  - `schema` (array|object, optional): JSON Schema for validation
     *  - `partials` (array<string,string>, optional): Handlebars partials
     *  - `engineFlags` (array, optional): LightnCandy compile/runtime options
     *  - `format` ("pdf"|"html"|"md"): Output format (default: pdf)
     *  - `paperSize` (string, optional): e.g. "A4", "Letter" (PDF only)
     *  - `orientation` ("portrait"|"landscape", optional): PDF page orientation
     *  - `assetsBaseUrl` (string, optional): Base path for Dompdf to resolve relative assets
     *  - `filename` (string, optional): Output filename (for PDF disposition header)
     *
     * @param Request $req
     * @return \Illuminate\Http\JsonResponse|Response
     *
     * @throws \RuntimeException if template resolution or rendering fails.
     */
    public function render(Request $req)
    {
        Log::info('[TruthRenderController] Incoming render request', [
            'templateName' => $req->get('templateName'),
            'format'       => $req->get('format', 'pdf'),
        ]);

        try {
            $result = RenderDocument::run($req->all())->toArray();

            return match ($result['format']) {
                'pdf' => response($result['content'], 200, [
                    'Content-Type'        => 'application/pdf',
                    'Content-Disposition' => "inline; filename=\"{$result['filename']}.pdf\"",
                ]),
                'html' => response($result['content'], 200, [
                    'Content-Type' => 'text/html; charset=UTF-8',
                ]),
                'md' => response($result['content'], 200, [
                    'Content-Type' => 'text/markdown; charset=UTF-8',
                ]),
                default => response(['error' => 'Unsupported format'], 415),
            };
        } catch (TemplateNotFoundException $e) {
            return response()->json([
                'error' => $e->getMessage(),
            ], 422);
        }
    }
}
