<?php

namespace TruthRenderer\Actions;

use TruthRenderer\Exceptions\TemplateNotFoundException;
use TruthRenderer\Contracts\TemplateRegistryInterface;
use TruthRenderer\Contracts\RendererInterface;
use Lorisleiva\Actions\Concerns\AsAction;
use TruthRenderer\DTO\RenderRequest;
use TruthRenderer\DTO\RenderResult;
use Illuminate\Support\Facades\Log;

/**
 * Action for rendering a document using a template and input data.
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
 *  - `filename` (string, optional): Output filename (used for persisted files)
 */
class RenderDocument
{
    use AsAction;

    public function __construct(
        protected TemplateRegistryInterface $registry,
        protected RendererInterface $renderer,
    ) {}

    /**
     * Handles the render request and returns the rendered document or a saved file.
     *
     * @param array $input Render parameters including template, data, format, etc.
     * @param bool|null $persist Whether to persist to file (true) or return raw content (false).
     *
     * @return RenderResult Render result containing content, format, and filename.
     *
     * @throws TemplateNotFoundException If neither template nor templateName is provided.
     */
    public function handle(array $input, ?bool $persist = false): RenderResult
    {
        $templateName = $input['templateName'] ?? null;
        $template     = $input['template'] ?? null;

        $autoPartials = [];
        $autoSchema = null;
        $assetsBaseFromTemplateDir = null;

        // 1️⃣ Load template and partials if using templateName
        if ($templateName && !$template) {
            Log::info('[RenderDocument] Resolving named template', ['templateName' => $templateName]);

            $template = $this->registry->get($templateName);
            $tplDir = $this->registry->resolveDir($templateName);

            if ($tplDir && is_dir($tplDir)) {
                $partialsDir = $tplDir . DIRECTORY_SEPARATOR . 'partials';
                if (is_dir($partialsDir)) {
                    foreach (scandir($partialsDir) as $file) {
                        if (in_array($file, ['.', '..'])) continue;
                        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                        if (!in_array($ext, ['hbs', 'html'])) continue;

                        $src = file_get_contents($partialsDir . DIRECTORY_SEPARATOR . $file);
                        if ($src !== false) {
                            $name = pathinfo($file, PATHINFO_FILENAME);
                            $autoPartials[$name] = $src;
                        }
                    }
                }

                $schemaFile = $tplDir . DIRECTORY_SEPARATOR . 'schema.json';
                if (is_file($schemaFile)) {
                    $decoded = json_decode(file_get_contents($schemaFile), true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $autoSchema = $decoded;
                        Log::info('[RenderDocument] Loaded schema', ['file' => $schemaFile]);
                    }
                }

                $assetsBaseFromTemplateDir = $tplDir;
            }
        }

        if (!$template) {
            Log::warning('[RenderDocument] Missing template and templateName');
            throw new TemplateNotFoundException();
        }

        // 2️⃣ Merge inputs
        $partials    = $input['partials'] ?? [];
        $data        = $input['data'] ?? [];
        $schema      = $input['schema'] ?? null;
        $engineFlags = $input['engineFlags'] ?? [];
        $format      = $input['format'] ?? 'pdf';
        $paper       = $input['paperSize'] ?? 'A4';
        $orient      = $input['orientation'] ?? 'portrait';

        $assetsBase = $input['assetsBaseUrl'] ?? $assetsBaseFromTemplateDir;
        $filename   = $input['filename'] ?? 'render';

        $partialsFinal = array_merge($autoPartials, is_array($partials) ? $partials : []);
        $schemaFinal   = (is_array($schema) || is_object($schema)) ? $schema : $autoSchema;

        $renderReq = new RenderRequest(
            template:      (string) $template,
            data:          is_array($data) || is_object($data) ? $data : [],
            schema:        $schemaFinal,
            partials:      $partialsFinal,
            engineFlags:   is_array($engineFlags) ? $engineFlags : [],
            format:        $format,
            paperSize:     $paper,
            orientation:   $orient,
            assetsBaseUrl: $assetsBase,
        );

        Log::info('[RenderDocument] Invoking renderer', [
            'format' => $renderReq->format,
            'partials' => count($partialsFinal),
            'hasSchema' => !is_null($schemaFinal),
            'assetsBase' => $assetsBase,
        ]);

        return $persist
            ? $this->renderer->renderToFile($renderReq, $filename)
            : $this->renderer->render($renderReq);
    }
}
