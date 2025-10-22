<?php

namespace LBHurtado\OMRTemplate\Data;

use Spatie\LaravelData\Data;

class ZoneMapData extends Data
{
    public function __construct(
        public string $template_id,
        public string $document_type,
        public array $zones,
        public ?array $fiducials = null,
        public ?string $size = 'A4',
        public ?int $dpi = 300,
    ) {}

    public function toJson($options = 0): string
    {
        $data = [
            'template_id' => $this->template_id,
            'document_type' => $this->document_type,
            'size' => $this->size,
            'dpi' => $this->dpi,
        ];

        if ($this->fiducials) {
            $data['fiducials'] = $this->fiducials;
        }

        $data['zones'] = $this->zones;

        return json_encode($data, (is_int($options) ? $options : 0) | JSON_PRETTY_PRINT);
    }
}
