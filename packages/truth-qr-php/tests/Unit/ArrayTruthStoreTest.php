<?php

use TruthQr\Contracts\TruthStore;
use TruthQr\Stores\ArrayTruthStore;

it('tracks progress and completeness (ArrayTruthStore)', function () {
    $store = new ArrayTruthStore();

    $code = 'XYZ';
    $store->initIfMissing($code, 3);

    // put chunks out of order
    $store->putChunk($code, 2, 3, 'BB');
    $store->putChunk($code, 1, 3, 'AA');

    $st = $store->status($code);
    expect($st)->toMatchArray([
        'code' => $code,
        'total' => 3,
        'received' => 2,
    ]);
    expect($st['missing'])->toEqual([3]);
    expect($store->isComplete($code))->toBeFalse();

    $store->putChunk($code, 3, 3, 'CC');
    expect($store->isComplete($code))->toBeTrue();

    // artifact cache
    $store->setArtifact($code, '{"ok":true}', 'application/json');
    $art = $store->getArtifact($code);
    expect($art)->toBeArray()
        ->and($art['content'])->toBe('{"ok":true}')
        ->and($art['content_type'])->toBe('application/json');

    // chunks retrieval
    $parts = $store->getChunks($code);
    expect($parts)->toEqual([1 => 'AA', 2 => 'BB', 3 => 'CC']);

    // cleanup
    $store->forget($code);
    expect($store->getChunks($code))->toBeEmpty();
});
