<?php

declare(strict_types=1);

use TruthCodec\Transport\{Base64UrlDeflateTransport, Base64UrlGzipTransport};
use TruthCodec\Contracts\{Envelope, PayloadSerializer, TransportCodec};
use TruthCodec\Serializer\{JsonSerializer, YamlSerializer};
use TruthCodec\Envelope\{EnvelopeV1Line, EnvelopeV1Url};
use Illuminate\Support\Facades\Route;
use TruthQrUi\Actions\EncodePayload;
use TruthQr\Classify\Classify;

dataset('envelopes', [
    'v1url'  => fn () => new EnvelopeV1Url(),
    'v1line' => fn () => new EnvelopeV1Line(),
]);

dataset('transports', [
    'b64url+deflate' => fn () => new Base64UrlDeflateTransport(),
    'b64url+gzip'    => fn () => new Base64UrlGzipTransport(),
]);

dataset('serializers', [
    'json' => fn () => new JsonSerializer(),
    'yaml' => fn () => new YamlSerializer(),
]);

dataset('writers_optional', function () {
    $cases = [];

    if (class_exists(\TruthQr\Writers\BaconQrWriter::class)) {
        $cases['bacon-svg'] = fn() => new \TruthQr\Writers\BaconQrWriter('svg', 128, 8);
    }
    if (class_exists(\TruthQr\Writers\EndroidQrWriter::class)) {
        $cases['endroid-svg'] = fn() => new \TruthQr\Writers\EndroidQrWriter('svg', 128, 8);
    }

    // If no writers available in this environment, provide a null sentinel
    if (empty($cases)) {
        $cases['no-writer'] = null;
    }

    return $cases;
});

it('encodes via handle() and round-trips with TruthAssembler [matrix]',
    function (Envelope $env, TransportCodec $tx, PayloadSerializer $ser) {
        $payload = [
            'type' => 'demo',
            'code' => 'ENC-HANDLE-'.bin2hex(random_bytes(2)),
            'data' => ['hello' => 'world', 'n' => [1,2,3]],
        ];

        /** @var EncodePayload $action */
        $action = app(EncodePayload::class);

        // Encourage multi-part to exercise assembly even for tiny payloads
        $res = $action->handle(
            payload:    $payload,
            code:       $payload['code'],
            serializer: $ser,
            transport:  $tx,
            envelope:   $env,
            writer:     null,
            opts:       ['by' => 'size', 'size' => 80]
        );

        $lines = ep_lines_from_result($res);
        expect($lines)->toBeArray()->not->toBeEmpty();

        shuffle($lines);

        $asm = ep_make_assembler($ser, $tx, $env);
        $sess = (new Classify($asm))->newSession();
        $sess->addLines($lines);

        expect($sess->isComplete())->toBeTrue();
        $decoded = $sess->assemble();
        expect($decoded)->toEqual($payload);
    }
)->with('envelopes')->with('transports')->with('serializers');

it('asController encodes and round-trips using FQCNs',
    function () {
        Route::post('/api/encode', [EncodePayload::class, 'asController']);

        $payload = [
            'type' => 'demo',
            'code' => 'ENC-HTTP-'.bin2hex(random_bytes(2)),
            'data' => ['a' => 1, 'b' => 'two'],
        ];

        $resp = $this->postJson('/api/encode', [
            'payload'          => $payload,
            'code'             => $payload['code'],
            'by'               => 'size',
            'size'             => 100,
            'serializer_fqcn'  => JsonSerializer::class,
            'transport_fqcn'   => Base64UrlDeflateTransport::class,
            'envelope_fqcn'    => EnvelopeV1Url::class,
        ])->assertOk();

        $res = $resp->json();
        $lines = ep_lines_from_result($res);
        expect($lines)->toBeArray()->not->toBeEmpty();

        $ser = new JsonSerializer();
        $tx  = new Base64UrlDeflateTransport();
        $env = new EnvelopeV1Url();

        shuffle($lines);
        $sess = (new Classify(ep_make_assembler($ser, $tx, $env)))->newSession();
        $sess->addLines($lines);

        expect($sess->isComplete())->toBeTrue();
        $decoded = $sess->assemble();
        expect($decoded)->toEqual($payload);
    }
);

