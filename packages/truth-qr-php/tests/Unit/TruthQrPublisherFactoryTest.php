<?php

use TruthQr\Publishing\TruthQrPublisherFactory;
use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthQr\TruthQrPublisher;

use TruthCodec\Envelope\EnvelopeV1Url;

it('merges config defaults and delegates to publisher', function () {
    // Bind real collaborators (or fakes/mocks if you prefer)
    $publisher = new TruthQrPublisher(
        serializer: new JsonSerializer(),
        transport:  new Base64UrlTransport(),
        envelope:   new EnvelopeV1Url(),
    );

    // Simulate config defaults
    $factory = new TruthQrPublisherFactory($publisher, [
        'strategy' => 'count',
        'count'    => 2,
        'size'     => 800,
    ]);

    $payload = ['type' => 'ER', 'code' => 'XYZ', 'data' => ['a' => 1, 'b' => 'dsaskdlsadjlksadjklsadjklsajdlksadjlksadjlksadjlsadjlsakdjlsakdjlksadjlasdjlsajdlksdjlsakdjlas']];

    $urls = $factory->publish($payload, 'XYZ', ['by' => 'count', 'count' => 2]); // uses defaults
    expect($urls)->toBeArray()->and(count($urls))->toBe(2);
    $urls = $factory->publish($payload, 'XYZ'); // uses defaults
    expect($urls)->toBeArray()->and(count($urls))->toBe(2);

    // Per-call override
    $urls2 = $factory->publish($payload, 'XYZ', ['by' => 'size', 'size' => 50]);
    expect($urls2)->toBeArray()->and(count($urls2))->toBeGreaterThan(2);
});
