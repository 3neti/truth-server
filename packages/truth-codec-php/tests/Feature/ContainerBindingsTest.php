<?php

use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Decode\ChunkAssembler;
use TruthCodec\Decode\ChunkDecoder;
use TruthCodec\Contracts\Envelope;

/**
 * A tiny fake transport that makes its work obvious:
 *  - encode() wraps text in << ... >>
 *  - decode() unwraps the text
 */
class FakeBracketTransport implements TransportCodec
{
    public function encode(string $plain): string
    {
        return '<<' . $plain . '>>';
    }

    public function decode(string $packed): string
    {
        // very forgiving "unwrap"
        if (str_starts_with($packed, '<<') && str_ends_with($packed, '>>')) {
            return substr($packed, 2, -2);
        }
        // fall back to original (lets the test fail in assemble if wrong)
        return $packed;
    }

    public function name(): string
    {
        return 'fake-bracket';
    }
}

it('resolves ChunkEncoder from the container and uses injected transport', function () {
    // Force deterministic JSON for easy assertions
    $this->app->bind(PayloadSerializer::class, fn () => new JsonSerializer());

    // Inject our fake transport so we can see its markers in the chunks
    $this->app->bind(TransportCodec::class, fn () => new FakeBracketTransport());

    // Rebind encoder as in your ServiceProvider snippet
    $this->app->bind(ChunkEncoder::class, function ($app) {
        return new ChunkEncoder(
            $app->make(PayloadSerializer::class),
            $app->make(TransportCodec::class),
            app(Envelope::class)
        );
    });

    /** @var ChunkEncoder $encoder */
    $encoder = app(ChunkEncoder::class);

    $payload = ['type' => 'ER', 'code' => 'XYZ', 'data' => ['a' => 1, 'b' => 2]];

    // Small chunk size to force multiple chunks and keep assertions simple
    $chunks = $encoder->encodeToChunks($payload, 'XYZ', 20);

    expect($chunks)->not->toBeEmpty();
    foreach ($chunks as $line) {
        // Header format: ER|v1|<CODE>|i/N|<payloadPart>
        expect($line)->toMatch('/^ER\|v1\|XYZ\|\d+\/\d+\|/');

        // Payload part should start with our fake transport marker "<<" at least on the first chunk
        // (later chunks will continue the encoded stream, but the very first one definitely starts with "<<")
    }
    expect($chunks[0])->toMatch('/\|\<\</'); // has "<<" right after the last '|'
});

it('roundtrips via container (encoder -> decoder/assembler) with injected transport', function () {
    // Deterministic JSON again
    $this->app->bind(PayloadSerializer::class, fn () => new JsonSerializer());
    // Fake transport to prove the assembler uses TransportCodec::decode()
    $this->app->bind(TransportCodec::class, fn () => new FakeBracketTransport());

    // Bind encoder
    $this->app->bind(ChunkEncoder::class, function ($app) {
        return new ChunkEncoder(
            $app->make(PayloadSerializer::class),
            $app->make(TransportCodec::class),
            app(Envelope::class)
        );
    });

    // Bind assembler (constructor signature includes both deps per your provider snippet)
    $this->app->bind(ChunkAssembler::class, function ($app) {
        return new ChunkAssembler(
            $app->make(PayloadSerializer::class),
            $app->make(TransportCodec::class)
        );
    });

    /** @var ChunkEncoder $encoder */
    $encoder = app(ChunkEncoder::class);
    /** @var ChunkAssembler $assembler */
    $assembler = app(ChunkAssembler::class);

    $decoder = new ChunkDecoder(app(Envelope::class));

    $payload = [
        'type' => 'ER',
        'code' => 'XYZ',
        'data' => ['hello' => 'world', 'nums' => [1,2,3]],
    ];

    $chunks = $encoder->encodeToChunks($payload, 'XYZ', 24);
    expect($chunks)->toHaveCount(3)->toBeGreaterThan(0);

    foreach ($chunks as $line) {
        $assembler->add($decoder->parseLine($line));
    }

    // If your assembler’s isComplete checks exact indices, this should be true now
    expect($assembler->isComplete())->toBeTrue();

    // The assembled array should equal the original payload
    expect($assembler->assemble())->toEqual($payload);
});