it('optionally returns QR image binaries when a writer is provided', function ($writerFactory) {
    if ($writerFactory === null) {
        $this->markTestSkipped('No QR writer available in this environment');
    }

    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Line();

    // ✅ Support both closures and pre-instantiated writers
    $writer = is_callable($writerFactory) ? $writerFactory() : $writerFactory;

    $payload = [
        'type' => 'demo',
        'code' => 'ENC-QR-'.bin2hex(random_bytes(2)),
        'data' => ['z' => str_repeat('Q', 100)],
    ];

    /** @var \TruthQrUi\Actions\EncodePayload $action */
    $action = app(\TruthQrUi\Actions\EncodePayload::class);
    $res = $action->handle($payload, $payload['code'], $ser, $tx, $env, $writer, ['by'=>'size','size'=>64]);

    expect($res)->toHaveKey('qr');
    $qr = array_values($res['qr']); // normalize keys
    expect($qr)->toBeArray()->not->toBeEmpty();
    expect($qr[0])->toStartWith('<?xml'); // change to "\x89PNG" if using PNG output
})
    ->with('writers_optional')
    ->group('qr');

it('can produce a single part for large size and multi-part for small size',
    function () {
        $ser = new JsonSerializer();
        $tx  = new Base64UrlDeflateTransport();
        $env = new EnvelopeV1Url();

        $payload = ['type'=>'demo','code'=>'ENC-SIZE-TEST','data'=>['t'=>str_repeat('A', 400)]];

        $action = app(EncodePayload::class);

        // Large size ⇒ single URL
        $res1 = $action->handle($payload, $payload['code'], $ser, $tx, $env, null, ['by'=>'size','size'=>5000]);
        $u1 = ep_lines_from_result($res1);
        expect(count($u1))->toBe(1);
        expect($u1[0])->toStartWith('truth://v1/');

        // Small but realistic size ⇒ at least 2 parts
        $res2 = $action->handle(
            $payload, $payload['code'], $ser, $tx, $env, null,
            ['by' => 'size', 'size' => 60]   // was 120
        );
        $u2 = ep_lines_from_result($res2);
        expect(count($u2))->toBeGreaterThan(1);
    }
);

it('controller supports list-key mapping (lines vs urls) based on envelope', function () {
    Route::post('/api/encode', [EncodePayload::class, 'asController']);

    $payload = ['type'=>'demo','code'=>'ENC-ALIAS-'.bin2hex(random_bytes(2)),'data'=>['a'=>1]];

    // EnvelopeV1Line => 'lines'
    $resp1 = $this->postJson('/api/encode', [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'envelope_fqcn'    => EnvelopeV1Line::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'serializer_fqcn'  => JsonSerializer::class,
        'by' => 'size', 'size' => 100,
    ])->assertOk();
    $res1 = $resp1->json();
    expect($res1)->toHaveKey('lines')->and($res1)->not->toHaveKey('urls');

    // EnvelopeV1Url => 'urls'
    $resp2 = $this->postJson('/api/encode', [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'envelope_fqcn'    => EnvelopeV1Url::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'serializer_fqcn'  => JsonSerializer::class,
        'by' => 'size', 'size' => 100,
    ])->assertOk();
    $res2 = $resp2->json();
    expect($res2)->toHaveKey('urls')->and($res2)->not->toHaveKey('lines');
});

it('asController rejects invalid payload (non-array non-JSON)', function () {
    Route::post('/api/encode', [EncodePayload::class, 'asController']);

    $resp = $this->postJson('/api/encode', [
        // invalid: a binary-ish string that will not json_decode to array
        'payload'          => "\xFF\xFF not-json \x00",
        'code'             => 'ENC-BAD-001',
        'serializer_fqcn'  => JsonSerializer::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'envelope_fqcn'    => EnvelopeV1Url::class,
    ]);

    $resp->assertStatus(422);
});

