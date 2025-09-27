<?php

use TruthQr\Classify\Classify;
use TruthQr\Stores\ArrayTruthStore;
use TruthQr\Assembly\TruthAssembler;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlTransport;

it('round-trips via Classify using real assembler and store', function () {
    // Wire real components
    $assembler = new TruthAssembler(
        store:      new ArrayTruthStore(),
        envelope:   new EnvelopeV1Url(),
        transport:  new Base64UrlTransport(),
        serializer: new JsonSerializer(),
    );

    $classify = new Classify($assembler);
    $sess = $classify->newSession(); // code unknown

    $payload = [
        'type' => 'ER',
        'code' => 'ILOCOS-ABC',
        'data' => ['precinct' => '101', 'tallies' => [['cand'=>'X','votes'=>5],['cand'=>'Y','votes'=>3]]],
    ];

    // Create URL lines (2 parts)
    $ser = new JsonSerializer();
    $tx  = new Base64UrlTransport();
    $env = new EnvelopeV1Url();

    $blob  = $ser->encode($payload);
    $frag  = $tx->encode($blob);
    $parts = str_split($frag, 28); // force 2+ parts
    $lines = [];
    foreach ($parts as $i => $p) {
        $lines[] = $env->header($payload['code'], $i+1, count($parts), $p);
    }

    // Feed all lines
    $sess->addLines($lines);

    expect($sess->isComplete())->toBeTrue();

    $decoded = $sess->assemble();
    expect($decoded)->toEqual($payload);
});
