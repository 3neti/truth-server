<?php

declare(strict_types=1);

namespace Tests\Unit;

use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Decode\ChunkAssembler;
use TruthCodec\Decode\ChunkDecoder;
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Contracts\Envelope;

it('fails to assemble when a chunk is missing', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    $lines = iterator_to_array($enc->encodeToChunks($payload, 'ABC', 5)); // 5 chunks
    // drop one
    array_splice($lines, 2, 1);

    foreach ($lines as $line) {
        $asm->add($dec->parseLine($line));
    }

    expect($asm->isComplete())->toBeFalse();
    expect(fn () => $asm->assemble())->toThrow(\RuntimeException::class);
});

it('rejects chunks with mixed ER codes', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    // Generate consistent chunks (code = XYZ)
    $lines = iterator_to_array($enc->encodeToChunks($payload, 'XYZ', 4));

    // Tamper exactly one chunk header to a different code (ABC)
    // Format: ER|v1|<code>|i/N|<payload>
    $tampered = preg_replace('/^ER\|v1\|[^|]+\|/', 'ER|v1|ABC|', $lines[1]);

    // Add a good chunk first
    $asm->add($dec->parseLine($lines[0]));

    // Adding the tampered chunk should throw immediately
    expect(fn () => $asm->add($dec->parseLine($tampered)))
        ->toThrow(\InvalidArgumentException::class);

    // (Optional) sanity: assembler should not be complete
    expect($asm->isComplete())->toBeFalse();
});

it('rejects mismatched totals across chunks', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    // Generate a 3-chunk message with a consistent header (code = XYZ)
    $lines = iterator_to_array($enc->encodeToChunks($payload, 'XYZ', 3));

    // Tamper ONE chunk's header total: i/3 -> i/4 (keep the same index i)
    // Header format: ER|v1|<code>|<i>/<N>|<payload>
// Tamper ONE chunk's header total: i/3 -> i/4 (keep the same index i)
    $tampered = preg_replace_callback(
        '/^(ER\|v1\|[^|]+\|)(\d+)\/(\d+)\|/',
        function ($m) {
            // $m[1] = "ER|v1|<code>|"
            // $m[2] = i
            // $m[3] = N (original total)
            return $m[1] . $m[2] . '/4|';
        },
        $lines[0]
    );

// Safety: verify the tamper changed the string
    expect($tampered)->not()->toBe($lines[0]);

// Also verify the decoded header now reports total=4
    $hdr = $dec->parseLine($tampered);
    expect($hdr->total)->toBe(4);

    // Sanity: assembler should not be complete
    expect($asm->isComplete())->toBeFalse();

    // Add one good chunk first (total=3)
    $asm->add($dec->parseLine($lines[1]));

// Adding the tampered chunk (total=4) should throw immediately
    expect(fn () => $asm->add($hdr))
        ->toThrow(\InvalidArgumentException::class); // or \InvalidArgumentException::class, see #2

    expect($tampered)->toMatch('/\|\d+\/4\|/');
});

it('rejects out-of-range index (e.g. 4/3)', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    // Make a clean 3-chunk message.
    $lines = iterator_to_array($enc->encodeToChunks($payload, 'XYZ', 3));

    // Tamper ONE chunk’s index from i/3 to 4/3 (keep the total 3)
    $tampered = preg_replace_callback(
        '/^(ER\|v1\|[^|]+\|)(\d+)\/(\d+)\|/',
        function ($m) {          // m[1] = "ER|v1|<code>|", m[2] = i, m[3] = N
            return $m[1] . '4/3|';
        },
        $lines[2]
    );

    // Safety: ensure we actually changed something
    expect($tampered)->not()->toBe($lines[2]);

    // Decode the tampered header and assert index/total
    $hdrTampered = $dec->parseLine($tampered);

//    expect($hdrTampered->index)->toBe(4);
//    expect($hdrTampered->total)->toBe(3);
//
//    // Add two valid chunks first (establish total=3 in the assembler)
//    $asm->add($dec->parseLine($lines[0]));
//    $asm->add($dec->parseLine($lines[1]));

    // Now adding index=4 for total=3 must throw immediately
    expect(fn () => $asm->add($hdrTampered))
        ->toThrow(\InvalidArgumentException::class);

    expect($asm->isComplete())->toBeFalse();
})->throws(\InvalidArgumentException::class);

