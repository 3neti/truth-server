<?php

namespace LBHurtado\OMRTemplate\Data;

use Dompdf\Dompdf;
use Spatie\LaravelData\Data;

class OutputBundle extends Data
{
    public function __construct(
        public string $html,
        public Dompdf $pdf,
        public ZoneMapData $zoneMap,
        public ?array $metadata = null,
    ) {}

    public function savePdf(string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $this->pdf->output());
    }

    public function saveJson(string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $this->zoneMap->toJson());
    }

    public function saveMetadata(string $path): void
    {
        $directory = dirname($path);
        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, json_encode($this->metadata, JSON_PRETTY_PRINT));
    }

    public function saveAll(string $basePath): void
    {
        $this->savePdf("{$basePath}.pdf");
        $this->saveJson("{$basePath}.json");
        
        if ($this->metadata) {
            $this->saveMetadata("{$basePath}.meta.json");
        }
    }
}
