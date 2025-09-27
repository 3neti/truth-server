<?php

namespace TruthCodec\Decode;

use TruthCodec\Contracts\Envelope;

/**
 * ChunkDecoder is responsible for parsing a single encoded
 * chunk line into a structured {@see ChunkHeader}.
 *
 * Each line is expected to follow the EnvelopeV1 format:
 *
 *     ER|v1|<code>|<i>/<N>|<payload-part>
 *
 * where:
 *   - `<code>` is the logical identifier for the envelope (e.g. precinct code).
 *   - `<i>/<N>` indicates the 1-based index of this chunk and the total count.
 *   - `<payload-part>` is a segment of the serialized payload (e.g. base64url).
 *
 * This decoder delegates header parsing to {@see Envelope::parseHeader()}.
 *
 * Typical usage:
 *
 * ```php
 * $decoder = new ChunkDecoder();
 * $header  = $decoder->parseLine($line);
 * echo $header->index;   // 1
 * echo $header->total;   // 5
 * echo $header->code;    // "XYZ"
 * ```
 */
class ChunkDecoder
{
    public function __construct(
        private readonly Envelope $envelope
    ) {}

    /**
     * Parse a single chunk line into a {@see ChunkHeader}.
     *
     * @param string $line One raw encoded line (including prefix, index/total, and payload).
     *
     * @return ChunkHeader Structured header containing code, index, total, and payload part.
     *
     * @throws \InvalidArgumentException If the line does not match the expected format
     *                                   or if {@see Envelope::parseHeader()} fails.
     */
    public function parseLine(string $line): ChunkHeader
    {
        [$code, $idx, $tot, $payload] = $this->envelope->parse($line);

        return new ChunkHeader($code, $idx, $tot, $payload);
    }
}
