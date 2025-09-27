<?php
// packages/truth-codec-php/tests/Transport/Base64UrlDeflateTransportTest.php

use TruthCodec\Transport\Base64UrlDeflateTransport;

it('round-trips text using base64url+deflate', function () {
    $tx = new Base64UrlDeflateTransport();

    $src = json_encode(['a' => 1, 'b' => 'hello/world? yes+no'], JSON_UNESCAPED_SLASHES);
    $enc = $tx->encode($src);

    expect($enc)->toBeString()->and($enc)->not()->toContain('=');

    $dec = $tx->decode($enc);
    expect($dec)->toBe($src);
});

it('rejects invalid input', function () {
    $tx = new Base64UrlDeflateTransport();
    expect(fn() => $tx->decode('not-base64url!!'))->toThrow(InvalidArgumentException::class);
});
