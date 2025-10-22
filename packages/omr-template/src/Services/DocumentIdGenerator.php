<?php

namespace LBHurtado\OMRTemplate\Services;

use Illuminate\Support\Str;

class DocumentIdGenerator
{
    /**
     * Generate a structured document ID
     *
     * Format: <TYPE>-<GROUP>-<SERIAL>
     * Example: BALLOT-ABC-001-PDF-147
     */
    public function generate(string $type, string $group, int $serial): string
    {
        return strtoupper(
            sprintf(
                '%s-%s-PDF-%s',
                $type,
                $group,
                str_pad((string) $serial, 3, '0', STR_PAD_LEFT)
            )
        );
    }

    /**
     * Generate a UUID-based document ID
     *
     * Format: <TYPE>-<UUID>
     * Example: BALLOT-550e8400-e29b-41d4-a716-446655440000
     */
    public function generateUuid(string $type): string
    {
        return strtoupper($type) . '-' . Str::uuid()->toString();
    }

    /**
     * Generate from identifier (backwards compatible)
     *
     * If identifier looks like a serial (ABC-001), adds random suffix
     * Otherwise uses as-is
     */
    public function fromIdentifier(string $identifier, string $type = 'DOCUMENT'): string
    {
        // If identifier already looks like a full document ID, return it
        if (preg_match('/^[A-Z]+-[A-Z0-9]+-[A-Z]+-\d+$/i', $identifier)) {
            return strtoupper($identifier);
        }

        // If it's a simple serial like "ABC-001" or "001"
        if (preg_match('/^[A-Z0-9-]+$/i', $identifier)) {
            $serial = random_int(100, 999);
            return strtoupper(sprintf('%s-%s-PDF-%03d', $type, $identifier, $serial));
        }

        // Otherwise, sanitize and use as-is
        return strtoupper(preg_replace('/[^A-Z0-9-]/i', '', $identifier));
    }

    /**
     * Parse a document ID into components
     *
     * @return array{type: string, group: string, serial: int}|null
     */
    public function parse(string $documentId): ?array
    {
        if (preg_match('/^([A-Z]+)-([A-Z0-9-]+)-PDF-(\d+)$/i', $documentId, $matches)) {
            return [
                'type' => strtoupper($matches[1]),
                'group' => strtoupper($matches[2]),
                'serial' => (int) $matches[3],
            ];
        }

        return null;
    }

    /**
     * Validate document ID format
     */
    public function isValid(string $documentId): bool
    {
        return (bool) preg_match('/^[A-Z]+-[A-Z0-9-]+-[A-Z]+-\d+$/i', $documentId);
    }
}
