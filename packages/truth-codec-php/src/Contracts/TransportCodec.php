<?php

namespace TruthCodec\Contracts;

/**
 * TransportCodec transforms a serialized byte/string "blob" for transit,
 * e.g., base64url or base64url+gzip, and reverses the transform on decode.
 *
 * NOTE: This operates on the *serialized* payload (JSON/YAML), not on the DTO.
 */
interface TransportCodec
{
    /**
     * Encode the serialized blob into a transport-safe string (e.g. base64url).
     */
    public function encode(string $blob): string;

    /**
     * Decode the transport-safe string back into the original serialized blob.
     *
     * @throws \InvalidArgumentException if the input cannot be decoded.
     */
    public function decode(string $blob): string;

    /**
     * Human-friendly name (e.g. "none", "base64url", "base64url+gzip").
     */
    public function name(): string;
}