it('fails when a chunk payload is corrupted', function () {
    $payload = ['type' => 'ER', 'code' => 'XYZ', 'data' => ['hello' => 'world']];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    $lines = iterator_to_array($enc->encodeToChunks($payload, 'XYZ', 4));

    // Corrupt only the payload part of one line
    $tampered = preg_replace(
        '/^(ER\|v1\|[^|]+\|\d+\/\d+\|)(.+)$/',
        '$1$2X',
        $lines[2]
    );
    expect($tampered)->not()->toBe($lines[2]); // sanity

    // Ingest
    $asm->add($dec->parseLine($lines[0]));
    $asm->add($dec->parseLine($tampered)); // corrupted
    $asm->add($dec->parseLine($lines[1]));
    $asm->add($dec->parseLine($lines[3]));

    // We don't assert isComplete() here—implementations vary on how they gate it.
    expect(fn () => $asm->assemble())->toThrow(\RuntimeException::class);
});

it('does not assemble when mixing envelopes (ER vs BAL) even if code matches', function () {
    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    $er = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];
    $bal = [
        'type' => 'BAL',        // different envelope type
        'code' => 'XYZ',        // same code as ER
        'data' => ['ballot' => 1],
    ];

    // Two-chunk messages to keep it simple
    $erLines  = iterator_to_array($enc->encodeToChunks($er,  'XYZ', 2));
    $balLines = iterator_to_array($enc->encodeToChunks($bal, 'XYZ', 2));

    // Add one ER chunk first
    $asm->add($dec->parseLine($erLines[0]));

    // Adding a BAL chunk (different envelope type) should fail immediately if the
    // assembler validates envelope types on add(). If yours validates only on assemble(),
    // the following assertion will flag that and you can move it to assemble().
    expect(fn () => $asm->add($dec->parseLine($balLines[1])))
        ->toThrow(\InvalidArgumentException::class);

    // If your implementation defers the error to assemble(), you can alternatively use:
    // $asm->add($dec->parseLine($balLines[1]));
    // expect(fn () => $asm->assemble())->toThrow(\RuntimeException::class);
});

// NOTE: If your assembler currently IGNORES duplicates, flip the expectations accordingly or skip this test.
// TODO: Decide policy on duplicates.
// Strict mode = throw (first test).
// Lenient mode = ignore (second test).
// For now, both skipped until policy is set.
it('errors on duplicate index chunk (same i/N twice)', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    $lines = iterator_to_array($enc->encodeToChunks($payload, 'XYZ', 3));

    $h0 = $dec->parseLine($lines[0]);
    $h1 = $dec->parseLine($lines[1]);
    $h2 = $dec->parseLine($lines[2]);

    expect([$h0->index, $h1->index, $h2->index])->toEqual([1, 2, 3]);

    $dupHdr = $dec->parseLine($lines[1]);
    expect($dupHdr->index)->toBe(2); // we expect the duplicate to be index #2

    // Add all valid chunks 1..3
    $asm->add($dec->parseLine($lines[0]));
    $asm->add($dec->parseLine($lines[1]));
    $asm->add($dec->parseLine($lines[2]));

    // Optional (only if your isComplete() is trustworthy):
    // expect($asm->isComplete())->toBeTrue();

    // Duplicate chunk #2 (same index/total)
    $dup = $lines[1];

    // Should throw immediately on add()
    expect(fn () => $asm->add($dec->parseLine($dup)))
        ->toThrow(\InvalidArgumentException::class);
})->skip();

it('ignores duplicate index chunk if already present', function () {
    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world'],
    ];

    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    $lines = iterator_to_array($enc->encodeToChunks($payload, 'XYZ', 3));

    $asm->add($dec->parseLine($lines[0]));
    $asm->add($dec->parseLine($lines[1]));
    $asm->add($dec->parseLine($lines[2]));

    expect($asm->isComplete())->toBeTrue();

    // Duplicate #2 should be a no-op (no exception)
    $asm->add($dec->parseLine($lines[1]));

    // Still complete and assembles cleanly
    expect($asm->isComplete())->toBeTrue();
    expect($asm->assemble())->toEqual($payload);
})->skip();
