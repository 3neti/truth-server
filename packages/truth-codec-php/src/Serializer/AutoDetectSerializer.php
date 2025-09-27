<?php

namespace TruthCodec\Serializer;

use TruthCodec\Contracts\PayloadSerializer;

/**
 * A delegating serializer that can automatically detect
 * whether a payload is encoded as JSON or YAML (or other formats),
 * and decode it accordingly.
 *
 * - On **encode()**, it always uses the configured "primary" serializer
 *   (by default the first candidate, typically JSON) to ensure deterministic output.
 * - On **decode()**, it tries candidate serializers in a configurable order,
 *   optionally guided by a lightweight "sniff" of the input string
 *   (e.g. `{` → JSON, `---` → YAML).
 *
 * This class is most useful when inputs come from external sources
 * (files, QR codes, user uploads) where the format is not guaranteed.
 *
 * Example:
 * ```php
 * $auto = new AutoDetectSerializer([
 *     new JsonSerializer(),
 *     new YamlSerializer(),
 * ]);
 *
 * $jsonPayload = $auto->decode('{"a":1}');
 * $yamlPayload = $auto->decode("---\na: 1\n");
 *
 * // Encoding always uses the primary (default JSON):
 * $txt = $auto->encode(['hello' => 'world']); // -> '{"hello":"world"}'
 * ```
 */
final class AutoDetectSerializer implements PayloadSerializer
{
    /** The serializer used for encoding (deterministic, usually JSON). */
    private PayloadSerializer $primary;

    /** Candidate serializers for decoding, in priority order. */
    private array $candidates;

    /**
     * @param PayloadSerializer[]     $candidates Decoding candidates in order of preference.
     * @param PayloadSerializer|null  $primary    Serializer to use for encoding;
     *                                            defaults to the first candidate.
     *
     * @throws \InvalidArgumentException If no candidates are provided.
     */
    public function __construct(array $candidates, ?PayloadSerializer $primary = null)
    {
        if (!$candidates) {
            throw new \InvalidArgumentException('AutoDetectSerializer requires at least one candidate serializer.');
        }
        $this->candidates = $candidates;
        $this->primary = $primary ?? $candidates[0];
    }

    /**
     * Encode a payload deterministically.
     *
     * Delegates to the configured "primary" serializer (e.g. JSON).
     *
     * @param array<string,mixed> $payload Structured data to serialize.
     * @return string Serialized string.
     */
    public function encode(array $payload): string
    {
        return $this->primary->encode($payload);
    }

    /**
     * Attempt to decode text by automatically selecting the correct serializer.
     *
     * Detection strategy:
     *  - Quick sniff:
     *    - JSON if the text starts with `{` or `[`.
     *    - YAML if it starts with `---`.
     *  - Otherwise, try each candidate in order until one succeeds.
     *
     * @param string $text Raw encoded payload.
     * @return array<string,mixed> Decoded payload as an associative array.
     *
     * @throws \InvalidArgumentException If none of the candidate serializers can decode the input.
     */
    public function decode(string $text): array
    {
        $trim = ltrim($text);

        // Format sniff
        $sniffPreferred = [];
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $sniffPreferred = $this->prioritize(JsonSerializer::class);
        } elseif (str_starts_with($trim, '---')) {
            $sniffPreferred = $this->prioritize(YamlSerializer::class);
        }

        $order = $sniffPreferred ?: $this->candidates;

        $errors = [];
        foreach ($order as $s) {
            try {
                return $s->decode($text);
            } catch (\Throwable $e) {
                $errors[] = get_class($s) . ': ' . $e->getMessage();
            }
        }

        throw new \InvalidArgumentException(
            'Auto-detect decode failed (tried ' .
            implode(', ', array_map(fn($s) => $s->format(), $this->candidates)) .
            '). Last errors: ' . implode(' | ', $errors)
        );
    }

    /**
     * Identifier for this serializer.
     *
     * @return string Always "auto".
     */
    public function format(): string
    {
        return 'auto';
    }

    /**
     * Reorder candidates, moving any matching class to the front.
     *
     * @param string $class Fully-qualified serializer class name.
     * @return PayloadSerializer[] Candidates reordered to prioritize the given class.
     */
    private function prioritize(string $class): array
    {
        $front = [];
        $rest  = [];
        foreach ($this->candidates as $s) {
            if ($s instanceof $class) {
                $front[] = $s;
            } else {
                $rest[] = $s;
            }
        }
        return [...$front, ...$rest];
    }
}
