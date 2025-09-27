<?php

namespace TruthRenderer;

use TruthRenderer\Contracts\TemplateRegistryInterface;
use TruthRenderer\DTO\{RenderRequest, RenderResult};
use TruthRenderer\Contracts\RendererInterface;
use TruthRenderer\Engine\HandlebarsEngine;
use TruthRenderer\Validation\Validator;
use Dompdf\{Dompdf, Options};

class Renderer implements RendererInterface
{
    protected ?string $path = null;

    public function getPath(): ?string
    {
        return $this->path;
    }

    public function setPath(string $path): static
    {
        $this->path = $path;

        return $this;
    }

    public function __construct(
        private ?HandlebarsEngine $engine = null,
        private ?Validator $validator = null,
        private ?Options $dompdfOptions = null,
        private ?TemplateRegistryInterface $registry = null // optional
    ) {
        $this->engine         = $this->engine ?? new HandlebarsEngine();
        $this->validator      = $this->validator ?? new Validator();
        $this->dompdfOptions  = $this->dompdfOptions ?? $this->defaultDompdfOptions();
        // $this->registry is optional; when not bound, inline-only behavior remains
    }

    public function render(RenderRequest $request): RenderResult
    {
        // 1) Validate (no-op when schema is null)
        $this->validator->validate($request->dataAsArray(), $request->schema);

        // 2) Resolve template:
        //    - If registry is available and contains an entry with this key, use it.
        //    - Otherwise treat $request->template as literal Handlebars/HTML.
        $template = $request->template;
        if ($this->registry) {
            try {
                // If found, this returns the raw template source.
                $resolved = $this->registry->get($template);
                if (is_string($resolved) && $resolved !== '') {
                    $template = $resolved;
                }
            } catch (\RuntimeException $e) {
                // Not found → keep using inline template; this is intentional.
            }
        }

        if ($template === '') {
            throw new \InvalidArgumentException('Template is empty; provide a template string or a valid registry key.');
        }

        // 3) Render HTML
        $data = $request->dataAsArray();
        $data = $this->base64EncodeSvgQRCodes($data);
        $html = $this->engine->render(
            $template,
            $data,
            $request->partials ?? [],
            $request->engineFlags
        );

        // 4) Output format
        return match ($request->format) {
            'pdf'  => $this->toPdf($html, $request),
            'html' => new RenderResult('html', $html, $this->getPath()),
            'md'   => new RenderResult('md', $this->htmlToMarkdown($html), $this->getPath()),
            default => throw new \InvalidArgumentException("Unsupported format: {$request->format}")
        };
    }

    public function renderToFile(RenderRequest $request, string $path): RenderResult
    {
        // Normalize path first, even before setting
        $dir = dirname($path);
        $basename = basename($path);

        // If no extension, append based on format
        if (!str_contains($basename, '.')) {
            $ext = match ($request->format) {
                'pdf'  => 'pdf',
                'html' => 'html',
                'md'   => 'md',
                default => throw new \InvalidArgumentException("Cannot infer extension for format: {$request->format}"),
            };

            $basename .= ".{$ext}";
            $path = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $basename;
        }

        // Now set full path and render
        $result = $this->setPath($path)->render($request);

        if (!is_dir($dir)) {
            throw new \RuntimeException("Target directory does not exist: $dir");
        }

        if (file_put_contents($path, $result->content) === false) {
            throw new \RuntimeException("Failed to write render output to: $path");
        }

        return $result;
    }

//    public function renderToFile(RenderRequest $request, string $path): RenderResult
//    {
//        $result = $this->setPath($path)->render($request);
//
//        $dir = dirname($path);
//        if (!is_dir($dir)) {
//            throw new \RuntimeException("Target directory does not exist: $dir");
//        }
//        if (file_put_contents($path, $result->content) === false) {
//            throw new \RuntimeException("Failed to write render output to: $path");
//        }
//        return $result;
//    }

    private function toPdf(string $html, RenderRequest $request): RenderResult
    {
        $opts = clone $this->dompdfOptions;

        if ($request->assetsBaseUrl) {
            $opts->setChroot($request->assetsBaseUrl);
            $opts->setIsRemoteEnabled(true);
        }

        $dompdf = new Dompdf($opts);
        $dompdf->loadHtml($html, 'UTF-8');

        $paperSize   = $request->paperSize ?: 'A4';
        $orientation = $request->orientation ?: 'portrait';
        $dompdf->setPaper($paperSize, $orientation);

        $dompdf->render();
        $pdf = $dompdf->output();

        return new RenderResult('pdf', $pdf, $this->getPath());
    }

    private function defaultDompdfOptions(): Options
    {
        $opts = new Options();
        $opts->set('isRemoteEnabled', true);
        $opts->set('isHtml5ParserEnabled', true);
        $opts->set('defaultFont', 'DejaVu Sans');
        return $opts;
    }

    /**
     * Minimal HTML → Markdown fallback.
     */
    private function htmlToMarkdown(string $html): string
    {
        $text = preg_replace('/<(br|\/p|\/div)>/i', "$0\n", $html) ?? $html;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        return trim($text);
    }

    private function base64EncodeSvgQRCodes(array $data, ?int $size = null): array
    {
        if (!isset($data['qrMeta']['qr']) || !is_array($data['qrMeta']['qr'])) {
            return $data;
        }

        // Fallback to config if size not passed
        $size ??= config('truth-renderer.render.qr_size', 200);

        $data['qrMeta']['qr'] = array_map(function ($qr) use ($size) {
            if (
                str_starts_with($qr, '<svg') ||
                str_contains($qr, '<svg')
            ) {
                $svgBase64 = base64_encode($qr);

                return sprintf(
                    '<img src="data:image/svg+xml;base64,%s" width="%d" height="%d" />',
                    $svgBase64,
                    $size,
                    $size
                );
            }

            return $qr;
        }, $data['qrMeta']['qr']);

        return $data;
    }
}
