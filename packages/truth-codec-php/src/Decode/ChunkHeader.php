<?php

namespace TruthCodec\Decode;

/**
 * Represents the metadata and payload segment for a single chunk
 * within a multi-part encoded payload (e.g., Election Return, Ballot, Canvass).
 *
 * Each chunk contains:
 *  - A shared `code` identifying which payload group it belongs to.
 *  - An `index` (1-based) indicating its position in the sequence.
 *  - A `total` indicating the total number of chunks expected.
 *  - The actual `payloadPart`, which is a fragment of the serialized payload.
 *
 * Instances of this class are typically produced by {@see ChunkDecoder::parseLine()}
 * and then consumed by {@see ChunkAssembler::add()}.
 *
 * Example header line format (Envelope V1):
 *   ER|v1|XYZ|2/5|<payloadPart>
 */
final class ChunkHeader
{
    /**
     * @param string $code        Identifier code shared across all chunks of the same payload.
     * @param int    $index       1-based position of this chunk within the sequence.
     * @param int    $total       Total number of chunks in this payload set.
     * @param string $payloadPart Fragment of the serialized payload (to be reassembled).
     */
    public function __construct(
        public readonly string $code,
        public readonly int $index,
        public readonly int $total,
        public readonly string $payloadPart
    ) {}
}
