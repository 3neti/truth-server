<?php

use Illuminate\Support\Facades\Route;
use TruthQrUi\Http\Controllers\EncodeController;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;
use TruthCodec\Envelope\EnvelopeV1Url;

it('EncodeController forwards to EncodePayload::asController', function () {
    Route::post('/api/encode', EncodeController::class);

    $payload = ['type'=>'demo','code'=>'ENC-CTRL-001','data'=>['x'=>1]];

    $resp = $this->postJson('/api/encode', [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'by'               => 'size',
        'size'             => 100,
        'serializer_fqcn'  => JsonSerializer::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'envelope_fqcn'    => EnvelopeV1Url::class,
    ])->assertOk();

    $body = $resp->json();
    expect($body)->toHaveKeys(['code','by','urls'])
        ->and($body['code'])->toBe('ENC-CTRL-001');
});


// ... existing imports at the top of the file ...
use TruthCodec\Envelope\EnvelopeV1Line;

/**
 * Helpers
 */
function ec_payload_stub(array $overrides = []): array
{
    return array_merge([
        'type' => 'demo',
        'code' => 'ENC-CTRL-QR-' . bin2hex(random_bytes(2)),
        'data' => ['hello' => 'world'],
    ], $overrides);
}

function ec_has_bacon(): bool
{
    return class_exists(\TruthQr\Writers\BaconQrWriter::class) && class_exists(\BaconQrCode\Writer::class);
}

function ec_has_endroid(): bool
{
    return class_exists(\TruthQr\Writers\EndroidQrWriter::class) && class_exists(\Endroid\QrCode\Builder\Builder::class);
}

/**
 * 1) Omitting include_qr or setting it false → no 'qr' key
 */
it('does NOT include qr by default (include_qr omitted)', function () {
    $payload = ec_payload_stub();

    $res = test()->postJson('/api/encode', [
        'payload' => $payload,
        'code' => $payload['code'],
        'envelope' => 'v1line',
        'transport' => 'base64url+deflate',
        'serializer' => 'json',
        'by' => 'size',
        'size' => 120,
        // no include_qr
    ])->assertOk()->json();

    expect($res)->not->toHaveKey('qr');
});

it('does NOT include qr when include_qr=false', function () {
    $payload = ec_payload_stub();

    $res = test()->postJson('/api/encode', [
        'payload' => $payload,
        'code' => $payload['code'],
        'envelope' => 'v1line',
        'transport' => 'base64url+deflate',
        'serializer' => 'json',
        'by' => 'size',
        'size' => 120,
        'include_qr' => false,
        'writer' => 'bacon(svg,size=128,margin=8)', // should be ignored
    ])->assertOk()->json();

    expect($res)->not->toHaveKey('qr');
});

/**
 * 2) include_qr=true with alias writer (bacon / endroid) → includes 'qr' AND 'lines'
 */
it('includes qr when include_qr=true and writer alias is provided (bacon or endroid)', function () {
    if (!ec_has_bacon() && !ec_has_endroid()) {
        test()->markTestSkipped('No QR writer (bacon nor endroid) available in this environment.');
    }

    $payload = ec_payload_stub();

    // Prefer bacon if present; fall back to endroid
    $writerSpec = ec_has_bacon()
        ? 'bacon(svg,size=128,margin=8)'
        : 'endroid(svg,size=128,margin=8)';

    $res = test()->postJson('/api/encode', [
        'payload'         => $payload,
        'code'            => $payload['code'],
        'envelope'        => 'v1line',
        'transport'       => 'base64url+deflate',
        'serializer'      => 'json',
        'by'              => 'size',
        'size'            => 80,
        'include_qr'      => true,
        'writer'          => $writerSpec,
    ])->assertOk()->json();

    expect($res)->toHaveKey('qr');
    $qr = array_values($res['qr']);
    expect($qr)->toBeArray()->not->toBeEmpty();
    expect($qr[0])->toStartWith('<?xml');

    // 🔒 End-to-end sanity: ensure the encoded "lines" are also present
    expect($res)->toHaveKey('lines');
    expect($res['lines'])->toBeArray()->not->toBeEmpty();
})->group('qr');

/**
 * 3) include_qr=true with writer_fqcn + fmt/size/margin → includes 'qr' AND 'lines'
 */
it('includes qr when include_qr=true and writer_fqcn is used with fmt/size/margin', function () {
    $writerFqcn = null;
    if (ec_has_bacon()) {
        $writerFqcn = \TruthQr\Writers\BaconQrWriter::class;
    } elseif (ec_has_endroid()) {
        $writerFqcn = \TruthQr\Writers\EndroidQrWriter::class;
    } else {
        test()->markTestSkipped('No QR writer FQCN available in this environment.');
    }

    $payload = ec_payload_stub();

    $res = test()->postJson('/api/encode', [
        'payload'         => $payload,
        'code'            => $payload['code'],
        'envelope'        => 'v1line',
        'transport'       => 'base64url+deflate',
        'serializer'      => 'json',
        'by'              => 'size',
        'size'            => 80,
        'include_qr'      => true,
        'writer_fqcn'     => $writerFqcn,
        'writer_fmt'      => 'svg',
        'writer_size'     => 128,
        'writer_margin'   => 8,
    ])->assertOk()->json();

    expect($res)->toHaveKey('qr');
    $qr = array_values($res['qr']);
    expect($qr)->toBeArray()->not->toBeEmpty();
    expect($qr[0])->toStartWith('<?xml');

    // 🔒 End-to-end sanity: ensure the encoded "lines" are also present
    expect($res)->toHaveKey('lines');
    expect($res['lines'])->toBeArray()->not->toBeEmpty();
})->group('qr');

