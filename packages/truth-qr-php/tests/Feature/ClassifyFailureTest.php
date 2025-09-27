<?php

declare(strict_types=1);

use TruthQr\Assembly\TruthAssembler;
use TruthQr\Classify\Classify;
use TruthQr\Publishing\TruthQrPublisherFactory;
use TruthQr\Stores\ArrayTruthStore;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlTransport;

/**
 * Incomplete set: omit one published line, verify status & exception on assemble.
 */
it('Classify reports incomplete when a chunk is missing and assemble fails', function () {
    // Arrange assembler & collaborators (pure in-memory to keep test fast)
    $store = new ArrayTruthStore();
    $env   = new EnvelopeV1Url();          // truth://v1/ER/<code>/<i>/<n>?c=...
    $ser   = new JsonSerializer();
    $tx    = new Base64UrlTransport();

    $asm = new TruthAssembler(
        store: $store,
        envelope: $env,
        transport: $tx,
        serializer: $ser
    );

    /** @var TruthQrPublisherFactory $publisherFactory */
    $publisherFactory = app(TruthQrPublisherFactory::class);

    $payload = [
        'type' => 'ER',
        'code' => 'ER-INCOMPLETE-001',
        'data' => [
            'precinct' => 'CURRIMAO-001',
            'tallies'  => [
                ['cand' => 'X', 'votes' => 101],
                ['cand' => 'Y', 'votes' => 99],
            ],
        ],
    ];

    // Publish with small size to force multiple parts
    $lines = $publisherFactory->publish($payload, $payload['code'], [
        'by'   => 'size',
        'size' => 60,
    ]);
    expect(count($lines))->toBeGreaterThan(1);

    // Drop the last chunk to simulate a missing scan
    $missingKey  = array_key_last($lines);
    $missingLine = $lines[$missingKey];
    unset($lines[$missingKey]);

    [$code, $missingIdx] = (function (string $u): array {
        // Parse using your envelope (safer than +1 if keys ever change)
        $env = new \TruthCodec\Envelope\EnvelopeV1Url();
        [, $i, ] = $env->parse($u); // [code, i, n, payload]
        return [$env, $i];
    })($missingLine);


    // Shuffle and ingest via Classify
    shuffle($lines);
    $classify = new Classify($asm);
    $sess = $classify->newSession();
    $sess->addLines($lines);

    // Assert incomplete + status reflects the missing index
    expect($sess->isComplete())->toBeFalse();

    $st = $sess->status();
    expect($st['missing'])->toContain($missingIdx);
    expect($st['code'])->toBe($payload['code']);
    expect($st['received'])->toBe($st['total'] - 1);
//    expect($st['missing'])->toContain($missingKey);

    // Attempting assemble should throw
    expect(fn () => $sess->assemble())->toThrow(RuntimeException::class);

    // Now add the missing line, re-check, and assemble successfully
    $sess->addLine($missingLine);
    expect($sess->isComplete())->toBeTrue();

    $decoded = $sess->assemble();
    expect($decoded)->toEqual($payload);
});

/**
 * Duplicate lines: ingest same chunk multiple times; progress should remain correct.
 */
it('Classify tolerates duplicate lines without overcounting', function () {
    $store = new ArrayTruthStore();
    $env   = new EnvelopeV1Url();
    $ser   = new JsonSerializer();
    $tx    = new Base64UrlTransport();

    $asm = new TruthAssembler(
        store: $store,
        envelope: $env,
        transport: $tx,
        serializer: $ser
    );

    /** @var TruthQrPublisherFactory $publisherFactory */
    $publisherFactory = app(TruthQrPublisherFactory::class);

    $payload = [
        'type' => 'ER',
        'code' => 'ER-DUP-001',
        'data' => ['hello' => 'world'],
    ];

    // Publish with count strategy so we know exactly how many parts to expect.
    $lines = $publisherFactory->publish($payload, $payload['code'], [
        'by'    => 'count',
        'count' => 3,
    ]);
//    expect(array_keys($lines))->toEqual([1,2,3]);

    // Ingest line #1 twice, then #2 once; should still be received=2
    $classify = new Classify($asm);
    $sess = $classify->newSession();

    $sess->addLine($lines[1]);
    $sess->addLine($lines[1]); // duplicate
    $sess->addLine($lines[2]);

    // Ask which index is actually missing
    $st = $sess->status();
    $missingIdx = $st['missing'][0] ?? null;
    expect($missingIdx)->not->toBeNull();

// Build an index→line map by parsing the envelope
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();
    $byIndex = [];
    foreach ($lines as $ln) {
        [$code, $i] = $env->parse($ln);  // [code, index, total, payload]
        $byIndex[(int) $i] = $ln;
    }

// Feed the truly-missing line
    expect(isset($byIndex[$missingIdx]))->toBeTrue();
    $sess->addLine($byIndex[$missingIdx]);

// Now it should complete and assemble
    expect($sess->isComplete())->toBeTrue();

    $decoded = $sess->assemble();
    expect($decoded)->toEqual($payload);
});
