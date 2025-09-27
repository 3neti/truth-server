<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

use TruthQrUi\Http\Controllers\DecodeController;
use TruthQrUi\Http\Controllers\EncodeController;

use TruthQrUi\Actions\EncodePayload;

use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Envelope\EnvelopeV1Line;

/**
 * Small helpers (scoped to this file)
 */

// Normalize whatever Encode endpoint returns into an array of lines/urls (strings).
function dc_extract_lines(array $res): array
{
    if (isset($res['urls']) && is_array($res['urls'])) {
        return array_values(array_map(static fn($v) => (string)$v, $res['urls']));
    }
    if (isset($res['lines']) && is_array($res['lines'])) {
        return array_values(array_map(static fn($v) => (string)$v, $res['lines']));
    }
    if (isset($res['chunks']) && is_array($res['chunks'])) {
        return array_values(array_map(static fn($c) => (string)($c['text'] ?? ''), $res['chunks']));
    }
    throw new RuntimeException('Encode result did not include urls/lines/chunks.');
}

// Force multipart output by repeatedly lowering size until > 1 piece (with a floor).
function dc_force_multipart(array $payload, string $code): array
{
    $ser = new JsonSerializer();
    $tx  = new Base64UrlDeflateTransport();
    $env = new EnvelopeV1Url();

    /** @var EncodePayload $enc */
    $enc = app(EncodePayload::class);

    $size = 120; // start small but reasonable
    for ($i = 0; $i < 8; $i++, $size = max(40, (int)($size * 0.75))) {
        $res  = $enc->handle($payload, $code, $ser, $tx, $env, null, ['by' => 'size', 'size' => $size]);
        $urls = dc_extract_lines($res);
        if (count($urls) > 1) {
            return $urls;
        }
    }
    // If we really can’t split, just return the single URL set.
    return dc_extract_lines(
        $enc->handle($payload, $code, $ser, $tx, $env, null, ['by' => 'size', 'size' => 60])
    );
}

beforeEach(function () {
    // Wire endpoints the same way EncodeControllerTest does.
    Route::post('/api/encode', EncodeController::class);
    Route::post('/api/decode', DecodeController::class);
});

