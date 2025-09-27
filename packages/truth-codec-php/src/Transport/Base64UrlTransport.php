<?php

namespace TruthCodec\Transport;

use TruthCodec\Contracts\TransportCodec;

/**
 * Base64UrlTransport encodes/decodes using URL-safe base64 without padding.
 */
final class Base64UrlTransport implements TransportCodec
{
    public function encode(string $blob): string
    {
        $b64 = base64_encode($blob);
        // URL-safe + strip padding
        return rtrim(strtr($b64, '+/', '-_'), '=');
    }

    public function decode(string $blob): string
    {
        // restore padding
        $padLen = (4 - (strlen($blob) % 4)) % 4;
        if ($padLen) $blob .= str_repeat('=', $padLen);

        // URL-safe back to standard alphabet
        $b64 = strtr($blob, '-_', '+/');

        $raw = base64_decode($b64, true);
        if ($raw === false) {
            throw new \InvalidArgumentException('Invalid base64url payload');
        }
        return $raw;
    }
//    public function decode(string $blob): string
//    {
//        // Restore padding to multiple of 4
//        $padded = strtr($blob, '-_', '+/');
//        $len = strlen($padded);
//        $pad = (4 - ($len % 4)) % 4;
//        if ($pad) $padded .= str_repeat('=', $pad);
//
//        $raw = base64_decode($padded, true);
//        if ($raw === false) {
//            throw new \InvalidArgumentException('Invalid base64url payload.');
//        }
//        return $raw;
//    }

    public function name(): string
    {
        return 'base64url';
    }
}
