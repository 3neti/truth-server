<?php

use TruthQr\Assembly\Contracts\TruthAssemblerContract;
use TruthQr\Tests\Support\FakeTruthAssembler;

it('streams JSON artifact when available', function () {
    app()->singleton(TruthAssemblerContract::class, fn () => new FakeTruthAssembler());

    $this->post('/truth/ingest', ['line' => 'ER|v1|XYZ|1/2|A'])->assertOk();
    $this->post('/truth/ingest', ['line' => 'ER|v1|XYZ|2/2|B'])->assertOk();

    $res = $this->get('/truth/artifact/XYZ');
    $res->assertOk()->assertHeader('Content-Type', 'application/json');

    expect($res->getContent())->toBe('{"ok":true,"code":"XYZ"}');
});

it('404s when artifact is missing', function () {
    app()->singleton(\TruthQr\Assembly\TruthAssembler::class, fn () => new FakeTruthAssembler());

    $res = $this->get('/truth/artifact/NONE');
    $res->assertStatus(404);
});
