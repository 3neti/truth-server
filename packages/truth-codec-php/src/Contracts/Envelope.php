<?php

namespace TruthCodec\Contracts;

/**
 * Envelope format contract (v1 family).
 * Implementations encode/decode chunk metadata + payload fragment.
 */
interface Envelope
{
    /**
     * Build a complete transport string (line or URL) for one chunk.
     * Return SHOULD include the payload fragment already appended/encoded.
     *
     * @return string e.g. "ER|v1|CODE|i/N|<payload>" or "truth://v1/ER/CODE/i/N?c=<payload>"
     */
    public function header(string $code, int $index, int $total, string $payloadPart): string;

    /**
     * Parse an encoded line/URL into [code, index, total, payloadPart].
     *
     * @return array{0:string,1:int,2:int,3:string}
     * @throws \InvalidArgumentException on malformed/unsupported input
     */
    public function parse(string $encoded): array;

    /** The logical family prefix (e.g., "ER", "BAL", "TRUTH"). */
    public function prefix(): string;

    /** The semantic version (e.g., "v1"). */
    public function version(): string;

    /** Transport form: "line" or "url". */
    public function transport(): string;
}
