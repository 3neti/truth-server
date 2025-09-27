<?php

namespace TruthCodec\Serializer;

use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Support\CanonicalJson;

/**
 * JsonSerializer
 *
 * Implements the {@see PayloadSerializer} contract using JSON
 * as the serialization format. This serializer ensures
 * deterministic output via {@see CanonicalJson}, which produces
 * stable string encodings (e.g., sorted keys).
 *
 * Responsibilities:
 * - Encode arbitrary associative arrays into canonical JSON strings.
 * - Decode JSON strings back into PHP arrays.
 * - Advertise the supported format identifier ("json").
 *
 * Example usage:
 * ```php
 * $s = new JsonSerializer();
 * $encoded = $s->encode(['b' => 2, 'a' => 1]);
 * // => {"a":1,"b":2} (canonical order)
 *
 * $decoded = $s->decode($encoded);
 * // => ['a' => 1, 'b' => 2]
 * ```
 */
class JsonSerializer implements PayloadSerializer
{
    /**
     * Encode an array payload into a canonical JSON string.
     *
     * @param array<string,mixed> $payload Arbitrary DTO-style associative array.
     *
     * @return string Canonical JSON string representation of the payload.
     */
    public function encode(array $payload): string
    {
        return CanonicalJson::encode($payload);
    }

    /**
     * Decode a JSON string back into a PHP associative array.
     *
     * @param string $text Valid JSON-encoded string.
     *
     * @throws \InvalidArgumentException if the decoded value is not an array.
     * @throws \JsonException if the input is not valid JSON (from JSON_THROW_ON_ERROR).
     *
     * @return array<string,mixed> Decoded payload as an associative array.
     */
    public function decode(string $text): array
    {
        $data = json_decode($text, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('JSON payload must decode to array');
        }
        return $data;
    }

    /**
     * Identify the format handled by this serializer.
     *
     * @return string Always returns "json".
     */
    public function format(): string
    {
        return 'json';
    }
}