it('decodes via HTTP using friendly aliases (v1url + base64url+deflate + json)', function () {
    $payload = [
        'type' => 'demo',
        'code' => 'DEC-HTTP-ALIAS-001',
        'data' => ['x' => 1, 'y' => 2],
    ];

    // 1) Encode (alias path on EncodeController)
    $enc = test()->postJson('/api/encode', [
        'payload'   => $payload,
        'code'      => $payload['code'],
        'envelope'  => 'v1url',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',
        'by'        => 'size',
        'size'      => 120,
    ])->assertOk()->json();

    $lines = dc_extract_lines($enc);
    expect($lines)->toBeArray()->not->toBeEmpty();

    shuffle($lines);

    // 2) Decode (alias path on DecodeController)
    $res = test()->postJson('/api/decode', [
        'lines'      => $lines,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk()->json();

    expect($res['complete'] ?? null)->toBeTrue();
    expect($res['payload'] ?? null)->toEqual($payload);
});

it('decodes via HTTP using FQCNs (EnvelopeV1Line + Base64UrlDeflateTransport + JsonSerializer)', function () {
    $payload = [
        'type' => 'demo',
        'code' => 'DEC-HTTP-FQCN-001',
        'data' => ['a' => 'b'],
    ];

    // Encode to *line* style so the decode can ingest ER|v1|... lines too
    $enc = test()->postJson('/api/encode', [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'envelope_fqcn'    => EnvelopeV1Line::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'serializer_fqcn'  => JsonSerializer::class,
        'by'               => 'size',
        'size'             => 100,
    ])->assertOk()->json();

    $lines = dc_extract_lines($enc);
    expect($lines)->toBeArray()->not->toBeEmpty();

    $envFqcn = str_starts_with($lines[0], 'truth://v1/')
        ? \TruthCodec\Envelope\EnvelopeV1Url::class
        : \TruthCodec\Envelope\EnvelopeV1Line::class;

    shuffle($lines);

    $res = test()->postJson('/api/decode', [
        'lines'           => $lines,
        'envelope_fqcn'   => $envFqcn,
        'transport_fqcn'  => Base64UrlDeflateTransport::class,
        'serializer_fqcn' => JsonSerializer::class,
    ])->assertOk()->json();
//    dd($res, $lines);
    expect($res['complete'] ?? null)->toBeTrue();
    expect($res['payload'] ?? null)->toEqual($payload);
});

it('reports missing indices when a piece is absent (alias path)', function () {
    $payload = ['type' => 'demo', 'code' => 'DEC-MISS-001', 'blob' => str_repeat('X', 1200)];

    // Force multipart set
    $urls = dc_force_multipart($payload, $payload['code']);
    expect(count($urls))->toBeGreaterThan(1);

    // Drop the second piece (by index in the TRUTH URL)
    $filtered = array_values(array_filter($urls, function (string $u) {
        // "truth://v1/ER/<CODE>/<i>/<N>?c=..."
        $parts = explode('/', $u);
        $i = (int) ($parts[5] ?? 0);
        return $i !== 2;
    }));

    $res = test()->postJson('/api/decode', [
        'lines'      => $filtered,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk()->json();

    expect($res['complete'] ?? null)->toBeFalse();
    expect($res['missing'] ?? [])->toContain(2);
    expect(($res['received'] ?? 0))->toBe(($res['total'] ?? 0) - 1);
});

it('rejects invalid shape (neither lines[] nor chunks[]) with 422', function () {
    $res = test()->postJson('/api/decode', [
        'envelope'  => 'v1url',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',
        // intentionally omit 'lines' and 'chunks'
    ])->assertStatus(422)->json();

    expect($res)->toHaveKey('error');
});

it('is tolerant to conflicting duplicate input: either 422 error or 200 with complete=false', function () {
    $payload = ['type'=>'demo','code'=>'DEC-CDUP-001','data'=>['w'=>str_repeat('Z', 600)]];
    $urls = dc_force_multipart($payload, $payload['code']);
    if (count($urls) < 2) {
        test()->markTestSkipped('Could not force multi-part; environment produced single part.');
    }

    // Corrupt the *payload query* minimally to induce mismatch (flip last char of ?c=…)
    $bad = preg_replace_callback('/(\?c=)([A-Za-z0-9\-_]+)$/', function ($m) {
        $c = $m[2];
        $flip = substr($c, -1) === 'A' ? 'B' : 'A';
        return $m[1] . substr($c, 0, -1) . $flip;
    }, $urls[0]) ?? $urls[0];

    $mixed = $urls;
    $mixed[0] = $bad;

    $resp = test()->postJson('/api/decode', [
        'lines'      => $mixed,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ]);

    // We accept either strict 422 or soft 200/complete=false (depending on TruthAssembler behavior)
    $status = $resp->getStatusCode();
    expect([200, 422])->toContain($status);

    $body = $resp->json();

    if ($status === 200) {
        expect($body['complete'] ?? null)->toBeFalse();
        expect(($body['missing'] ?? []))->not->toBeEmpty();
    } else {
        // strict error is fine
        expect($body)->toHaveKey('error');
    }
});

/** Accept chunks shape ([{text: "..."}]) */
it('accepts chunks payload (array of {text})', function () {
    // encode as URL envelope so we get truth://...
    $enc = test()->postJson('/api/encode', [
        'payload'         => ['type'=>'demo','code'=>'DEC-CHUNKS-001','x'=>1],
        'code'            => 'DEC-CHUNKS-001',
        'envelope'        => 'v1url',
        'transport'       => 'base64url+deflate',
        'serializer'      => 'json',
        'by'              => 'size',
        'size'            => 120,
    ])->assertOk()->json();

    $urls = dc_extract_lines($enc);     // helper you already use
    $chunks = array_map(fn($u) => ['text' => $u], $urls);
    shuffle($chunks);

    $res = test()->postJson('/api/decode', [
        'chunks'     => $chunks,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk()->json();

    expect($res['complete'] ?? null)->toBeTrue();
});

/** Alias/FQCN precedence (alias wins over FQCN) */
it('prefers aliases over FQCNs when both are present', function () {
    $payload = ['type'=>'demo','code'=>'DEC-ALIAS-WINS','d'=>1];

    $enc = test()->postJson('/api/encode', [
        'payload'         => $payload,
        'code'            => $payload['code'],
        'envelope'        => 'v1url', // alias says URL
        'envelope_fqcn'   => \TruthCodec\Envelope\EnvelopeV1Line::class, // conflicting FQCN
        'transport'       => 'base64url+deflate',
        'serializer'      => 'json',
        'by'              => 'size', 'size' => 100,
    ])->assertOk()->json();

    $lines = dc_extract_lines($enc); // should be URLs (alias won)
    expect($lines[0])->toStartWith('truth://v1/');

    $res = test()->postJson('/api/decode', [
        'lines'           => $lines,
        'envelope'        => 'v1url', // again: alias should win
        'envelope_fqcn'   => \TruthCodec\Envelope\EnvelopeV1Line::class,
        'transport'       => 'base64url+deflate',
        'serializer'      => 'json',
    ])->assertOk()->json();

    expect($res['complete'] ?? null)->toBeTrue();
});

/** Serializer = auto round-trip */
it('round-trips with serializer=auto', function () {
    $payload = ['type'=>'demo','code'=>'DEC-AUTO-001','data'=>['a'=>1,'b'=>2]];

    $enc = test()->postJson('/api/encode', [
        'payload'   => $payload,
        'code'      => $payload['code'],
        'envelope'  => 'v1url',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',          // encode as JSON
        'by'        => 'size', 'size' => 100,
    ])->assertOk()->json();

    $lines = dc_extract_lines($enc);
    shuffle($lines);

    $res = test()->postJson('/api/decode', [
        'lines'      => $lines,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'auto',         // decode with autodetect
    ])->assertOk()->json();

    expect($res['complete'] ?? null)->toBeTrue();
    expect($res['payload']  ?? null)->toEqual($payload);
});

/** Invalid inputs → 422 with message */
it('returns 422 when neither lines nor chunks are provided', function () {
    test()->postJson('/api/decode', [
        'envelope'  => 'v1url',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',
    ])->assertStatus(422)->assertJsonStructure(['error']);
});

/** Mismatched collaborators */
it('does not assemble if envelope type mismatches', function () {
    $payload = ['type'=>'demo','code'=>'DEC-MISMATCH-ENV','k'=>1];

    $enc = test()->postJson('/api/encode', [
        'payload'   => $payload,
        'code'      => $payload['code'],
        'envelope'  => 'v1url',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',
    ])->assertOk()->json();

    $lines = dc_extract_lines($enc);
    shuffle($lines);

    $res = test()->postJson('/api/decode', [
        'lines'      => $lines,
        'envelope'   => 'v1line', // wrong on purpose
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk()->json();

    expect($res['complete'] ?? null)->toBeFalse();
});

/** Large multiparts (smoke) */
it('handles multi-part and returns meaningful status fields', function () {
    $payload = ['type'=>'demo','code'=>'DEC-STATUS','blob'=>str_repeat('X', 3000)];

    $enc = test()->postJson('/api/encode', [
        'payload'   => $payload,
        'code'      => $payload['code'],
        'envelope'  => 'v1url',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',
        'by'        => 'size', 'size' => 40,
    ])->assertOk()->json();

    $lines = ensureMultipart($payload, 3);

    expect(count($lines))->toBeGreaterThan(2);

    shuffle($lines);

    $res = test()->postJson('/api/decode', [
        'lines'      => $lines,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk()->json();

    expect($res)->toHaveKeys(['code','total','received','missing','complete']);
    expect($res['complete'])->toBeTrue();
});
