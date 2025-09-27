<?php

namespace TruthCodec\Serializer;

use TruthCodec\Contracts\PayloadSerializer;
use Symfony\Component\Yaml\Yaml;

/**
 * YamlSerializer
 *
 * Implements the {@see PayloadSerializer} contract using YAML
 * as the serialization format. This serializer leverages the
 * Symfony YAML component for robust encoding and decoding.
 *
 * Responsibilities:
 * - Encode arbitrary associative arrays into YAML text.
 * - Decode YAML text back into PHP arrays.
 * - Advertise the supported format identifier ("yaml").
 *
 * Example usage:
 * ```php
 * $s = new YamlSerializer();
 * $encoded = $s->encode(['a' => 1, 'b' => [2,3]]);
 * // =>
 * // a: 1
 * // b:
 * //   - 2
 * //   - 3
 *
 * $decoded = $s->decode($encoded);
 * // => ['a' => 1, 'b' => [2, 3]]
 * ```
 */
class YamlSerializer implements PayloadSerializer
{
    /**
     * Encode an array payload into a YAML string.
     *
     * @param array<string,mixed> $payload Arbitrary DTO-style associative array.
     *
     * @return string YAML-encoded representation of the payload.
     */
    public function encode(array $payload): string
    {
        return Yaml::dump($payload, 10, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
    }

    /**
     * Decode a YAML string back into a PHP associative array.
     *
     * @param string $text Valid YAML-encoded string.
     *
     * @throws \InvalidArgumentException if the decoded value is not an array.
     *
     * @return array<string,mixed> Decoded payload as an associative array.
     */
    public function decode(string $text): array
    {
        $data = Yaml::parse($text);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('YAML payload must decode to array');
        }
        return $data;
    }

    /**
     * Identify the format handled by this serializer.
     *
     * @return string Always returns "yaml".
     */
    public function format(): string
    {
        return 'yaml';
    }
}
