<?php

declare(strict_types=1);

use BaconQrCode\Renderer\Image\ImagickImageBackEnd; // used in optional conversion test
use BaconQrCode\Renderer\Image\EpsImageBackEnd;     // vector EPS backend
use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\BaconQrWriter;

/**
 * EPS is BaconQrCode's vector “print-friendly” backend.
 * This test ensures our writer emits valid EPS data.
 */
it('renders EPS vector data for given lines', function () {
    if (!class_exists(EpsImageBackEnd::class)) {
        $this->markTestSkipped('EPS backend (EpsImageBackEnd) not available.');
    }

    // Explicitly bind Bacon writer with EPS format
    app()->bind(TruthQrWriter::class, fn () => new BaconQrWriter('eps', 512, 16));

    /** @var TruthQrWriter $writer */
    $writer = app(TruthQrWriter::class);

    $lines = [
        1 => 'TRUTH|v1|XYZ|1/2|PAYLOAD_A',
        2 => 'TRUTH|v1|XYZ|2/2|PAYLOAD_B',
    ];

    $out = $writer->write($lines);

    expect($out)->toBeArray()
        ->and(array_keys($out))->toEqual([1, 2]);

    // EPS typically starts with PostScript header
    expect($out[1])->toStartWith('%!PS-Adobe-')
        ->and($out[2])->toStartWith('%!PS-Adobe-');

    // Basic sanity: BoundingBox is commonly present in EPS
    expect($out[1])->toContain('%%BoundingBox:')
        ->and($out[2])->toContain('%%BoundingBox:');

    // Non-empty payloads
    expect(strlen($out[1]))->toBeGreaterThan(100);
    expect(strlen($out[2]))->toBeGreaterThan(100);
});

/**
 * OPTIONAL smoke test: convert EPS to PDF using Imagick.
 * This requires Ghostscript + Imagick PDF support and is brittle in CI.
 * Enable locally by un-skipping or by checking an env flag.
 */
it('optionally converts EPS to PDF via Imagick (smoke test)', function () {
    if (!class_exists(EpsImageBackEnd::class) || !class_exists(ImagickImageBackEnd::class) || !class_exists(Imagick::class)) {
        $this->markTestSkipped('Imagick + EPS required for conversion smoke test.');
    }

    if (!env('ENABLE_EPS_TO_PDF_TEST', false)) {
        $this->markTestSkipped('Guarded by ENABLE_EPS_TO_PDF_TEST env flag.');
    }

    // Explicit binding again
    app()->bind(TruthQrWriter::class, fn () => new BaconQrWriter('eps', 512, 16));

    /** @var TruthQrWriter $writer */
    $writer = app(TruthQrWriter::class);

    $out = $writer->write([0 => 'PDF-SMOKE']);
    $eps = $out[0] ?? '';

    expect($eps)->toStartWith('%!PS-Adobe-');

    // Convert EPS -> PDF using Imagick (requires Ghostscript under the hood)
    $img = new Imagick();
    $img->readImageBlob($eps);
    $img->setImageFormat('pdf');
    $pdf = $img->getImageBlob();
    $img->clear();

    // Basic sanity: PDF starts with %PDF-1.
    expect(substr($pdf, 0, 5))->toBe('%PDF-');
    expect(strlen($pdf))->toBeGreaterThan(2000);
});
