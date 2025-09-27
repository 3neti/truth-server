<?php

namespace TruthCodec\Contracts;

/**
 * Interface PayloadSerializer
 *
 * Defines a contract for converting structured PHP payloads (arrays) into
 * textual formats (e.g. JSON, YAML) and back again. Implementations must
 * guarantee round-trip safety, strict error handling, and explicit format
 * reporting.
 *
 * Typical consumers of this interface include:
 *  - ChunkEncoder / ChunkDecoder: breaking payloads into transportable chunks
 *  - ChunkAssembler: reconstructing payloads from chunks
 *  - Envelope codecs: wrapping payloads with metadata
 *
 * Contractual obligations:
 *  - encode() must always produce a string that decode() can fully reverse
 *  - decode() must throw \InvalidArgumentException on invalid input
 *  - format() must return a stable identifier string ("json", "yaml", etc.)
 *
 * Example usage:
 *
 * <code>
 * use TruthCodec\Serializer\JsonSerializer;
 *
 * $serializer = new JsonSerializer();
 * $payload = ['type' => 'ER', 'code' => 'XYZ', 'data' => ['hello' => 'world']];
 *
 * $encoded = $serializer->encode($payload); // string
 * $decoded = $serializer->decode($encoded); // array
 *
 * assert($decoded === $payload);
 * assert($serializer->format() === 'json');
 * </code>
 *
 * @package TruthCodec\Contracts
 */
interface PayloadSerializer
{
    /** @return string serialized payload */
    public function encode(array $payload): string;

    /**
     * @throws \InvalidArgumentException on invalid input
     * @return array<string,mixed>
     */
    public function decode(string $text): array;

    /** "json" | "yaml" */
    public function format(): string;
}
