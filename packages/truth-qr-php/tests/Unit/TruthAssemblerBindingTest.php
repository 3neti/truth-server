<?php

use TruthQr\Assembly\TruthAssembler;
use TruthQr\Contracts\TruthStore;
use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Contracts\PayloadSerializer;

/**
 * This checks that TruthAssembler resolves from the container
 * and its collaborators are bound.
 */
it('resolves TruthAssembler with bound collaborators', function () {
    /** @var TruthAssembler $asm */
    $asm = app(TruthAssembler::class);

    expect($asm)->toBeInstanceOf(TruthAssembler::class);

    // Also check collaborators resolve
    expect(app(TruthStore::class))->not()->toBeNull();
    expect(app(Envelope::class))->not()->toBeNull();
    expect(app(TransportCodec::class))->not()->toBeNull();
    expect(app(PayloadSerializer::class))->not()->toBeNull();
});
