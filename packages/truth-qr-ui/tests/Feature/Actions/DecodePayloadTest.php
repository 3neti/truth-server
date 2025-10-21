<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lorisleiva\Actions\ActionRequest;

use TruthQrUi\Actions\EncodePayload;
use TruthQrUi\Actions\DecodePayload;

use TruthQr\Assembly\TruthAssembler;
use TruthQr\Classify\Classify;
use TruthQr\Stores\ArrayTruthStore;

use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;

use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Envelope\EnvelopeV1Line;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;

/**
 * Normalize whatever EncodePayload returns into a flat array of envelope texts.
 * Supports:
 *  - ['lines' => [...]] (line envelope)
 *  - ['urls'  => [...]] (url envelope)
 *  - legacy ['chunks' => [['text' => '...'], ...]]
 */
function dp_lines_from_encode(array $res): array
{
    if (isset($res['lines']) && is_array($res['lines'])) {
        return array_map(static fn($v) => (string) $v, array_values($res['lines']));
    }
    if (isset($res['urls']) && is_array($res['urls'])) {
        return array_map(static fn($v) => (string) $v, array_values($res['urls']));
    }
    if (isset($res['chunks']) && is_array($res['chunks'])) {
        return array_values(array_map(
            static fn($c) => (string)($c['text'] ?? ''),
            $res['chunks']
        ));
    }
    throw new RuntimeException('Encode result missing lines/urls/chunks.');
}

/**
 * Build a TruthAssembler using explicit collaborators — mirrors production decode path.
 */
function dp_make_assembler(PayloadSerializer $ser, TransportCodec $tx, Envelope $env): TruthAssembler
{
    return new TruthAssembler(
        store: new ArrayTruthStore(),
        envelope: $env,
        transport: $tx,
        serializer: $ser
    );
}

/**
 * Helper: force a multi-part result by adjusting size downward until we reach minParts.
 * Returns the resulting array of envelope strings.
 */
function dp_force_multipart(array $payload, string $code, PayloadSerializer $ser, TransportCodec $tx, Envelope $env, int $minParts = 3): array
{
    $action = app(EncodePayload::class);
    $size   = 200; // start conservative

    for ($iter = 0; $iter < 12; $iter++) {
        $res  = $action->handle($payload, $code, $ser, $tx, $env, null, ['by'=>'size','size'=>$size]);
        $list = dp_lines_from_encode($res);
        if (count($list) >= $minParts) {
            return $list;
        }
        // reduce size to force more chunks
        $size = max(20, (int)floor($size * 0.6));
    }

    throw new RuntimeException("Could not force multi-part >= {$minParts} after many attempts; last count=".count($list));
}

/* -------------------------------------------------------------------------- */
/* 1) Basic happy paths                                                       */
/* -------------------------------------------------------------------------- */

it('decodes via handle() (URL envelope) and round-trips', function () {
    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Url();

    $payload = [
        'type' => 'demo',
        'code' => 'DEC-HANDLE-001',
        'data' => ['a' => 1, 'b' => 'two'],
    ];

    // Encode (single or multi-part; both are fine)
    $encRes = app(EncodePayload::class)->handle(
        payload: $payload,
        code: $payload['code'],
        serializer: $ser,
        transport: $tx,
        envelope: $env,
        writer: null,
        opts: ['by'=>'size','size'=>200]
    );

    $lines = dp_lines_from_encode($encRes);
    shuffle($lines);

    $out = app(DecodePayload::class)->handle($lines, $env, $tx, $ser);
    expect($out['complete'])->toBeTrue();
    expect($out['payload'])->toEqual($payload);
});

it('decodes via HTTP controller (alias args) and round-trips', function () {
    Route::post('/api/decode', [DecodePayload::class, 'asController']);

    $payload = [
        'type' => 'demo',
        'code' => 'DEC-HTTP-001',
        'data' => ['x'=>1, 'y'=>2],
    ];

    // Encode using the explicit collaborators (URL envelope for controller friendly lines)
    $ser  = new JsonSerializer();
    $tx   = new Base64UrlDeflateTransport();
    $env  = new EnvelopeV1Url();

    $encRes = app(EncodePayload::class)->handle($payload, $payload['code'], $ser, $tx, $env, null, ['by'=>'size','size'=>140]);
    $lines  = dp_lines_from_encode($encRes);
    shuffle($lines);

    // Decode via controller using ALIASES, not FQCN
    $resp = $this->postJson('/api/decode', [
        'lines'      => $lines,
        'envelope'   => 'v1url',               // alias
        'transport'  => 'base64url+deflate',   // alias
        'serializer' => 'json',                // alias
    ])->assertOk();

    $body = $resp->json();
    expect($body['complete'])->toBeTrue();
    expect($body['payload'])->toEqual($payload);
});

