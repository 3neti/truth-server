<?php

namespace LBHurtado\OMRTemplate\Engine;

use LBHurtado\OMRTemplate\Contracts\CoordinatesSink;

class CoordinatesRegistry implements CoordinatesSink
{
    protected array $coordinates = [];
    protected ?array $config = null;

    public function __construct(?array $config = null)
    {
        $this->config = $config;
    }

    public function register(string $type, string $id, array $coords): void
    {
        if (!isset($this->coordinates[$type])) {
            $this->coordinates[$type] = [];
        }

        $this->coordinates[$type][$id] = $coords;
    }

    public function export(string $documentId): string
    {
        $coordsConfig = null;
        if ($this->config !== null) {
            $coordsConfig = $this->config['coords'] ?? [];
        } elseif (function_exists('config')) {
            $coordsConfig = config('omr-template.coords');
        }
        
        $path = $coordsConfig['path'] ?? ($this->config['output_path'] ?? '/tmp') . '/coords';
        
        if (!file_exists($path)) {
            mkdir($path, 0755, true);
        }

        $filename = $path . '/' . $documentId . '.json';
        file_put_contents($filename, json_encode($this->coordinates, JSON_PRETTY_PRINT));

        return $filename;
    }

    public function getAll(): array
    {
        return $this->coordinates;
    }

    public function clear(): void
    {
        $this->coordinates = [];
    }

    public function get(string $type, ?string $id = null): mixed
    {
        if ($id === null) {
            return $this->coordinates[$type] ?? [];
        }

        return $this->coordinates[$type][$id] ?? null;
    }
}
