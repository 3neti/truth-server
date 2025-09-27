<?php

namespace TruthCodec\Transport;

use TruthCodec\Contracts\TransportCodec;

/**
 * Base64UrlDeflateTransport compresses with raw DEFLATE (gzdeflate),
 * then base64url-encodes the result; the inverse uses base64url decode
 * and gzinflate. This matches the original QR payload pipeline used
 * in your app & tests.
 */
final class Base64UrlDeflateTransport implements TransportCodec
{
    public function encode(string $blob): string
    {
        $deflated = gzdeflate($blob, 9);
        if ($deflated === false) {
            throw new \RuntimeException('Failed to deflate payload.');
        }

        $b64 = base64_encode($deflated);
        // base64url (no padding)
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }

    public function decode(string $blob): string
    {
        // restore base64 padding
        $padded = strtr($blob, '-_', '+/');
        $padLen = (4 - (strlen($padded) % 4)) % 4;
        if ($padLen) {
            $padded .= str_repeat('=', $padLen);
        }

        $bin = base64_decode($padded, true);
        if ($bin === false) {
            throw new \InvalidArgumentException('Invalid base64url payload.');
        }

        $raw = gzinflate($bin);
        if ($raw === false) {
            throw new \InvalidArgumentException('Invalid DEFLATE stream.');
        }

        return $raw;
    }

    public function name(): string
    {
        return 'base64url+deflate';
    }
}