/* -------------------------------------------------------------------------- */
/* 2) Missing index path (incomplete set)                                     */
/* -------------------------------------------------------------------------- */

it('reports missing indices when a chunk is absent', function () {
    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Url();

    $payload = [
        'type' => 'demo',
        'code' => 'DEC-MISS-001',
        'data' => ['blob' => str_repeat('x', 1500)]
    ];

    // Ensure >= 3 parts so we can remove a predictable middle index
    $urls = dp_force_multipart($payload, $payload['code'], $ser, $tx, $env, minParts: 3);
    // parse the index from path segment ".../<code>/<i>/<N>?..."
    $parseIdx = static function (string $line): int {
        $parts = explode('/', $line);
        return (int)($parts[5] ?? 0);
    };

    // Remove the piece with index 2
    $missingIndex = 2;
    $filtered = array_values(array_filter($urls, fn($u) => $parseIdx($u) !== $missingIndex));

    $out = app(DecodePayload::class)->handle($filtered, $env, $tx, $ser);

    expect($out['complete'])->toBeFalse();
    expect($out['missing'])->toContain($missingIndex);
    expect($out['received'])->toBe($out['total'] - 1);
});

/* -------------------------------------------------------------------------- */
/* 3) Duplicates: exact dup allowed, conflicting dup should error (optional)  */
/* -------------------------------------------------------------------------- */

it('tolerates exact duplicate lines and does not overcount', function () {
    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Url();

    $payload = ['type'=>'demo','code'=>'DEC-DUP-001','data'=>['z'=>str_repeat('q', 400)]];

    $urls = dp_force_multipart($payload, $payload['code'], $ser, $tx, $env, minParts: 3);
    // Inject a duplicate of the first line
    array_splice($urls, 1, 0, [$urls[0]]);

    $out = app(DecodePayload::class)->handle($urls, $env, $tx, $ser);

    // still complete and same payload
    expect($out['complete'])->toBeTrue();
    expect($out['payload'])->toEqual($payload);
});

/**
 * OPTIONAL (guard it): Conflicting duplicate payload should raise an error.
 * Depending on your Classify/session implementation this may be thrown as
 * InvalidArgumentException (handle) or 422 (controller). We try controller path here.
 */
it('fails on conflicting duplicate lines (controller)', function () {
    Route::post('/api/decode', [DecodePayload::class, 'asController']);

    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Url();

    $payload = ['type'=>'demo','code'=>'DEC-CDUP-001','data'=>['w'=>str_repeat('Z', 500)]];

    $urls = dp_force_multipart($payload, $payload['code'], $ser, $tx, $env, minParts: 3);
    // Take one URL and corrupt its payload query param "c=" minimally to break hash
    // (Simple toggle of one char near the end)
    $bad = $urls[0];
    $bad = preg_replace_callback('/(\?c=)([A-Za-z0-9\-_]+)$/', function ($m) {
        $c = $m[2];
        // flip last char among a small safe set
        $last = substr($c, -1);
        $flip = $last === 'A' ? 'B' : 'A';
        return $m[1].substr($c, 0, -1).$flip;
    }, $bad) ?? $bad;

    $mixed = $urls;
    $mixed[0] = $bad;

    $resp = $this->postJson('/api/decode', [
        'lines'      => $mixed,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ]);

    // Either 422 or 200/complete=false depending on your assembler’s guardrails.
    // Make the expectation tolerant:
    $resp->assertStatus(in_array($resp->getStatusCode(), [200, 422], true) ? $resp->getStatusCode() : 422);
    $body = $resp->json();

    if (($body['complete'] ?? null) === false) {
        // acceptable: session refused to assemble due to mismatch
        expect($body['missing'] ?? [])->not->toBeEmpty();
    } else {
        // strict behavior: explicit error
        expect($resp->getStatusCode())->toBe(422);
    }
})->group('conflict');

/* -------------------------------------------------------------------------- */
/* 4) Large payload still assembles                                           */
/* -------------------------------------------------------------------------- */

it('handles large payloads by chunking and still assembles', function () {
    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Url();

    $payload = ['type'=>'demo','code'=>'DEC-LARGE-001','blob'=>str_repeat('DATA', 5000)];

    $urls = dp_force_multipart($payload, $payload['code'], $ser, $tx, $env, minParts: 4);
    shuffle($urls);

    $out = app(DecodePayload::class)->handle($urls, $env, $tx, $ser);
    expect($out['complete'])->toBeTrue();
    expect($out['payload'])->toEqual($payload);
});

