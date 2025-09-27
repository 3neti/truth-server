<?php

declare(strict_types=1);

namespace TruthQrUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use TruthQrUi\Actions\DecodePayload;
use TruthQrUi\Support\CodecAliasFactory;
use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;

/**
 * DecodeController
 *
 * Thin HTTP controller for the Playground.
 *
 * Responsibilities:
 * - Accept alias/FQCN params for serializer/transport/envelope.
 * - Accept optional envelope overrides (prefix, version).
 * - Normalize "lines" or "chunks" input into a flat string[].
 * - Call constructor-driven DecodePayload::handle() with explicit collaborators.
 */
final class DecodeController
{
    public function __invoke(ActionRequest $request)
    {
        return $this->store($request);
    }

    public function store(ActionRequest $request): JsonResponse
    {
        // --- Early shape validation so we return {"error": "..."} instead of Laravel's {"message": "..."} ---
        $linesInput  = $request->input('lines');
        $chunksInput = $request->input('chunks');

        $hasLines  = is_array($linesInput)  && !empty($linesInput);
        $hasChunks = is_array($chunksInput) && !empty($chunksInput);

        if (!$hasLines && !$hasChunks) {
            return response()->json([
                'error' => 'Provide either "lines": string[] or "chunks": [{"text": "..."}].',
            ], 422);
        }

        // Normalize to a simple string[] for the action (mirrors extractLines logic)
        $lines = $hasLines
            ? array_map(static fn($v) => (string) $v, array_values($linesInput))
            : array_map(static fn($c) => (string) ($c['text'] ?? ''), array_values($chunksInput));

        // --- Collaborators via alias or FQCN + optional prefix/version overrides ---
        $envAlias    = $request->input('envelope');
        $txAlias     = $request->input('transport');
        $serAlias    = $request->input('serializer');
        $envPrefix   = $request->string('envelope_prefix')->value();  // optional
        $envVersion  = $request->string('envelope_version')->value(); // optional

        $serFqcn = (string) $request->input('serializer_fqcn', \TruthCodec\Serializer\JsonSerializer::class);
        $txFqcn  = (string) $request->input('transport_fqcn',  \TruthCodec\Transport\Base64UrlDeflateTransport::class);
        $envFqcn = (string) $request->input('envelope_fqcn',   \TruthCodec\Envelope\EnvelopeV1Url::class);

        /** @var PayloadSerializer $serializer */
        $serializer = is_string($serAlias) && $serAlias !== ''
            ? CodecAliasFactory::makeSerializer($serAlias)
            : new $serFqcn();

        /** @var TransportCodec $transport */
        $transport = is_string($txAlias) && $txAlias !== ''
            ? CodecAliasFactory::makeTransport($txAlias)
            : new $txFqcn();

        /** @var Envelope $envelope */
        $envelope = is_string($envAlias) && $envAlias !== ''
            ? CodecAliasFactory::makeEnvelope($envAlias, [
                'prefix'  => $envPrefix,
                'version' => $envVersion,
            ])
            : new $envFqcn($envPrefix, $envVersion);

        // --- Execute action ---
        try {
            $res = app(DecodePayload::class)->handle($lines, $envelope, $transport, $serializer);
            return response()->json($res);
        } catch (\InvalidArgumentException|\JsonException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Throwable $e) {
            report($e);
            return response()->json(['error' => 'Decode failed'], 422);
        }
    }
//    public function store(ActionRequest $request): JsonResponse
//    {
//        // --- collect lines ---
//        $lines = $this->extractLines($request->input('lines'), $request->input('chunks'));
//
//        // --- Collaborators via alias or FQCN + optional prefix/version overrides ---
//        $envAlias    = $request->input('envelope');
//        $txAlias     = $request->input('transport');
//        $serAlias    = $request->input('serializer');
//        $envPrefix   = $request->string('envelope_prefix')->value();  // optional
//        $envVersion  = $request->string('envelope_version')->value(); // optional
//
//        $serFqcn = (string) $request->input('serializer_fqcn', \TruthCodec\Serializer\JsonSerializer::class);
//        $txFqcn  = (string) $request->input('transport_fqcn',  \TruthCodec\Transport\Base64UrlDeflateTransport::class);
//        $envFqcn = (string) $request->input('envelope_fqcn',   \TruthCodec\Envelope\EnvelopeV1Url::class);
//
//        /** @var PayloadSerializer $serializer */
//        $serializer = is_string($serAlias) && $serAlias !== ''
//            ? CodecAliasFactory::makeSerializer($serAlias)
//            : new $serFqcn();
//
//        /** @var TransportCodec $transport */
//        $transport = is_string($txAlias) && $txAlias !== ''
//            ? CodecAliasFactory::makeTransport($txAlias)
//            : new $txFqcn();
//
//        /** @var Envelope $envelope */
//        $envelope = is_string($envAlias) && $envAlias !== ''
//            ? CodecAliasFactory::makeEnvelope($envAlias, [
//                'prefix'  => $envPrefix,
//                'version' => $envVersion,
//            ])
//            : new $envFqcn($envPrefix, $envVersion);
//
//        // --- Execute action ---
//        try {
//            $res = app(DecodePayload::class)->handle($lines, $envelope, $transport, $serializer);
//            return response()->json($res);
//        } catch (\InvalidArgumentException|\JsonException $e) {
//            return response()->json(['error' => $e->getMessage()], 422);
//        } catch (\Throwable $e) {
//            report($e);
//            return response()->json(['error' => 'Decode failed'], 422);
//        }
//    }

    /**
     * Accept either:
     * - lines: ["ER|v1|...","truth://v1/..."]
     * - chunks: [{"text":"..."}, ...]
     *
     * @param  mixed $lines
     * @param  mixed $chunks
     * @return array<int,string>
     */
    private function extractLines(mixed $lines, mixed $chunks): array
    {
        if (is_array($lines) && !empty($lines)) {
            return array_map(static fn($v) => (string) $v, array_values($lines));
        }

        if (is_array($chunks) && !empty($chunks)) {
            return array_map(
                static fn($c) => (string) ($c['text'] ?? ''),
                array_values($chunks)
            );
        }

        abort(422, 'Provide either "lines": string[] or "chunks": [{"text": "..."}].');
    }
}
