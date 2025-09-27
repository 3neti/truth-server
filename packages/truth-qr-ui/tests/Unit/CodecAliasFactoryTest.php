<?php
// tests/Unit/CodecAliasFactoryTest.php

declare(strict_types=1);

use TruthQrUi\Support\CodecAliasFactory as Alias;

// truth-codec-php
use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Envelope\EnvelopeV1Line;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Serializer\AutoDetectSerializer;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Serializer\YamlSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;
use TruthCodec\Transport\Base64UrlGzipTransport;
use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Transport\NoopTransport;

// truth-qr-php
use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\BaconQrWriter;
use TruthQr\Writers\EndroidQrWriter;
use TruthQr\Writers\NullQrWriter;

/** Serializers */
it('maps serializer aliases to concrete implementations', function () {
    expect(Alias::makeSerializer('json'))->toBeInstanceOf(JsonSerializer::class);
    expect(Alias::makeSerializer('yaml'))->toBeInstanceOf(YamlSerializer::class);
    expect(Alias::makeSerializer('auto'))->toBeInstanceOf(AutoDetectSerializer::class);
//    // alias variants
    expect(Alias::makeSerializer('yml'))->toBeInstanceOf(YamlSerializer::class);
    expect(Alias::makeSerializer('autodetect'))->toBeInstanceOf(AutoDetectSerializer::class);
});

/** Transports */
it('maps transport aliases to concrete implementations', function () {
    expect(Alias::makeTransport('none'))->toBeInstanceOf(NoopTransport::class);
    expect(Alias::makeTransport('base64url'))->toBeInstanceOf(Base64UrlTransport::class);
    expect(Alias::makeTransport('b64url'))->toBeInstanceOf(Base64UrlTransport::class);
    expect(Alias::makeTransport('base64url+deflate'))->toBeInstanceOf(Base64UrlDeflateTransport::class);
    expect(Alias::makeTransport('b64url+deflate'))->toBeInstanceOf(Base64UrlDeflateTransport::class);
    expect(Alias::makeTransport('base64url+gzip'))->toBeInstanceOf(Base64UrlGzipTransport::class);
    expect(Alias::makeTransport('b64url+gzip'))->toBeInstanceOf(Base64UrlGzipTransport::class);
});

/** Envelopes */
it('maps envelope aliases to concrete implementations', function () {
    expect(Alias::makeEnvelope('v1url'))->toBeInstanceOf(EnvelopeV1Url::class);
    expect(Alias::makeEnvelope('url'))->toBeInstanceOf(EnvelopeV1Url::class);
    expect(Alias::makeEnvelope('v1line'))->toBeInstanceOf(EnvelopeV1Line::class);
    expect(Alias::makeEnvelope('line'))->toBeInstanceOf(EnvelopeV1Line::class);
});

/** Writers: Null (always available) */
it('creates a Null writer from spec', function () {
    $w = Alias::makeWriter('null(svg)');
    expect($w)->toBeInstanceOf(NullQrWriter::class);
    // interface contract typically exposes ->format()
    expect(method_exists($w, 'format'))->toBeTrue();
    expect($w->format())->toBe('svg');
});

/** Writers: Bacon (skip if package not installed) */
it('creates a Bacon writer from spec with tunables', function () {
    if (!class_exists(\BaconQrCode\Writer::class)) {
        test()->markTestSkipped('bacon/qr-code not installed.');
    }

    /** @var TruthQrWriter $w */
    $w = Alias::makeWriter('bacon(svg,size=300,margin=8)');
    expect($w)->toBeInstanceOf(BaconQrWriter::class);
    expect($w->format())->toBe('svg');
})->group('writers');

/** Writers: Endroid (skip if package not installed) */
it('creates an Endroid writer from spec', function () {
    if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
        test()->markTestSkipped('endroid/qr-code not installed.');
    }

    /** @var TruthQrWriter $w */
    $w = Alias::makeWriter('endroid(png,size=256,margin=12)');
    expect($w)->toBeInstanceOf(EndroidQrWriter::class);
    expect($w->format())->toBe('png');
})->group('writers');

/** Invalid inputs */
it('throws for unknown aliases and specs', function () {
    expect(fn () => Alias::makeSerializer('toml'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Alias::makeTransport('foobar'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Alias::makeEnvelope('v2line'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Alias::makeWriter('pixelart(gif)'))->toThrow(InvalidArgumentException::class);
});

// --- Envelope overrides: URL ---
it('makeEnvelope(url) accepts explicit prefix/version via opts', function () {
    // When overrides are provided, the ctor should apply them and header()/parse() should reflect them.
    $env = Alias::makeEnvelope('v1url', ['prefix' => 'BAL', 'version' => 'v1']);
    expect($env)->toBeInstanceOf(EnvelopeV1Url::class);

    $url = $env->header('CODE123', 1, 2, 'PAY');
    // truth://v1/BAL/CODE123/1/2?c=PAY
    expect($url)->toStartWith('truth://v1/BAL/');

    [$code,$i,$n,$c] = $env->parse($url);
    expect([$code,$i,$n,$c])->toEqual(['CODE123',1,2,'PAY']);
});

// --- Envelope overrides: LINE ---
it('makeEnvelope(line) accepts explicit prefix/version via opts', function () {
    $env = Alias::makeEnvelope('v1line', ['prefix' => 'TRUTH', 'version' => 'v1']);
    expect($env)->toBeInstanceOf(EnvelopeV1Line::class);

    $line = $env->header('L-CODE', 2, 3, 'P');
    // TRUTH|v1|L-CODE|2/3|P
    expect($line)->toStartWith('TRUTH|v1|');

    [$code,$i,$n,$p] = $env->parse($line);
    expect([$code,$i,$n,$p])->toEqual(['L-CODE',2,3,'P']);
});

// --- Envelope: no overrides still works (back-compat) ---
it('makeEnvelope without opts falls back to ctor defaults/config/constants', function () {
    $env1 = Alias::makeEnvelope('v1url');
    $env2 = Alias::makeEnvelope('v1line');

    expect($env1)->toBeInstanceOf(EnvelopeV1Url::class);
    expect($env2)->toBeInstanceOf(EnvelopeV1Line::class);

    // Smoke: both can header()/parse() round-trip using their internal prefix/version.
    $u = $env1->header('A', 1, 1, 'X');
    [$c1,$i1,$n1,$p1] = $env1->parse($u);
    expect([$c1,$i1,$n1,$p1])->toEqual(['A',1,1,'X']);

    $l = $env2->header('B', 1, 1, 'Y');
    [$c2,$i2,$n2,$p2] = $env2->parse($l);
    expect([$c2,$i2,$n2,$p2])->toEqual(['B',1,1,'Y']);
});

// --- Envelope: invalid alias still errors ---
it('makeEnvelope throws on unknown alias (with or without opts)', function () {
    expect(fn () => Alias::makeEnvelope('nope'))->toThrow(InvalidArgumentException::class);
    expect(fn () => Alias::makeEnvelope('nope', ['prefix' => 'X']))->toThrow(InvalidArgumentException::class);
});
