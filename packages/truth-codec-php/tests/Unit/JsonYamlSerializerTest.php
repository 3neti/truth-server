<?php

use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Serializer\YamlSerializer;

test('json serializer', function () {
    $s = new JsonSerializer();
    $txt = $s->encode(['b'=>2,'a'=>1]);
    expect($txt)->toBe('{"a":1,"b":2}');
    $arr = $s->decode($txt);
    expect($arr)->toBe(['a'=>1,'b'=>2]);
});

test('yaml serializer', function () {
    $s = new YamlSerializer();
    $txt = $s->encode(['a'=>1,'b'=>[2,3]]);
    expect($s->decode($txt))->toBe(['a'=>1,'b'=>[2,3]]);
});
