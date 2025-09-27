<?php

use TruthQr\Classify\ClassifySession;
use TruthQr\Assembly\TruthAssembler;
use TruthQr\Stores\ArrayTruthStore;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlTransport;

// Minimal real collaborators (no Redis)
function mkAssembler(): TruthAssembler {
    return new TruthAssembler(
        store:      new ArrayTruthStore(),
        envelope:   new EnvelopeV1Url(),        // truth://v1/ER/<code>/<i>/<n>?c=...
        transport:  new Base64UrlTransport(),
        serializer: new JsonSerializer(),
    );
}

it('locks code on first line and completes/assembles', function () {
    $asm = mkAssembler();
    $sess = new ClassifySession($asm); // code unknown at start

    // Build a tiny payload, publish 2 parts manually
    $code = 'XYZ';
    $payload = ['type'=>'ER','code'=>$code,'data'=>['x'=>1,'y'=>2]];
    $blob = (new JsonSerializer())->encode($payload);
    $frag = (new Base64UrlTransport())->encode($blob);
    $parts = str_split($frag, 40);
    $env   = new EnvelopeV1Url();

    $l1 = $env->header($code, 1, count($parts), $parts[0]);
    $l2 = $env->header($code, 2, count($parts), $parts[1]);

    // Ingest out of order
    $sess->addLine($l2);
    $sess->addLine($l1);

    expect($sess->code())->toBe($code);
    expect($sess->isComplete())->toBeTrue();

    $decoded = $sess->assemble();
    expect($decoded)->toEqual($payload);

    $art = $sess->artifact();
    expect($art)->toBeArray()
        ->and($art['mime'])->toBe('application/json')
        ->and(is_string($art['body']))->toBeTrue();
});

it('rejects lines for a different code once locked', function () {
    $asm = mkAssembler();
    $sess = new ClassifySession($asm); // no code yet

    $env  = new EnvelopeV1Url();
    $blob = (new JsonSerializer())->encode(['type'=>'ER','code'=>'A','data'=>['n'=>1]]);
    $frag = (new Base64UrlTransport())->encode($blob);
    $parts = str_split($frag, 10);

    // First line sets code=A
    $l1 = $env->header('A', 1, 1, $parts[0]);
    $sess->addLine($l1);

    // Now different code should error
    $other = $env->header('B', 1, 1, $parts[0]);
    expect(fn () => $sess->addLine($other))
        ->toThrow(\InvalidArgumentException::class);

    expect($sess->errors())->not()->toBeEmpty();
});
