<?php

use TruthCodec\Envelope\EnvelopeV1Line;

it('round-trips a line envelope', function () {
    $env = new EnvelopeV1Line; // defaults: prefix=ER, version=v1

    $code = 'XYZ';
    $i    = 2;
    $n    = 5;
    $payload = '{"hello":"world"}';

    $line = $env->header($code, $i, $n, $payload);

    // Example: ER|v1|XYZ|2/5|{"hello":"world"}
    expect($line)->toMatch('/^ER\|v1\|XYZ\|2\/5\|/');

    [$c2, $i2, $n2, $p2] = $env->parse($line);
    expect([$c2, $i2, $n2, $p2])
        ->toEqual([$code, $i, $n, $payload]);
});

it('rejects invalid index/total on header()', function () {
    $env = new EnvelopeV1Line();
    expect(fn () => $env->header('ABC', 0, 3, 'x'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $env->header('ABC', 4, 3, 'x'))->toThrow(InvalidArgumentException::class);
    expect(fn () => $env->header('ABC', 1, 0, 'x'))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid line structure on parse()', function () {
    $env = new EnvelopeV1Line();
    // Missing segments
    expect(fn () => $env->parse('ER|v1|CODE|2/5'))->toThrow(InvalidArgumentException::class);
    // Bad i/N segment
    expect(fn () => $env->parse('ER|v1|CODE|2-of-5|payload'))->toThrow(InvalidArgumentException::class);
});

it('rejects mismatched prefix or version', function () {
    $env = new EnvelopeV1Line();

    // Wrong prefix
    $badPrefix = 'TRUTH|v1|CODE|1/2|abc';
    expect(fn () => $env->parse($badPrefix))->toThrow(InvalidArgumentException::class);

    // Wrong version
    $badVersion = 'ER|v2|CODE|1/2|abc';
    expect(fn () => $env->parse($badVersion))->toThrow(InvalidArgumentException::class);
});

it('guards index/total during parse()', function () {
    $env = new EnvelopeV1Line();

    // Index out of range (4/3)
    $line = 'ER|v1|CODE|4/3|abc';
    expect(fn () => $env->parse($line))->toThrow(InvalidArgumentException::class);

    // Zero/zero
    $line2 = 'ER|v1|CODE|0/0|abc';
    expect(fn () => $env->parse($line2))->toThrow(InvalidArgumentException::class);
});

it('allows runtime override via constructor and fluent setters (line)', function () {
    $env = new \TruthCodec\Envelope\EnvelopeV1Line('BAL', 'v1');
    $line = $env->header('CODE', 1, 2, 'x');
    expect($line)->toStartWith('BAL|v1|CODE|1/2|');

    // Fluent override wins over ctor
    $env2 = $env->withPrefix('ER');
    $line2 = $env2->header('CODE', 1, 2, 'x');
    expect($line2)->toStartWith('ER|v1|CODE|1/2|');
});
