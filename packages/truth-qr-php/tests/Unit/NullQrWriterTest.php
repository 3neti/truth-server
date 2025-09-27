<?php

use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\NullQrWriter;

beforeEach(function () {
    // Force Null writer for this test only
    config()->set('truth-qr.writer.format', 'svg'); // or 'png' — just keep in sync with the expectation
    app()->bind(TruthQrWriter::class, fn () => new NullQrWriter(config('truth-qr.writer.format', 'svg')));
});


it('null writer writes deterministic qr lines and preserves keys', function () {
    /** @var TruthQrWriter $writer */
    $writer = app(TruthQrWriter::class);

    $fmt = $writer->format();
    expect($fmt)->toBe(config('truth-qr.writer.format', 'svg'));

    $lines = [
        10 => 'ER|v1|XYZ|1/2|PAYLOAD_A',
        11 => 'ER|v1|XYZ|2/2|PAYLOAD_B',
    ];

    $out = $writer->write($lines);

    expect($out)->toBeArray()
        ->and(array_keys($out))->toEqual([10, 11])
        ->and($out[10])->toBe("qr:{$fmt}:" . $lines[10])
        ->and($out[11])->toBe("qr:{$fmt}:" . $lines[11]);
});
