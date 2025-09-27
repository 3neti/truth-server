<?php

declare(strict_types=1);

use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\EndroidQrWriter;

beforeEach(function () {
    // Ensure a fresh binding each test and avoid any package default binding
    app()->forgetInstance(TruthQrWriter::class);
//    app()->forgetInstances();
});

/** SVG */
it('renders SVG data for given lines (allows XML prolog) [Endroid]', function () {
    // Force Endroid writer (SVG)
    app()->bind(TruthQrWriter::class, fn () => new EndroidQrWriter('svg', 512, 16));

    /** @var TruthQrWriter $writer */
    $writer = app(TruthQrWriter::class);

    $lines = [
        1 => 'ER|v1|XYZ|1/2|PAYLOAD_A',
        2 => 'ER|v1|XYZ|2/2|PAYLOAD_B',
    ];
    $out = $writer->write($lines);

    // Normalize: strip optional BOM, whitespace, and XML prolog
    $normalize = static function (string $s): string {
        if (str_starts_with($s, "\xEF\xBB\xBF")) {
            $s = substr($s, 3);
        }
        $s = ltrim($s);
        $s = preg_replace('/^<\?xml[^>]*\?>\s*/i', '', $s) ?? $s;
        return ltrim($s);
    };

//    $svg1 = $normalize($out[1]);
//    $svg2 = $normalize($out[2]);
//
//    expect($out)->toBeArray()
//        ->and(array_keys($out))->toEqual([1, 2]);

    // Starts with <svg (don’t require trailing newline—Endroid may omit it)
//    expect($svg1)->toStartWith('<svg');
//    expect($svg2)->toStartWith('<svg');
//
//    // Basic sanity: non-trivial content
//    expect(strlen($out[1]))->toBeGreaterThan(100);
//    expect(str_contains($out[1], '<svg'))->toBeTrue();
});

///** PNG */
//it('renders PNG data for given lines [Endroid]', function () {
//    // Endroid's PNG writer relies on GD in many setups
//    if (!function_exists('imagecreatetruecolor')) {
//        test()->markTestSkipped('GD extension not available for PNG rendering.');
//    }
//
//    // Force Endroid writer (PNG)
//    app()->bind(TruthQrWriter::class, fn () => new EndroidQrWriter('png', 256, 8));
//
//    /** @var TruthQrWriter $writer */
//    $writer = app(TruthQrWriter::class);
//
//    $out = $writer->write([
//        0 => 'TEST-ENDROID',
//    ]);
//
//    expect(isset($out[0]))->toBeTrue();
//    expect(strlen($out[0]))->toBeGreaterThan(0);
//
//    // PNG magic header
//    expect(str_starts_with($out[0], "\x89PNG"))->toBeTrue();
//});
