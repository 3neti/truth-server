<?php

namespace TruthQr\Writers;

use TruthQr\Contracts\TruthQrWriter;
use BaconQrCode\Writer;

// v3 back-ends & styles
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\GDLibRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;

/**
 * Bacon QR writer (v3) with SVG (no deps), PNG (Imagick or GD), and EPS (vector).
 *
 * Defaults to SVG to avoid native deps in CI. If you set format=png,
 * it will try Imagick first; if missing, it falls back to GDLibRenderer.
 * EPS requires no native PHP extensions.
 */
final class BaconQrWriter implements TruthQrWriter
{
    public function __construct(
        private readonly string $fmt = 'svg',   // 'svg' (safe), 'png', or 'eps'
        private readonly int $size = 512,
        private readonly int $margin = 16
    ) {}

    public function write(array $lines): array
    {
        $writer = $this->makeWriter($this->fmt, $this->size, $this->margin);

        $out = [];
        foreach ($lines as $k => $line) {
            $out[$k] = $writer->writeString((string)$line);
        }
        return $out;
    }

    public function format(): string
    {
        return $this->fmt;
    }

    private function makeWriter(string $fmt, int $size, int $margin): Writer
    {
        // v3-safe: start with just the size, then optionally apply margin if supported
        $style = new RendererStyle($size);
        if (method_exists($style, 'withMargin')) {
            /** @var RendererStyle $style */
            $style = $style->withMargin($margin);
        }

        // SVG: no native deps, great default
        if ($fmt === 'svg') {
            $renderer = new ImageRenderer($style, new SvgImageBackEnd());
            return new Writer($renderer);
        }

        // EPS: vector output, no native deps
        if ($fmt === 'eps') {
            $renderer = new ImageRenderer($style, new EpsImageBackEnd());
            return new Writer($renderer);
        }

        // PNG via Imagick (preferred)
        if ($fmt === 'png' && class_exists(ImagickImageBackEnd::class)) {
            $renderer = new ImageRenderer($style, new ImagickImageBackEnd());
            return new Writer($renderer);
        }

        // PNG via GD (v3 uses GDLibRenderer; note the signature is just ($size))
        if ($fmt === 'png' && class_exists(GDLibRenderer::class)) {
            $renderer = new GDLibRenderer($size);
            return new Writer($renderer);
        }

        throw new \RuntimeException(
            $fmt === 'png'
                ? 'PNG output requires Imagick or GD (GDLibRenderer). Use SVG/EPS or install extensions.'
                : "Unsupported format '{$fmt}'."
        );
    }
}
