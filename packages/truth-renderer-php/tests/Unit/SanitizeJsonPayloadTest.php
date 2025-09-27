<?php

use TruthRenderer\Support\SanitizeJsonPayload;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->fixturePath = 'tests/fixtures/ER-317537-payload.json';
    $this->sanitizer = new SanitizeJsonPayload();
});

it('can sanitize and decode from file_get_contents', function () {
    $raw = file_get_contents($this->fixturePath);

    $parsed = $this->sanitizer->toArray($raw);

    expect($parsed)
        ->toBeArray()
        ->toHaveKey('templateName')
        ->and($parsed['data'])->toHaveKey('qrMeta');
});

it('can sanitize and decode from stream', function () {
    $stream = fopen($this->fixturePath, 'r');
    $raw = stream_get_contents($stream);
    fclose($stream);

    $parsed = $this->sanitizer->toArray($raw);

    expect($parsed)
        ->toBeArray()
        ->toHaveKey('data')
        ->and($parsed['data']['qrMeta'])->toHaveKey('qr');
});

it('can sanitize and decode from a Laravel Request body', function () {
    $raw = file_get_contents($this->fixturePath);
    $request = Request::create('/test', 'POST', [], [], [], [], $raw);

    $parsed = $this->sanitizer->toArray($request->getContent());

    expect($parsed)
        ->toBeArray()
        ->toHaveKey('format')
        ->and($parsed['data']['tallyMeta'])->toHaveKey('id');
});
