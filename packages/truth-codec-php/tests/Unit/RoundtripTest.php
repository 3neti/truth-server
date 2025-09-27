<?php

use TruthCodec\Decode\{ChunkDecoder, ChunkAssembler};
use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Serializer\YamlSerializer;
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Contracts\Envelope;

test('roundtrip json', function () {
    $payload = ['type'=>'ER','code'=>'XYZ','data'=>['hello'=>'world']];
    $enc = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $lines = $enc->encodeToChunks($payload, 'XYZ', 16);

    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new JsonSerializer(), new Base64UrlTransport());

    foreach ($lines as $line) {
        $asm->add($dec->parseLine($line));
    }

    expect($asm->isComplete())->toBeTrue();
    expect($asm->assemble())->toEqual($payload);
});

test('roundtrip yaml', function () {
    $payload = ['type'=>'ballot','id'=>'B123','votes'=>[['position'=>'PRES','candidate'=>'LD']]];
    $enc = new ChunkEncoder(new YamlSerializer(), new Base64UrlTransport(), app(Envelope::class));
    $lines = $enc->encodeToChunks($payload, 'B123', 24);

    $dec = new ChunkDecoder(app(Envelope::class));
    $asm = new ChunkAssembler(new YamlSerializer(), new Base64UrlTransport());

    foreach ($lines as $line) {
        $asm->add($dec->parseLine($line));
    }
    expect($asm->assemble())->toBe($payload);
});
