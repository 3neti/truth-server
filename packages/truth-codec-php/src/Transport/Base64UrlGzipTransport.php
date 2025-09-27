<?php

namespace TruthCodec\Transport;

use TruthCodec\Contracts\TransportCodec;

/**
 * Base64UrlGzipTransport compresses with gzip, then base64url encodes.
 */
final class Base64UrlGzipTransport implements TransportCodec
{
    public function encode(string $blob): string
    {
        $gz = gzencode($blob, 9);
        if ($gz === false) {
            throw new \RuntimeException('Failed to gzip-encode payload.');
        }
        $b64 = base64_encode($gz);
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }

    public function decode(string $blob): string
    {
        $padded = strtr($blob, '-_', '+/');
        $len = strlen($padded);
        $pad = (4 - ($len % 4)) % 4;
        if ($pad) $padded .= str_repeat('=', $pad);

        $gz = base64_decode($padded, true);
        if ($gz === false) {
            throw new \InvalidArgumentException('Invalid base64url payload.');
        }

        $raw = gzdecode($gz);
        if ($raw === false) {
            throw new \InvalidArgumentException('Invalid gzip stream.');
        }
        return $raw;
    }

    public function name(): string
    {
        return 'base64url+gzip';
    }
}
