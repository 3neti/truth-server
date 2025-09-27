<?php

use Illuminate\Support\Facades\Redis;
use TruthQr\Stores\RedisTruthStore;

$hasRedis = false;
try {
    // Attempt a ping on default connection; if it errors, we’ll skip.
    Redis::connection()->ping();
    $hasRedis = true;
} catch (\Throwable $e) {
    $hasRedis = false;
}

it('stores and retrieves chunks via Redis', function () {
    $store = new RedisTruthStore(
        keyPrefix: 'test:truth:qr:',
        defaultTtl: 60,
        connection: null
    );

    $code = 'ABC123';
    $store->initIfMissing($code, 2, 60);

    $store->putChunk($code, 1, 2, 'P1');
    $st = $store->status($code);
    expect($st['received'])->toBe(1)
        ->and($st['missing'])->toEqual([2])
        ->and($store->isComplete($code))->toBeFalse();

    $store->putChunk($code, 2, 2, 'P2');
    expect($store->isComplete($code))->toBeTrue();

    $parts = $store->getChunks($code);
    expect($parts)->toEqual([1 => 'P1', 2 => 'P2']);

    $store->setArtifact($code, 'OK', 'text/plain');
    $art = $store->getArtifact($code);
    expect($art)->toEqual(['content' => 'OK', 'content_type' => 'text/plain']);

    $store->forget($code);
    expect($store->getChunks($code))->toBeEmpty();
})->skip(!$hasRedis, 'Redis not available; skipping RedisTruthStore test.');
