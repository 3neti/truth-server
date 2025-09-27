<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;

/**
 * Sanity: the package should register a playground route.
 */
it('registers the playground route', function () {
    expect(Route::has('truth-qr.playground'))->toBeTrue();
});

/**
 * The controller should render an Inertia page.
 * We only assert the component path so the test stays tolerant of prop changes.
 */
it('renders the Playground Inertia page', function () {
    $resp = $this->get(route('truth-qr.playground'));

    // If Inertia isn’t installed in the host app, this will be a 404/500; skip in that case.
    if (! class_exists(\Inertia\Inertia::class)) {
        test()->markTestSkipped('Inertia is not installed in this environment.');
    }

    $resp->assertOk()
        ->assertInertia(fn (Assert $page) =>
        $page->component('TruthQrUi/Playground') // <— keep this in sync with your controller
        );
});

/**
 * Optional: if your controller passes props, verify their shape without being brittle.
 * This test is tolerant: props are optional; when present we check minimal structure.
 */
it('optionally exposes useful props to the page (tolerant)', function () {
    if (! class_exists(\Inertia\Inertia::class)) {
        test()->markTestSkipped('Inertia is not installed in this environment.');
    }

    $resp = $this->get(route('truth-qr.playground'))->assertOk();

    // Only assert the component; if you later add props like meta/env/defaults, expand here.
    $resp->assertInertia(fn (Assert $page) =>
    $page->component('TruthQrUi/Playground')
        ->etc() // allow any props / none
    );
});

/**
 * Lightweight end-to-end: confirm the encode/decode API routes that the Playground
 * will call are available and mutually compatible using a small payload.
 */
it('exercises encode → decode endpoints end-to-end', function () {
    // Sanity: routes exist
    expect(Route::has('truth-qr.encode'))->toBeTrue();
    expect(Route::has('truth-qr.decode'))->toBeTrue();

    $payload = [
        'type' => 'demo',
        'code' => 'PG-E2E-001',
        'data' => ['x' => 1, 'y' => 2]
    ];

    // Encode (use URL envelope so Playground can show clickable lines)
    $enc = $this->postJson(route('truth-qr.encode'), [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'by'               => 'size',
        'size'             => 200,
        // prefer aliases (UI-friendly); FQCNs also work if you pass *_fqcn instead
        'envelope'         => 'v1url',
        'transport'        => 'base64url+deflate',
        'serializer'       => 'json',
    ])->assertOk()->json();

    // Normalize output to a flat array of lines/urls
    $lines = (static function (array $res): array {
        if (isset($res['urls']) && is_array($res['urls'])) {
            return array_map(static fn($v) => (string) $v, array_values($res['urls']));
        }
        if (isset($res['lines']) && is_array($res['lines'])) {
            return array_map(static fn($v) => (string) $v, array_values($res['lines']));
        }
        throw new RuntimeException('Encode response missing urls/lines');
    })($enc);

    expect($lines)->toBeArray()->not->toBeEmpty();

    // Shuffle to simulate out-of-order scans
    shuffle($lines);

    // Decode with the same collaborators (aliases)
    $dec = $this->postJson(route('truth-qr.decode'), [
        'lines'      => $lines,
        'envelope'   => 'v1url',
        'transport'  => 'base64url+deflate',
        'serializer' => 'json',
    ])->assertOk()->json();

    expect($dec['complete'] ?? null)->toBeTrue();
    expect($dec['payload']  ?? null)->toEqual($payload);
});

/**
 * FQCN-based variant (what advanced users might hit from a custom client).
 */
