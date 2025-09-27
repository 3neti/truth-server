<?php

use TruthQr\Support\Chunking;

it('splits by count into 1-based parts', function () {
    $blob  = str_repeat('A', 10); // 10 bytes
    $parts = Chunking::splitByCount($blob, 3);

    expect(array_keys($parts))->toEqual([1,2,3]);
    expect(strlen($parts[1]) + strlen($parts[2]) + strlen($parts[3]))->toBe(10);
});

it('splits by size into 1-based parts', function () {
    $blob  = str_repeat('B', 25);
    $parts = Chunking::splitBySize($blob, 8); // 8+8+8+1

    expect(array_keys($parts))->toEqual([1,2,3,4]);
    expect(array_values(array_map('strlen', $parts)))->toEqual([8,8,8,1]);
});
