<?php

use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthQr\Assembly\TruthAssembler;
use TruthQr\Stores\ArrayTruthStore;
use TruthQr\Writers\NullQrWriter;
use TruthQr\TruthQrPublisher;

// ---- Round-trip using URL envelope + Base64Url transport (split by SIZE) ----

it('publishes URL lines by size and round-trips via TruthAssembler', function () {
    // Publisher deps
    $serializer = new JsonSerializer();
    $transport  = new Base64UrlTransport();
    $envelope   = new EnvelopeV1Url(); // truth://v1/<prefix>/<code>/<i>/<n>?c=<frag>

    $publisher  = new TruthQrPublisher($serializer, $transport, $envelope);

    // DTO
    $payload = [
        'type' => 'ER',
        'code' => 'DEMO-CODE-001',
        'data' => [
            'precinct' => 'P-0001',
            'tallies'  => [
                ['cand' => 'X', 'votes' => 123],
                ['cand' => 'Y', 'votes' => 99],
            ],
        ],
    ];

    // Publish → lines
    $lines = $publisher->publish($payload, $payload['code'], [
        'by'   => 'size',
        'size' => 50, // force multiple parts
    ]);

    expect($lines)->toBeArray()->and(count($lines))->toBeGreaterThan(1);

    // Now assemble back
    $store = new ArrayTruthStore();
    $asm   = new TruthAssembler(
        store: $store,
        envelope: $envelope,
        transport: $transport,
        serializer: $serializer
    );

    foreach ($lines as $line) {
        $asm->ingestLine($line);
    }

    expect($asm->isComplete($payload['code']))->toBeTrue();

    $decoded = $asm->assemble($payload['code']);
    expect($decoded)->toEqual($payload);

    // And artifact is cached with JSON mime
    $artifact = $asm->artifact($payload['code']);
    expect($artifact)->toBeArray()
        ->and($artifact['mime'])->toBe('application/json')
        ->and(strlen($artifact['body']))->toBeGreaterThan(0);
});

// ---- QR image generation using NullQrWriter (deterministic strings) ----

it('publishes QR images from lines using NullQrWriter', function () {
    $serializer = new JsonSerializer();
    $transport  = new Base64UrlTransport();
    $envelope   = new EnvelopeV1Url();

    $publisher  = new TruthQrPublisher($serializer, $transport, $envelope);
    $writer     = new NullQrWriter('svg'); // returns strings: "qr:svg:<line>"

    $payload = [
        'type' => 'ER',
        'code' => 'DEMO-CODE-IMAGES',
        'data' => ['hello' => 'world'],
    ];

    $images = $publisher->publishQrImages($payload, $payload['code'], $writer, [
        'by' => 'count',
        'count' => 3,
    ]);

    expect($images)->toBeArray()->and(count($images))->toBe(3);
    // NullQrWriter format check
    foreach ($images as $img) {
        expect($img)->toStartWith('qr:svg:truth://v1');
    }
});
