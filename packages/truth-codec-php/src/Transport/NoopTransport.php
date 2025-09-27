<?php

namespace TruthCodec\Transport;

use TruthCodec\Contracts\TransportCodec;

/**
 * NoopTransport leaves the payload untouched. Backwards-compatible default.
 */
final class NoopTransport implements TransportCodec
{
    public function encode(string $blob): string
    {
        return $blob;
    }

    public function decode(string $blob): string
    {
        return $blob;
    }

    public function name(): string
    {
        return 'none';
    }
}