it('supports FQCN-based encode → decode as well', function () {
    $payload = ['type'=>'demo','code'=>'PG-E2E-FQCN','data'=>['k'=>'v']];

    $enc = $this->postJson(route('truth-qr.encode'), [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'by'               => 'size',
        'size'             => 120,
        'envelope_fqcn'    => EnvelopeV1Url::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'serializer_fqcn'  => JsonSerializer::class,
    ])->assertOk()->json();

    $urls = $enc['urls'] ?? $enc['lines'] ?? [];
    expect($urls)->toBeArray()->not->toBeEmpty();

    shuffle($urls);

    $dec = $this->postJson(route('truth-qr.decode'), [
        'lines'            => $urls,
        'envelope_fqcn'    => EnvelopeV1Url::class,
        'transport_fqcn'   => Base64UrlDeflateTransport::class,
        'serializer_fqcn'  => JsonSerializer::class,
    ])->assertOk()->json();

    expect($dec['complete'] ?? null)->toBeTrue();
    expect($dec['payload']  ?? null)->toEqual($payload);
});

it('encode+decode honors envelope_prefix/version for URL envelopes', function () {
    $payload = [
        'type' => 'demo',
        'code' => 'PG-URL-KNOBS-001',
        'data' => ['hello' => 'world'],
    ];

    // Encode with alias + explicit prefix/version overrides
    $enc = test()->postJson('/api/encode', [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'envelope'         => 'v1url',                 // alias path
        'envelope_prefix'  => 'TRUTH',                 // override
        'envelope_version' => 'v9',                    // override
        'transport'        => 'base64url+deflate',
        'serializer'       => 'json',
        'by'               => 'size',
        'size'             => 200,
    ])->assertOk()->json();

    // Extract lines/urls in a tolerant way
    $urls = isset($enc['urls']) ? array_values($enc['urls']) : (isset($enc['lines']) ? array_values($enc['lines']) : []);
    expect($urls)->toBeArray()->not->toBeEmpty();

    // Must reflect the overridden version/prefix in the deep-link path
    // truth://v9/TRUTH/<CODE>/<i>/<n>?c=...
    expect($urls[0])->toStartWith('truth://v9/TRUTH/');

    // Round-trip decode using the same alias + overrides
    shuffle($urls);
    $dec = test()->postJson('/api/decode', [
        'lines'            => $urls,
        'envelope'         => 'v1url',
        'envelope_prefix'  => 'TRUTH',
        'envelope_version' => 'v9',
        'transport'        => 'base64url+deflate',
        'serializer'       => 'json',
    ])->assertOk()->json();

    expect($dec['complete'] ?? null)->toBeTrue();
    expect($dec['payload'] ?? null)->toEqual($payload);
});

it('encode+decode honors envelope_prefix/version for LINE envelopes', function () {
    $payload = [
        'type' => 'demo',
        'code' => 'PG-LINE-KNOBS-001',
        'data' => ['x' => 1],
    ];

    // Encode with alias + explicit prefix/version overrides
    $enc = test()->postJson('/api/encode', [
        'payload'          => $payload,
        'code'             => $payload['code'],
        'envelope'         => 'v1line',                // alias path
        'envelope_prefix'  => 'BAL',                   // override
        'envelope_version' => 'v2',                    // override
        'transport'        => 'base64url+deflate',
        'serializer'       => 'json',
        'by'               => 'size',
        'size'             => 120,
    ])->assertOk()->json();

    $lines = isset($enc['lines']) ? array_values($enc['lines']) : (isset($enc['urls']) ? array_values($enc['urls']) : []);
    expect($lines)->toBeArray()->not->toBeEmpty();

    // Must reflect the overridden tokens at the head of the line:
    // BAL|v2|<CODE>|<i>/<n>|<payload>
    expect($lines[0])->toStartWith('BAL|v2|');

    // Round-trip decode using the same alias + overrides
    shuffle($lines);
    $dec = test()->postJson('/api/decode', [
        'lines'            => $lines,
        'envelope'         => 'v1line',
        'envelope_prefix'  => 'BAL',
        'envelope_version' => 'v2',
        'transport'        => 'base64url+deflate',
        'serializer'       => 'json',
    ])->assertOk()->json();

    expect($dec['complete'] ?? null)->toBeTrue();
    expect($dec['payload'] ?? null)->toEqual($payload);
});
