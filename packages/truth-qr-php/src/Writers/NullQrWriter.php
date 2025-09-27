<?php

namespace TruthQr\Writers;

use TruthQr\Contracts\TruthQrWriter;

/**
 * Test/dummy writer that simply returns "qr:<format>:<content>"
 * for each input line. Great for binding tests and CLI smoke tests.
 */
final class NullQrWriter implements TruthQrWriter
{
    public function __construct(private readonly string $fmt = 'png') {}

    public function write(array $lines): array
    {
        $out = [];
        foreach ($lines as $i => $line) {
            $out[$i] = "qr:{$this->fmt}:" . $line;
        }
        return $out;
    }

    public function format(): string
    {
        return $this->fmt;
    }
}
