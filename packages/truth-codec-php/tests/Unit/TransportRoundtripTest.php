<?php

use TruthCodec\Decode\{ChunkAssembler, ChunkDecoder};
use TruthCodec\Transport\Base64UrlGzipTransport;
use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Contracts\Envelope;

it('roundtrips JSON with base64url transport', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $serializer = new JsonSerializer();
    $transport = app(TransportCodec::class);
    $envelope = app(Envelope::class);

    $enc = new ChunkEncoder($serializer, $transport, $envelope);
    $dec = new ChunkDecoder($envelope);
    $asm = new ChunkAssembler($serializer, $transport);

    $lines = $enc->encodeToChunks($payload, 'XYZ', 32);

    expect($lines)->toBeArray()->and($lines)->not->toBeEmpty();

    // quick check: payload parts look base64url-ish (no '+' or '/')
    foreach ($lines as $line) {
        $part = explode('|', $line, 5)[4] ?? '';
        expect($part)->not->toContain('+')->and($part)->not->toContain('/');
    }

    foreach ($lines as $line) {
        $asm->add($dec->parseLine($line));
    }

    expect($asm->isComplete())->toBeTrue();
    expect($asm->assemble())->toEqual($payload);
});

it('roundtrips JSON with base64url+gzip transport', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world', 'array' => range(1, 50)],
    ];

    $serializer = new JsonSerializer();
    $transport = app(TransportCodec::class);
    $envelope = app(Envelope::class);

    $enc = new ChunkEncoder($serializer, $transport, $envelope);
    $dec = new ChunkDecoder($envelope);
    $asm = new ChunkAssembler($serializer, $transport);

    $lines = $enc->encodeToChunks($payload, 'XYZ', 32);

    foreach ($lines as $line) {
        $asm->add($dec->parseLine($line));
    }

    expect($asm->isComplete())->toBeTrue();
    expect($asm->assemble())->toEqual($payload);
});

it('fails assemble when base64url payload is corrupted', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $serializer = new JsonSerializer();
    $transport = new Base64UrlTransport();
    $envelope = app(Envelope::class);

    $enc = new ChunkEncoder($serializer, $transport, $envelope);
    $dec = new ChunkDecoder($envelope);
    $asm = new ChunkAssembler($serializer, $transport);

    $lines = $enc->encodeToChunks($payload, 'XYZ', 32);

    // Tamper payload part of one chunk (append an illegal char)
    $lines[0] = preg_replace(
        '/^(ER\|v1\|[^|]+\|\d+\/\d+\|)(.+)$/',
        '$1$2$',
        $lines[0]
    );

    foreach ($lines as $line) {
        $asm->add($dec->parseLine($line));
    }

    expect($asm->isComplete())->toBeTrue();
    expect(fn() => $asm->assemble())->toThrow(\InvalidArgumentException::class);
});
