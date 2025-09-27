<?php

declare(strict_types=1);

use TruthCodec\Envelope\EnvelopeV1Url;
use TruthQr\Contracts\TruthQrWriter;
use TruthCodec\Contracts\Envelope;
use TruthQr\Writers\NullQrWriter;

//it('binds Envelope::class to EnvelopeV1Url by default', function () {
//    $env = app(Envelope::class);
//    expect($env)->toBeInstanceOf(EnvelopeV1Url::class);
//
//    // quick sanity: header/parse roundtrip
//    $line = $env->header('XYZ', 1, 2, 'payload-part');
//    [$code, $i, $n, $payload] = $env->parse($line);
//    expect([$code, $i, $n, $payload])->toEqual(['XYZ', 1, 2, 'payload-part']);
//});
//
//
//it('merges default config (truth-qr)', function () {
//    // Assert config is present and has expected defaults
//    expect(config('truth-qr.default_format'))->toBe('png'); // adjust to your default
//    expect(config('truth-qr.default_transport'))->toBeNull(); // or whatever you set
//});
//
//it('binds TruthQrWriter::class to NullQrWriter by default', function () {
//    $writer = app(TruthQrWriter::class);
//    expect($writer)->toBeInstanceOf(NullQrWriter::class)
//        ->and($writer->format())->toBe(config('truth-qr.default_format', 'png'));
//});
//
//it('null writer returns tagged outputs', function () {
//    /** @var TruthQrWriter $writer */
//    $writer = app(TruthQrWriter::class);
//    $out = $writer->write(['L1', 'L2']);
//    expect($out)->toHaveKeys([0,1]);
//    expect($out[0])->toContain('qr:' . $writer->format() . ':L1');
//    expect($out[1])->toContain('qr:' . $writer->format() . ':L2');
//});
//
//use TruthQr\Writers\BaconQrWriter;
//
//it('binds TruthQrWriter to BaconQrWriter when driver=bacon', function () {
//    config()->set('truth-qr.driver', 'bacon');
//    config()->set('truth-qr.default_format', 'svg');
//
//    $writer = app(TruthQrWriter::class);
//
//    expect($writer)->toBeInstanceOf(BaconQrWriter::class);
//    expect($writer->format())->toBe('svg');
//});
