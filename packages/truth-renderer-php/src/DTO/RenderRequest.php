<?php

namespace TruthRenderer\DTO;

/**
 * Immutable value object that describes a render request.
 */
final class RenderRequest
{
    /**
     * @param string $template
     * @param array<string,mixed>|object $data
     * @param array<string,mixed>|object|null $schema JSON Schema (optional)
     * @param array<string,string>|null $partials keyed partials (optional)
     * @param array<string,mixed> $engineFlags LightnCandy compile/runtime options
     * @param 'pdf'|'html'|'md' $format
     * @param 'A4'|'Letter'|string|null $paperSize
     * @param 'portrait'|'landscape'|null $orientation
     * @param string|null $assetsBaseUrl used by Dompdf to resolve relative assets
     * @param int|null $qrSize Optional QR image size in pixels (width and height)
     */
    public function __construct(
        public readonly string $template,
        public readonly array|object $data,
        public readonly ?array $schema = null,
        public readonly ?array $partials = null,
        public readonly array $engineFlags = [],
        public readonly string $format = 'pdf',
        public readonly ?string $paperSize = 'A4',
        public readonly ?string $orientation = 'portrait',
        public readonly ?string $assetsBaseUrl = null,
        public readonly ?int $qrSize = null, // ✅ New field
    ) {}

    /**
     * Helper to normalize data to array for engines that require arrays.
     */
    public function dataAsArray(): array
    {
        return is_array($this->data)
            ? $this->data
            : json_decode(json_encode($this->data, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
    }
}
