<?php

use TruthCodec\Envelope\EnvelopeV1Url;

it('builds and parses deep link with default config', function () {
    config()->set('truth-codec.envelope.prefix', 'ER');
    config()->set('truth-codec.envelope.version', 'v1');
    config()->set('truth-codec.url.scheme', 'truth');
    config()->set('truth-codec.url.web_base', null);
    config()->set('truth-codec.url.payload_param', 'c');

    $env = new EnvelopeV1Url();

    $line = $env->header('XYZ', 2, 5, 'PAYLOAD');
    expect($line)->toStartWith('truth://v1/ER/XYZ/2/5?c=');

    [$code,$i,$n,$c] = $env->parse($line);
    expect([$code,$i,$n,$c])->toEqual(['XYZ',2,5,'PAYLOAD']);
});

it('builds and parses https URL when web_base set', function () {
    config()->set('truth-codec.envelope.prefix', 'ER');
    config()->set('truth-codec.envelope.version', 'v1');
    config()->set('truth-codec.url.web_base', 'https://truth.example/ingest');

    $env = new EnvelopeV1Url();
    $line = $env->header('XYZ', 1, 3, 'P');
    expect($line)->toStartWith('https://truth.example/ingest?');

    [$code,$i,$n,$c] = $env->parse($line);
    expect([$code,$i,$n,$c])->toEqual(['XYZ',1,3,'P']);
});

beforeEach(function () {
    // Common defaults
    config()->set('truth-codec.envelope.prefix', 'ER');
    config()->set('truth-codec.envelope.version', 'v1');

    // URL defaults (deep-link unless web_base is set)
    config()->set('truth-codec.url.scheme', 'truth');
    config()->set('truth-codec.url.web_base', null);
    config()->set('truth-codec.url.payload_param', 'c');
    config()->set('truth-codec.url.version_param', 'v'); // parse fallback only
});

it('round-trips a deep-link truth:// URL envelope', function () {
    // Deep link: web_base is null by default
    $env = new EnvelopeV1Url();

    $code = 'CURRIMAO-001';
    $i = 1;
    $n = 3;
    // include characters to exercise RFC3986 encoding
    $payload = 'eyJmb28iOiJiYXI/IyAmIiE9In0=';

    $url = $env->header($code, $i, $n, $payload);

    // Example: truth://v1/ER/CURRIMAO-001/1/3?c=eyJmb28iOiJiYXI%2FIyAmIiE9In0%3D
    expect($url)->toStartWith('truth://v1/ER/' . rawurlencode($code) . '/1/3?');

    [$c2, $i2, $n2, $p2] = $env->parse($url);
    expect([$c2, $i2, $n2, $p2])->toEqual([$code, $i, $n, $payload]);
});

it('round-trips a web URL http(s) envelope', function () {
    // Force https links
    config()->set('truth-codec.url.web_base', 'https://truth.example/ingest');
    // (payload_param remains "c", version encoded as "truth=v1")

    $env = new EnvelopeV1Url();

    $code = 'XYZ';
    $i = 2;
    $n = 4;
    $payload = 'A_B-C~safe.payload';

    $url = $env->header($code, $i, $n, $payload);

    // Example: https://truth.example/ingest?truth=v1&prefix=ER&code=XYZ&i=2&n=4&c=A_B-C~safe.payload
    expect($url)->toStartWith('https://truth.example/ingest?');

    [$c2, $i2, $n2, $p2] = $env->parse($url);
    expect([$c2, $i2, $n2, $p2])->toEqual([$code, $i, $n, $payload]);
});

it('rejects invalid deep-link structures', function () {
    // Deep-link mode
    config()->set('truth-codec.url.web_base', null);
    $env = new EnvelopeV1Url();

    // Not enough segments
    $bad = 'truth://v1/ER/CODE/1?c=abc';
    expect(fn() => $env->parse($bad))->toThrow(InvalidArgumentException::class);

    // Missing payload param
    $bad2 = 'truth://v1/ER/CODE/1/3';
    expect(fn() => $env->parse($bad2))->toThrow(InvalidArgumentException::class);

    // Prefix/version mismatch
    $bad3 = 'truth://v2/TRUTH/CODE/1/3?c=x';
    expect(fn() => $env->parse($bad3))->toThrow(InvalidArgumentException::class);

    // Index out of range
    $bad4 = 'truth://v1/ER/CODE/4/3?c=x';
    expect(fn() => $env->parse($bad4))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid web URL structures', function () {
    // Web URL mode
    config()->set('truth-codec.url.web_base', 'https://truth.example/ingest');
    $env = new EnvelopeV1Url();

    // Missing query
    $bad = 'https://truth.example/ingest';
    expect(fn() => $env->parse($bad))->toThrow(InvalidArgumentException::class);

    // Missing required params
    $bad2 = 'https://truth.example/ingest?truth=v1&prefix=ER&code=XYZ&i=1';
    expect(fn() => $env->parse($bad2))->toThrow(InvalidArgumentException::class);

    // Prefix/version mismatch
    $bad3 = 'https://truth.example/ingest?truth=v2&prefix=ER&code=XYZ&i=1&n=2&c=x';
    expect(fn() => $env->parse($bad3))->toThrow(InvalidArgumentException::class);

    // Index out of range
    $bad4 = 'https://truth.example/ingest?truth=v1&prefix=ER&code=XYZ&i=4&n=3&c=x';
    expect(fn() => $env->parse($bad4))->toThrow(InvalidArgumentException::class);
});

it('allows runtime override via constructor and fluent setters (url)', function () {
    config()->set('truth-codec.url.web_base', null); // deep-link
    $env = new \TruthCodec\Envelope\EnvelopeV1Url('BAL', 'v1');

    $url = $env->header('CODE', 1, 2, 'P');
    expect($url)->toStartWith('truth://v1/BAL/CODE/1/2?');

    $env2 = $env->withPrefix('ER');
    $url2 = $env2->header('CODE', 1, 2, 'P');
    expect($url2)->toStartWith('truth://v1/ER/CODE/1/2?');
});