it('chunks by explicit count and round-trips', function () {
    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();

    $payload = ['type'=>'demo','code'=>'ENC-COUNT-001','data'=>['t'=>str_repeat('B', 1200)]];

    $res = app(\TruthQrUi\Actions\EncodePayload::class)->handle(
        payload: $payload,
        code: $payload['code'],
        serializer: $ser,
        transport: $tx,
        envelope: $env,
        writer: null,
        opts: ['by'=>'count','count'=>5]
    );

    $lines = ep_lines_from_result($res);
    expect($lines)->toHaveCount(5);

    shuffle($lines);
    $sess = (new \TruthQr\Classify\Classify(ep_make_assembler($ser,$tx,$env)))->newSession();
    $sess->addLines($lines);
    expect($sess->isComplete())->toBeTrue();
    expect($sess->assemble())->toEqual($payload);
});

it('normalizes invalid size and count options and still works', function () {
    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();
    $payload = ['type'=>'demo','code'=>'ENC-NORM-001','data'=>['t'=>str_repeat('C', 300)]];

    $act = app(\TruthQrUi\Actions\EncodePayload::class);

    // size <= 0 → clamp to >=1 and produce at least one part
    $r1 = $act->handle($payload, $payload['code'], $ser, $tx, $env, null, ['by'=>'size','size'=>0]);
    $l1 = ep_lines_from_result($r1);
    expect($l1)->not->toBeEmpty();

    // count <= 0 → clamp to >=1; exactly 1 part
    $r2 = $act->handle($payload, $payload['code'], $ser, $tx, $env, null, ['by'=>'count','count'=>0]);
    $l2 = ep_lines_from_result($r2);
    expect($l2)->toHaveCount(1);

    // Round-trip one of them
    $cls = new \TruthQr\Classify\Classify(ep_make_assembler($ser,$tx,$env));
    $s   = $cls->newSession(); $s->addLines($l1);
    expect($s->isComplete())->toBeTrue();
    expect($s->assemble())->toEqual($payload);
});

it('supports YAML serializer and auto-detect', function () {
    $yamlSer = new \TruthCodec\Serializer\YamlSerializer();
    $autoSer = new \TruthCodec\Serializer\AutoDetectSerializer(
        [new \TruthCodec\Serializer\JsonSerializer(), new \TruthCodec\Serializer\YamlSerializer()],
        primary: new \TruthCodec\Serializer\JsonSerializer()
    );
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();

    $payload = ['type'=>'demo','code'=>'ENC-YAML-001','data'=>['a'=>1,'b'=>2]];

    // Encode with YAML
    $res = app(\TruthQrUi\Actions\EncodePayload::class)->handle(
        payload: $payload,
        code: $payload['code'],
        serializer: $yamlSer,
        transport:  $tx,
        envelope:   $env
    );
    $lines = ep_lines_from_result($res);

    // Decode with auto-detect
    shuffle($lines);
    $asm = ep_make_assembler($autoSer,$tx,$env);
    $sess = (new \TruthQr\Classify\Classify($asm))->newSession();
    $sess->addLines($lines);

    expect($sess->isComplete())->toBeTrue();
    expect($sess->assemble())->toEqual($payload);
});

it('works with gzip transport when available', function () {
    if (!class_exists(\TruthCodec\Transport\Base64UrlGzipTransport::class)) {
        $this->markTestSkipped('gzip transport not installed');
    }
    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlGzipTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();
    $payload = ['type'=>'demo','code'=>'ENC-GZ-001','data'=>['t'=>str_repeat('Z', 1500)]];

    $res = app(\TruthQrUi\Actions\EncodePayload::class)->handle($payload,$payload['code'],$ser,$tx,$env);
    $lines = ep_lines_from_result($res);

    shuffle($lines);
    $sess = (new \TruthQr\Classify\Classify(ep_make_assembler($ser,$tx,$env)))->newSession();
    $sess->addLines($lines);
    expect($sess->isComplete())->toBeTrue();
    expect($sess->assemble())->toEqual($payload);
});

