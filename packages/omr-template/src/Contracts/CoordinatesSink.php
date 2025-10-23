<?php

namespace LBHurtado\OMRTemplate\Contracts;

interface CoordinatesSink
{
    /**
     * Register coordinates for a specific element.
     *
     * @param string $type The element type (e.g., 'bubble', 'fiducial', 'timing_mark')
     * @param string $id The unique identifier for the element
     * @param array $coords The coordinates array (x, y, width, height, etc.)
     * @return void
     */
    public function register(string $type, string $id, array $coords): void;

    /**
     * Export all registered coordinates to a JSON file.
     *
     * @param string $documentId The document unique identifier
     * @return string The path to the exported JSON file
     */
    public function export(string $documentId): string;

    /**
     * Get all registered coordinates.
     *
     * @return array
     */
    public function getAll(): array;

    /**
     * Clear all registered coordinates.
     *
     * @return void
     */
    public function clear(): void;
}
