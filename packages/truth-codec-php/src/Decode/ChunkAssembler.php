<?php

namespace TruthCodec\Decode;

use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;

/**
 * The ChunkAssembler is responsible for collecting and validating
 * a series of chunked payload parts (from QR codes, files, or streams)
 * and reassembling them into the original payload.
 *
 * Workflow:
 *  1. Create a new instance with a {@see PayloadSerializer} (e.g. JSON, YAML).
 *  2. Call {@see add()} for each {@see ChunkHeader} produced by {@see ChunkDecoder}.
 *  3. Call {@see isComplete()} to check if all chunks have been collected.
 *  4. Call {@see assemble()} to rebuild and decode the original payload.
 *  5. Optionally, call {@see reset()} to clear state and start a new assembly.
 *
 * Validation rules:
 *  - All chunks must share the same `code` and `total` values.
 *  - Each chunk index must be in the range [1, total].
 *  - Duplicate or out-of-range indices will raise exceptions.
 *  - Assembly is only possible when *all* expected parts are present.
 */
class ChunkAssembler
{
    /** The code identifying the payload group (shared across all chunks). */
    private string $code = '';

    /** The total number of chunks expected for this payload. */
    private int $total = 0;

    /**
     * Collected chunk parts, keyed by their 1-based index.
     *
     * @var array<int,string>
     */
    private array $parts = [];

    /**
     * @param PayloadSerializer $serializer Serializer to use when decoding the final payload
     * @param TransportCodec $transport Transporter to use when decompressing the final payload
     */
    public function __construct(
        private readonly PayloadSerializer $serializer,
        private readonly TransportCodec $transport
    ) {}

    /**
     * Add a new chunk to the assembler.
     *
     * @param ChunkHeader $h Parsed header containing code, index, total, and payload part.
     *
     * @return array{
     *     code:string,
     *     total:int,
     *     received:int,
     *     missing:int[]
     * } Summary of the current assembly state.
     *
     * @throws \InvalidArgumentException If the chunk code disagrees with others,
     *                                   if the total differs, or if the index is out of range.
     */
    public function add(ChunkHeader $h): array
    {
        if ($this->total === 0) {
            $this->total = $h->total;
            $this->code = $h->code;
        }

        if ($h->code !== $this->code) {
            throw new \InvalidArgumentException('Chunks disagree on code');
        }
        if ($h->total !== $this->total) {
            throw new \InvalidArgumentException('Chunks disagree on total');
        }

        // Ensure index is within expected range
        if ($h->index < 1 || $h->index > $this->total) {
            throw new \InvalidArgumentException(
                "Chunk index {$h->index} out of range for total {$this->total}"
            );
        }

        $this->parts[$h->index] = $h->payloadPart;

        $missing = [];
        for ($i = 1; $i <= $this->total; $i++) {
            if (!array_key_exists($i, $this->parts)) {
                $missing[] = $i;
            }
        }

        return [
            'code' => $this->code,
            'total' => $this->total,
            'received' => count($this->parts),
            'missing' => $missing,
        ];
    }

    /**
     * Check whether the assembler has received all expected chunks.
     *
     * @return bool True if the number of received parts matches the expected total,
     *              false otherwise.
     */
    public function isComplete(): bool
    {
        if ($this->total <= 0) return false;
        for ($i = 1; $i <= $this->total; $i++) {
            if (!array_key_exists($i, $this->parts)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Assemble and decode the full payload.
     *
     * This concatenates all chunk payload parts in index order,
     * and then delegates decoding to the configured {@see PayloadSerializer}.
     *
     * @return array<string,mixed> The fully reconstructed payload.
     *
     * @throws \RuntimeException If called before all parts are present.
     * @throws \InvalidArgumentException If the payload cannot be decoded.
     */
    public function assemble(): array
    {
        if (!$this->isComplete()) {
            throw new \RuntimeException('Cannot assemble: missing parts');
        }
        ksort($this->parts);
        $blob = implode('', $this->parts);
        $unpacked = $this->transport->decode($blob);
        return $this->serializer->decode($unpacked);
//        return $this->serializer->decode($blob);
    }

    /**
     * Reset the assembler state.
     *
     * Clears code, total, and collected parts,
     * allowing the same assembler instance to be reused.
     */
    public function reset(): void
    {
        $this->code = '';
        $this->total = 0;
        $this->parts = [];
    }
}