it('handle() supports EnvelopeV1Line and emits ER|v1|… lines', function () {
    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Line();

    $payload = ['type'=>'demo','code'=>'ENC-LINE-001','data'=>['m'=>42]];

    $res = app(\TruthQrUi\Actions\EncodePayload::class)->handle($payload,$payload['code'],$ser,$tx,$env);
    $lines = ep_lines_from_result($res);

    expect($lines)->not->toBeEmpty();
    expect($lines[0])->toStartWith('ER|v1|');
});

it('asController accepts alias params (json / base64url+deflate / v1url) and round-trips', function () {
    \Illuminate\Support\Facades\Route::post('/api/encode', [\TruthQrUi\Actions\EncodePayload::class, 'asController']);

    $payload = ['type'=>'demo','code'=>'ENC-ALIAS-002','data'=>['k'=>'v']];

    $resp = $this->postJson('/api/encode', [
        'payload'   => $payload,
        'code'      => $payload['code'],
        'serializer'=> 'json',
        'transport' => 'base64url+deflate',
        'envelope'  => 'v1url',
        'by' => 'size','size'=>100
    ])->assertOk();

    $body  = $resp->json();
    $lines = ep_lines_from_result($body);
    expect($lines)->not->toBeEmpty();

    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();

    shuffle($lines);
    $sess = (new \TruthQr\Classify\Classify(ep_make_assembler($ser,$tx,$env)))->newSession();
    $sess->addLines($lines);
    expect($sess->isComplete())->toBeTrue();
    expect($sess->assemble())->toEqual($payload);
});

it('asController returns 422 on invalid payload', function () {
    \Illuminate\Support\Facades\Route::post('/api/encode', [\TruthQrUi\Actions\EncodePayload::class, 'asController']);

    $resp = $this->postJson('/api/encode', [
        'payload'         => "\xFF\xFF not-json \x00",
        'serializer_fqcn' => \TruthCodec\Serializer\JsonSerializer::class,
        'transport_fqcn'  => \TruthCodec\Transport\Base64UrlDeflateTransport::class,
        'envelope_fqcn'   => \TruthCodec\Envelope\EnvelopeV1Url::class,
    ]);

    $resp->assertStatus(422);
});

it('asController defaults code when not provided', function () {
    \Illuminate\Support\Facades\Route::post('/api/encode', [\TruthQrUi\Actions\EncodePayload::class, 'asController']);

    $payload = ['type'=>'demo','data'=>['x'=>1]]; // no code in payload or request
    $resp = $this->postJson('/api/encode', ['payload'=>$payload])->assertOk();

    $body = $resp->json();
    expect($body['code'] ?? '')->toBeString()->not->toBe('');
});

it('handles large payloads by chunking and still assembles', function () {
    $ser = new \TruthCodec\Serializer\JsonSerializer();
    $tx  = new \TruthCodec\Transport\Base64UrlDeflateTransport();
    $env = new \TruthCodec\Envelope\EnvelopeV1Url();

    // Build a low-compressibility payload (random hex rows)
    $rows = [];
    for ($i = 0; $i < 600; $i++) {
        $rows[] = bin2hex(random_bytes(64)); // ~128 chars each, hard to deflate
    }
    $payload = [
        'type'  => 'demo',
        'code'  => 'ENC-LARGE-001',
        'blob'  => $rows, // large & low-compressibility
    ];

    // Small size threshold => multiple parts
    $res   = app(\TruthQrUi\Actions\EncodePayload::class)
        ->handle($payload, $payload['code'], $ser, $tx, $env, null, ['by' => 'size', 'size' => 300]);
    $lines = ep_lines_from_result($res);

    expect(count($lines))->toBeGreaterThan(5);

    shuffle($lines);
    $sess = (new \TruthQr\Classify\Classify(ep_make_assembler($ser, $tx, $env)))->newSession();
    $sess->addLines($lines);
    expect($sess->isComplete())->toBeTrue();
    expect($sess->assemble())->toEqual($payload);
});
