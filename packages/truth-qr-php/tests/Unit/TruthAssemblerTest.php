<?php

use TruthQr\Assembly\TruthAssembler;
use TruthQr\Stores\ArrayTruthStore;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Envelope\EnvelopeV1Line;
use TruthCodec\Serializer\JsonSerializer;

/**
 * Test-local identity transport: no packing/unpacking.
 */
class FakeIdentityTransport implements TransportCodec {
    public function encode(string $data): string { return $data; }
    public function decode(string $data): string { return $data; }
    public function name(): string { return 'identity'; }
}

it('ingests chunks from envelope lines and assembles JSON payload', function () {
    // Arrange concrete, dependency-free collaborators
    $store = new ArrayTruthStore();        // in-memory truth store for tests
    $env   = new EnvelopeV1Line();         // config-driven prefix/version ("ER|v1|...")
    $ser   = new JsonSerializer();         // deterministic JSON (canonical order)
    $tx    = new FakeIdentityTransport();  // no packing/unpacking

    $asm = new TruthAssembler(
        store: $store,
        envelope: $env,
        transport: $tx,
        serializer: $ser
    );

    $code = 'XYZ';
    $payload = [
        'type' => 'ER',
        'code' => $code,
        'data' => ['hello' => 'world'],
    ];

    // Serialize → split into 3 roughly equal “QR-sized” parts
    $blob  = $ser->encode($payload);
    $parts = str_split($blob, (int)ceil(strlen($blob) / 3));
    $n     = count($parts);

    // Build envelope lines: PREFIX|v1|<code>|i/N|<payloadPart>
    $lines = [];
    foreach ($parts as $i => $part) {
        $lines[] = $env->header($code, $i + 1, $n, $tx->encode($part));
    }

    // Ingest out of order (exercise persistence/progress)
    $st1 = $asm->ingestLine($lines[2]); // 3/N
    expect($st1['received'])->toBe(1)
        ->and($st1['total'])->toBe($n);

    $st2 = $asm->ingestLine($lines[0]); // 1/N
    expect($st2['received'])->toBe(2)
        ->and($st2['missing'])->toContain(2);

    $st3 = $asm->ingestLine($lines[1]); // 2/N
    expect($st3['received'])->toBe($n)
        ->and($asm->isComplete($code))->toBeTrue();

    // Assemble final payload (decode) and verify
    $decoded = $asm->assemble($code);
    expect($decoded)->toEqual($payload);

    // Artifact is cached (JSON) with correct MIME type
    $art = $asm->artifact($code);

    expect($art)->toBeArray()
        ->and($art['mime'])->toBe('application/json')
        ->and($art['body'])->toBe($blob);
});
