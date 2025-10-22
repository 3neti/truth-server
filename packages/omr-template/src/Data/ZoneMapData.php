<?php

namespace LBHurtado\OMRTemplate\Data;

use Spatie\LaravelData\Data;

class ZoneMapData extends Data
{
    public function __construct(
        public string $template_id,
        public string $document_type,
        public array $zones,
    ) {}

    public function toJson($options = 0): string
    {
        return json_encode([
            'template_id' => $this->template_id,
            'document_type' => $this->document_type,
            'zones' => $this->zones,
        ], (is_int($options) ? $options : 0) | JSON_PRETTY_PRINT);
    }
}