/**
 * 4) Bad writer alias → 422 with error
 */
it('rejects invalid writer alias with 422', function () {
    $payload = ec_payload_stub();

    $res = test()->postJson('/api/encode', [
        'payload' => $payload,
        'code' => $payload['code'],
        'envelope' => 'v1line',
        'transport' => 'base64url+deflate',
        'serializer' => 'json',
        'by' => 'size',
        'size' => 120,
        'include_qr' => true,
        'writer' => 'unknown(fmt=svg)', // invalid driver
    ])->assertStatus(422)->json();

    expect($res)->toHaveKey('error');
});

/**
 * 5) Missing writer FQCN class → 422 with error
 */
it('rejects missing writer_fqcn with 422', function () {
    $payload = ec_payload_stub();

    $res = test()->postJson('/api/encode', [
        'payload' => $payload,
        'code' => $payload['code'],
        'envelope' => 'v1line',
        'transport' => 'base64url+deflate',
        'serializer' => 'json',
        'by' => 'size',
        'size' => 120,
        'include_qr' => true,
        'writer_fqcn' => '\\Vendor\\Missing\\NopeWriter', // non-existent
        'writer_fmt' => 'svg',
        'writer_size' => 128,
        'writer_margin' => 8,
    ])->assertStatus(422)->json();

    expect($res)->toHaveKey('error');
});

it('encodes without QR by default (no writer, no include_qr)', function () {
    $payload = ['type'=>'demo','code'=>'NO-QR-DEF','data'=>['x'=>1]];

    $res = test()->postJson('/api/encode', [
        'payload'   => $payload,
        'code'      => $payload['code'],
        'envelope'  => 'v1line',
        'transport' => 'base64url+deflate',
        'serializer'=> 'json',
        // intentionally omit include_qr and writer*
    ])->assertOk()->json();

    // Lines (or urls) always present; qr should be absent
    expect($res)->toHaveKey('lines')
        ->and($res)->not->toHaveKey('qr');
});

it('encodes with QR using bacon(svg) when include_qr=true', function () {
    if (!class_exists(\BaconQrCode\Writer::class)) {
        test()->markTestSkipped('bacon/qr-code not installed');
    }

    $payload = ['type'=>'demo','code'=>'QR-BACON','data'=>['y'=>2]];

    $res = test()->postJson('/api/encode', [
        'payload'    => $payload,
        'code'       => $payload['code'],
        'envelope'   => 'v1line',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
        'include_qr' => true,
        'writer'     => 'bacon(svg,size=128,margin=8)',
    ])->assertOk()->json();

    // Ensure normal output plus qr array
    expect($res)->toHaveKey('lines')
        ->and($res)->toHaveKey('qr');

    $qr = array_values($res['qr']);
    expect($qr)->toBeArray()->not->toBeEmpty();
    expect($qr[0])->toStartWith('<?xml'); // SVG
});

it('rejects unknown writer driver with 422', function () {
    $payload = ['type'=>'demo','code'=>'QR-BAD','data'=>['z'=>3]];

    $resp = test()->postJson('/api/encode', [
        'payload'    => $payload,
        'code'       => $payload['code'],
        'envelope'   => 'v1line',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
        'include_qr' => true,
        'writer'     => 'not-a-writer(svg)',
    ])->assertStatus(422);

    $body = $resp->json();
    expect($body)->toHaveKey('error');
});

// Optional: endroid PNG (only if dependency present)
it('encodes with QR using endroid(png) when available', function () {
    if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
        test()->markTestSkipped('endroid/qr-code not installed');
    }

    $payload = ['type'=>'demo','code'=>'QR-END-PNG','data'=>['k'=>9]];

    $res = test()->postJson('/api/encode', [
        'payload'    => $payload,
        'code'       => $payload['code'],
        'envelope'   => 'v1line',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
        'include_qr' => true,
        'writer'     => 'endroid(png,size=128)',
    ])->assertOk()->json();

    expect($res)->toHaveKey('lines')
        ->and($res)->toHaveKey('qr');

    $qr = array_values($res['qr']);
    expect($qr)->toBeArray()->not->toBeEmpty();

    // If your Endroid writer returns data URLs:
    // expect($qr[0])->toStartWith('data:image/png');
    // If it returns raw binary, you might instead assert on PNG signature:
    // expect(substr($qr[0], 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    expect($qr[0])->toStartWith('data:image/png'); // adjust if needed for your writer
});
