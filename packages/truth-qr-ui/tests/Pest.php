<?php

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TruthQrUi\Tests\TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature', 'Unit');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}


use TruthCodec\Contracts\{Envelope, TransportCodec, PayloadSerializer};
use TruthQr\Assembly\TruthAssembler;
use TruthQr\Stores\ArrayTruthStore;
use TruthQr\Classify\Classify;

/**
 * Helpers
 * -------
 */

/**
 * Normalize whatever the controller returns into a flat list of strings.
 * Supports:
 *  - ['lines' => [...]]
 *  - ['urls'  => [...]]
 *  - ['chunks' => [{'text'=>...}, ...]]  // legacy shape, in case it appears
 */
function ep_lines_from_result(array $res): array
{
    if (isset($res['lines']) && is_array($res['lines'])) {
        return array_map(static fn($v) => (string) $v, array_values($res['lines']));
    }

    if (isset($res['urls']) && is_array($res['urls'])) {
        return array_map(static fn($v) => (string) $v, array_values($res['urls']));
    }

    if (isset($res['chunks']) && is_array($res['chunks'])) {
        return array_values(array_map(
            static fn($c) => (string)($c['text'] ?? ''),
            $res['chunks']
        ));
    }

    throw new RuntimeException('EncodePayload result does not include lines, urls, or chunks.');
}

/** Build a TruthAssembler using explicit collaborators (no container bindings). */
function ep_make_assembler(PayloadSerializer $ser, TransportCodec $tx, Envelope $env): TruthAssembler
{
    return new TruthAssembler(
        store: new ArrayTruthStore(),
        envelope: $env,
        transport: $tx,
        serializer: $ser
    );
}

//function ep_lines_from_result(array $res): array
//{
//    if (isset($res['lines']) && is_array($res['lines'])) {
//        return array_values(array_map(static fn($v) => (string) $v, $res['lines']));
//    }
//    if (isset($res['urls']) && is_array($res['urls'])) {
//        return array_values(array_map(static fn($v) => (string) $v, $res['urls']));
//    }
//    if (isset($res['chunks']) && is_array($res['chunks'])) {
//        return array_values(array_map(static fn($c) => (string)($c['text'] ?? ''), $res['chunks']));
//    }
//    throw new RuntimeException('EncodePayload result missing lines/urls/chunks');
//}
//
//function ep_make_assembler(PayloadSerializer $ser, TransportCodec $tx, Envelope $env): TruthAssembler
//{
//    return new TruthAssembler(
//        store: new ArrayTruthStore(),
//        envelope: $env,
//        transport: $tx,
//        serializer: $ser
//    );
//}
//
function ep_roundtrip(array $lines, PayloadSerializer $ser, TransportCodec $tx, Envelope $env): array
{
    shuffle($lines);
    $asm = ep_make_assembler($ser, $tx, $env);
    $classify = new Classify($asm);
    $sess = $classify->newSession();
    $sess->addLines($lines);
    expect($sess->isComplete())->toBeTrue();
    return $sess->assemble();
}

function ensureMultipart(array $payload, int $targetMinParts = 3): array {
    $size = 64;
    do {
        $res = test()->postJson('/api/encode', [
            'payload' => $payload,
            'code' => $payload['code'],
            'envelope' => 'v1url',
            'transport' => 'base64url+deflate',
            'serializer' => 'json',
            'by' => 'size',
            'size' => $size,
        ])->assertOk()->json();

        $lines = dc_extract_lines($res);
        $size = (int) max(1, $size / 2);
    } while (count($lines) < $targetMinParts && $size > 1);
    return $lines;
}
