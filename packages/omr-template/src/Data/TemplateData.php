<?php

namespace LBHurtado\OMRTemplate\Data;

use Spatie\LaravelData\Data;

class TemplateData extends Data
{
    public function __construct(
        public string $template_id,
        public string $document_type,
        public array $contests_or_sections,
        public string $document_id,
        public string $layout = 'A4',
        public int $dpi = 300,
        public ?array $qr = null,
        public ?array $metadata = null,
        public ?array $fiducials = null,
    ) {}
}
