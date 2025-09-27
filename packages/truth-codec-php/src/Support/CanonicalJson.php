<?php

namespace TruthCodec\Support;

/**
 * CanonicalJson
 *
 * Provides deterministic JSON encoding for payloads.
 *
 * Unlike regular `json_encode`, this class ensures:
 * - All associative keys are sorted recursively in lexicographic order.
 * - No additional whitespace or pretty-printing is used.
 * - Encoded output is stable (same input always produces same output).
 *
 * This is useful for cryptographic signing, hashing, or
 * cross-system verification where JSON string stability matters.
 *
 * Example:
 * ```php
 * $data = ['b' => 2, 'a' => 1];
 * $json = CanonicalJson::encode($data);
 * // => {"a":1,"b":2}
 * ```
 */
final class CanonicalJson
{
    /**
     * Encode an array into a canonical JSON string.
     *
     * - Keys are sorted recursively before encoding.
     * - Uses `JSON_UNESCAPED_SLASHES` for compactness.
     *
     * @param array<string,mixed> $data Input associative or nested array.
     *
     * @throws \RuntimeException If encoding fails.
     *
     * @return string Canonical JSON representation of the input.
     */
    public static function encode(array $data): string
    {
        // Recursively sort keys
        $sorted = self::ksortRecursive($data);

        $json = json_encode($sorted, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode canonical JSON');
        }

        return $json;
    }

    /**
     * Recursively sort array keys to enforce deterministic ordering.
     *
     * @param array<string,mixed> $data Input array.
     *
     * @return array<string,mixed> Sorted array with nested arrays also sorted.
     */
    private static function ksortRecursive(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::ksortRecursive($v);
            }
        }

        ksort($data);
        return $data;
    }
}