/* -------------------------------------------------------------------------- */
/* 5) Controller accepts "chunks" wrapper and alias combos                     */
/* -------------------------------------------------------------------------- */

it('controller accepts chunks: [{text: ...}] and alias combos', function () {
    Route::post('/api/decode', [DecodePayload::class, 'asController']);

    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Line(); // Different envelope; still fine

    $payload = ['type'=>'demo','code'=>'DEC-CHUNKS-001','data'=>['foo'=>'bar', 'n'=>[1,2,3,4,5]]];

    // encode with line envelope to produce 'lines', convert to chunks shape for controller
    $encRes = app(EncodePayload::class)->handle($payload, $payload['code'], $ser, $tx, $env, null, ['by'=>'size','size'=>120]);
    $lines  = dp_lines_from_encode($encRes);
    $chunks = array_map(fn($t) => ['text' => $t], $lines);
    shuffle($chunks);

    $resp = $this->postJson('/api/decode', [
        'chunks'     => $chunks,
        'envelope'   => 'v1line',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk();

    $out = $resp->json();
    expect($out['complete'])->toBeTrue();
    expect($out['payload'])->toEqual($payload);
});

/* -------------------------------------------------------------------------- */
/* 6) ERData auto-transformation                                              */
/* -------------------------------------------------------------------------- */

it('correctly detects ERData format for transformation', function () {
    // Test ERData detection logic directly
    $decodeAction = app(DecodePayload::class);
    $reflection = new ReflectionClass($decodeAction);
    $isERDataMethod = $reflection->getMethod('isERData');
    $isERDataMethod->setAccessible(true);
    
    // Mock ERData payload (minified format)
    $erDataPayload = [
        'id' => 'test-er-001',
        'code' => 'ER-2024-TEST',
        'tallies' => [
            'AJ_006' => 150,  // candidate_code => count (key-value format)
            'SJ_002' => 120,
            'TH_001' => 200,
        ],
        'signatures' => [
            [
                'id' => 'uuid-juan',
                'signature' => 'signature123',
                'signed_at' => '2024-10-21T06:00:00+00:00'
            ]
        ],
        'created_at' => '2024-10-21T06:00:00+00:00',
        'updated_at' => '2024-10-21T06:00:00+00:00',
    ];
    
    // Mock full ElectionReturnData payload
    $fullPayload = [
        'id' => 'test-full-001',
        'code' => 'ER-2024-FULL',
        'precinct' => [
            'code' => 'TEST-001',
            'location_name' => 'Test School',
        ],
        'tallies' => [
            [
                'position_code' => 'PRESIDENT',
                'candidate_code' => 'AJ_006',
                'candidate_name' => 'Angelina Jolie',
                'count' => 150
            ]
        ],
        'signatures' => [],
        'ballots' => [],
        'created_at' => '2024-10-21T06:00:00+00:00',
        'updated_at' => '2024-10-21T06:00:00+00:00',
    ];
    
    // Test detection
    $isERDataDetected = $isERDataMethod->invoke($decodeAction, $erDataPayload);
    $isFullDetected = $isERDataMethod->invoke($decodeAction, $fullPayload);
    
    expect($isERDataDetected)->toBeTrue('ERData payload should be detected as ERData')
        ->and($isFullDetected)->toBeFalse('Full ElectionReturnData payload should NOT be detected as ERData');
});

it('does not transform regular ElectionReturnData payloads', function () {
    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Line();
    
    // Mock full ElectionReturnData payload
    $fullPayload = [
        'id' => 'test-full-001',
        'code' => 'ER-2024-FULL',
        'precinct' => [
            'code' => 'TEST-001',
            'location_name' => 'Test School',
        ],
        'tallies' => [
            [
                'position_code' => 'PRESIDENT',
                'candidate_code' => 'AJ_006',
                'candidate_name' => 'Angelina Jolie',
                'count' => 150
            ]
        ],
        'signatures' => [],
        'ballots' => [],
        'created_at' => '2024-10-21T06:00:00+00:00',
        'updated_at' => '2024-10-21T06:00:00+00:00',
    ];
    
    // Encode the full payload
    $encRes = app(EncodePayload::class)->handle(
        $fullPayload, 
        $fullPayload['code'], 
        $ser, 
        $tx, 
        $env, 
        null, 
        ['by'=>'size','size'=>200]
    );
    $lines = dp_lines_from_encode($encRes);
    
    // Decode should NOT transform (already in correct format)
    $out = app(DecodePayload::class)->handle($lines, $env, $tx, $ser);
    
    expect($out['complete'])->toBeTrue()
        ->and($out['transformed'] ?? false)->toBeFalse()
        ->and($out['payload'])->toEqual($fullPayload);
});
