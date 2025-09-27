<?php

use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\GDLibRenderer;
use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\BaconQrWriter;

beforeEach(function () {
    // Ensure a fresh binding each test
    app()->forgetInstance(TruthQrWriter::class);
});

/** SVG */
it('renders SVG data for given lines (allows XML prolog)', function () {
    // Force Bacon writer (SVG)
    app()->bind(\TruthQr\Contracts\TruthQrWriter::class, fn () => new \TruthQr\Writers\BaconQrWriter('svg', 512, 16));

    /** @var \TruthQr\Contracts\TruthQrWriter $writer */
    $writer = app(\TruthQr\Contracts\TruthQrWriter::class);

    $lines = [
        1 => 'ER|v1|XYZ|1/2|PAYLOAD_A',
        2 => 'ER|v1|XYZ|2/2|PAYLOAD_B',
    ];
    $out = $writer->write($lines);

    $normalize = static function (string $s): string {
        // Strip BOM
        if (str_starts_with($s, "\xEF\xBB\xBF")) $s = substr($s, 3);
        // Trim leading whitespace
        $s = ltrim($s);
        // Remove optional XML prolog
        $s = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $s) ?? $s;
        return ltrim($s);
    };

    $svg1 = $normalize($out[1]);
    $svg2 = $normalize($out[2]);

    // Start with <svg
    expect($svg1)->toMatch("/^<svg\b/i");
    expect($svg2)->toMatch("/^<svg\b/i");

    // End with </svg> (allow optional trailing newline/whitespace)
    expect(rtrim($svg1))->toEndWith("</svg>");
    expect(rtrim($svg2))->toEndWith("</svg>");

    // Quick sanity checks
    expect(strlen($out[1]))->toBeGreaterThan(100);
    expect(str_contains($out[1], '<rect'))->toBeTrue();
});

/** PNG */
it('renders PNG data when PNG backends are present (Imagick or GD)', function () {
    $hasImagick = class_exists(ImagickImageBackEnd::class);
    $hasGd = class_exists(GDLibRenderer::class);

    if (!$hasImagick && !$hasGd) {
        $this->markTestSkipped('PNG backend (Imagick or GDLibRenderer) not available.');
    }

    // Force Bacon writer (PNG)
    app()->bind(TruthQrWriter::class, fn () => new BaconQrWriter('png', 512, 16));

    /** @var TruthQrWriter $writer */
    $writer = app(TruthQrWriter::class);

    $out = $writer->write([0 => 'TEST']);

    expect(isset($out[0]))->toBeTrue();
    expect(strlen($out[0]))->toBeGreaterThan(0);
    expect(str_starts_with($out[0], "\x89PNG"))->toBeTrue();
});
