<?php

namespace TruthQrUi\Actions;

use TruthCodec\Contracts\{Envelope, PayloadSerializer, TransportCodec};
use Lorisleiva\Actions\Concerns\AsAction;
use Lorisleiva\Actions\ActionRequest;
use TruthQr\Contracts\TruthQrWriter;
use TruthQr\TruthQrPublisher;

/**
 * # EncodePayload
 *
 * Converts an arbitrary payload (array|string) into TRUTH envelope **lines/URLs**
 * and (optionally) **QR images**. The core path is `handle(...)`, which is
 * framework-agnostic and takes collaborators **explicitly via constructors**
 * (no reliance on container bindings).
 *
 * The HTTP path is `asController(...)`. It performs three things:
 *  1) Resolve collaborators (serializer, transport, envelope, optional writer)
 *     either from **short aliases** (preferred) or FQCNs (fallback).
 *  2) Validate & normalize request **payload/code/options** to the shape
 *     that `handle(...)` expects.
 *  3) Normalize the **response** to match the chosen envelope (uses `lines`
 *     for `EnvelopeV1Line`, `urls` for `EnvelopeV1Url`), while keeping the
 *     business logic centralized in `handle(...)`.
 *
 * ## Typical programmatic usage
 * ```php
 * $res = app(EncodePayload::class)->handle(
 *     payload:    $arrayOrJson,
 *     code:       'ER-001',
 *     serializer: new JsonSerializer(),
 *     transport:  new Base64UrlDeflateTransport(),
 *     envelope:   new EnvelopeV1Url(),       // or EnvelopeV1Line
 *     writer:     new BaconQrWriter('svg', 512, 16), // optional
 *     opts:       ['by' => 'size', 'size' => 800]     // or ['by' => 'count', 'count' => 4]
 * );
 * ```
 *
 * ## Return shape
 * ```php
 * [
 *   'code'  => string,
 *   'by'    => 'size'|'count',
 *   'lines' => string[],            // ER|v1|... or truth://... (1..N)
 *   'qr'    => array<int,string>,   // optional QR binaries (writer-dependent)
 * ]
 * ```
 */
final class EncodePayload
{
    use AsAction;

    /**
     * Core encoder (framework-agnostic).
     *
     * This is the **single source of truth** that turns a PHP payload into:
     *  - TRUTH envelope lines/URLs via TruthQrPublisher::publish()
     *  - Optional QR image binaries via TruthQrPublisher::publishQrImages()
     *
     * Everything else (validation, alias mapping, HTTP response shaping) lives
     * in helpers called by `asController(...)`.
     *
     * @param array<string,mixed>|string $payload  Either an array or a JSON string.
     * @param string                     $code     Unique document code to embed into the envelope.
     * @param PayloadSerializer          $serializer  e.g. JsonSerializer, YamlSerializer, AutoDetectSerializer
     * @param TransportCodec             $transport   e.g. Base64UrlDeflateTransport
     * @param Envelope                   $envelope    e.g. EnvelopeV1Url | EnvelopeV1Line
     * @param TruthQrWriter|null         $writer      Optional QR writer (Bacon/Endroid/Null/etc.)
     * @param array{by?:'size'|'count',size?:int,count?:int} $opts
     *        - by:    Strategy selector — 'size' (default) or 'count'
     *        - size:  Target characters per encoded fragment (when by='size')
     *        - count: Desired number of fragments (when by='count')
     *
     * @return array{
     *   code:string,
     *   by:string,
     *   lines:array<int,string>,
     *   qr?:array<int,string>
     * }
     */
    public function handle(
        array|string   $payload,
        string         $code,
        PayloadSerializer $serializer,
        TransportCodec $transport,
        Envelope       $envelope,
        ?TruthQrWriter $writer = null,
        array          $opts = []
    ): array {
        // Normalize chunking strategy
        $by    = ($opts['by'] ?? 'size') === 'count' ? 'count' : 'size';
        $size  = max(1, (int)($opts['size']  ?? 1200));
        $count = max(1, (int)($opts['count'] ?? 3));

        // Construct publisher from explicitly provided collaborators.
        $publisher = new TruthQrPublisher(
            serializer: $serializer,
            transport:  $transport,
            envelope:   $envelope,
        );

        // Serialize -> transport-encode -> envelope-encode (to lines/URLs).
        $lines = $publisher->publish(
            payload: is_array($payload) ? $payload : $this->jsonToArray($payload),
            code:    $code,
            options: $by === 'size'
                ? ['by' => 'size',  'size'  => $size]
                : ['by' => 'count', 'count' => $count]
        );

        // Optionally render QR images for each line/URL.
        $qr = null;
        if ($writer) {
            $qr = $publisher->publishQrImages(
                payload: is_array($payload) ? $payload : $this->jsonToArray($payload),
                code:    $code,
                writer:  $writer,
                options: $by === 'size'
                    ? ['by' => 'size',  'size'  => $size]
                    : ['by' => 'count', 'count' => $count]
            );
        }

        // Always return a 'lines' array; the HTTP wrapper can rename to 'urls' if needed.
        $out = [
            'code'  => $code,
            'by'    => $by,
            'lines' => array_values($lines),
        ];
        if ($qr !== null) {
            $out['qr'] = $qr; // Keep writer’s native indexing (often 0- or 1-based).
        }
        return $out;
    }

    /**
     * HTTP entrypoint.
     *
     * Thin wrapper that:
     *  1) Resolves collaborators (aliases or FQCNs) → `resolveCollaborators()`
     *  2) Validates & normalizes payload/code         → `normalizePayloadAndCode()`
     *  3) Normalizes chunking options                 → `normalizeOptions()`
     *  4) Delegates to `handle(...)`
     *  5) Shapes the response key ('lines' vs 'urls') → `shapeHttpResponse()`
     *
     * Query/body params:
     *   - serializer_fqcn: TruthCodec\Serializer\JsonSerializer
     *   - transport_fqcn:  TruthCodec\Transport\Base64UrlDeflateTransport
     *   - envelope_fqcn:   TruthCodec\Envelope\EnvelopeV1Url (or EnvelopeV1Line)
     *   - writer_fqcn:     TruthQr\Writers\BaconQrWriter (omit to skip images)
     *   - serializer (alias): 'json' | 'yaml' | 'auto'
     *   - transport  (alias): 'base64url' | 'base64url+deflate' | 'base64url+gzip'
     *   - envelope   (alias): 'v1url' | 'v1line'
     *   - by: 'size'|'count' (default 'size')
     *   - size: int         (default 1200)
     *   - count: int        (default 3)
     *   - code: string      (defaults to payload['code'] or generated)
     *   - payload: array|string (JSON)
     */
    public function asController(ActionRequest $request)
    {
        [$serializer, $transport, $envelope, $writer] = $this->resolveCollaborators($request);
        [$payload, $code] = $this->normalizePayloadAndCode($request);
        $opts = $this->normalizeOptions($request);

        $res = $this->handle(
            payload:    $payload,
            code:       $code,
            serializer: $serializer,
            transport:  $transport,
            envelope:   $envelope,
            writer:     $writer,
            opts:       $opts
        );

        return response()->json($this->shapeHttpResponse($res, $envelope, $code, $opts['by']));
    }

    /**
     * Resolve collaborators for HTTP requests.
     *
     * Preference order:
     *  - If short **aliases** are provided (`envelope`, `transport`, `serializer`),
     *    map them via CodecAliasFactory (DX-friendly).
     *  - Otherwise fall back to **FQCN** constructor parameters
     *    (`*_fqcn` inputs). No container bindings are used.
     *
     * @return array{
     *   0: PayloadSerializer,
     *   1: TransportCodec,
     *   2: Envelope,
     *   3: \TruthQr\Contracts\TruthQrWriter|null
     * }
     */
    private function resolveCollaborators(ActionRequest $request): array
    {
        // Aliases (preferred for the HTTP surface)
        $envAlias = $request->input('envelope');   // 'v1line' | 'v1url'
        $txAlias  = $request->input('transport');  // 'base64url+deflate' | ...
        $serAlias = $request->input('serializer'); // 'json' | 'yaml' | 'auto'

        // FQCN fallbacks
        $serFqcn = (string) $request->input('serializer_fqcn', \TruthCodec\Serializer\JsonSerializer::class);
        $txFqcn  = (string) $request->input('transport_fqcn',  \TruthCodec\Transport\Base64UrlDeflateTransport::class);
        $envFqcn = (string) $request->input('envelope_fqcn',   \TruthCodec\Envelope\EnvelopeV1Url::class);
        $wrFqcn  = $request->input('writer_fqcn'); // null → no images

        /** @var PayloadSerializer $serializer */
        $serializer = is_string($serAlias) && $serAlias !== ''
            ? \TruthQrUi\Support\CodecAliasFactory::makeSerializer($serAlias)
            : $this->new($serFqcn);

        /** @var TransportCodec $transport */
        $transport = is_string($txAlias) && $txAlias !== ''
            ? \TruthQrUi\Support\CodecAliasFactory::makeTransport($txAlias)
            : $this->new($txFqcn);

        /** @var Envelope $envelope */
        $envelope = is_string($envAlias) && $envAlias !== ''
            ? \TruthQrUi\Support\CodecAliasFactory::makeEnvelope($envAlias)
            : $this->new($envFqcn);

        /** @var \TruthQr\Contracts\TruthQrWriter|null $writer */
        $writer = $wrFqcn ? $this->new((string)$wrFqcn) : null;

        return [$serializer, $transport, $envelope, $writer];
    }

    /**
     * Validate & normalize the input payload and code for HTTP.
     *
     * - Ensures `payload` exists and is **either** an array **or** a JSON object string.
     * - Rejects empty arrays and non-object JSON strings.
     * - Determines the `code` (prefers the explicit input, otherwise `payload['code']`,
     *   otherwise generates a random ER-XXXXXX code).
     *
     * @return array{0:array<string,mixed>,1:string}  [payload, code]
     */
    private function normalizePayloadAndCode(ActionRequest $request): array
    {
        if (!$request->has('payload')) {
            abort(422, 'payload is required');
        }

        $payload = $request->input('payload');

        // String payloads must decode to an object/associative array.
        if (is_string($payload)) {
            try {
                $payload = $this->jsonToArray($payload);
            } catch (\Throwable) {
                abort(422, 'payload must be a valid JSON object string or an array');
            }
        }

        if (!is_array($payload) || $payload === []) {
            abort(422, 'payload must be a non-empty array or valid JSON object');
        }

        $code = (string) ($request->input('code') ?? ($payload['code'] ?? 'ER-'.bin2hex(random_bytes(3))));
        return [$payload, $code];
    }

    /**
     * Normalize chunking options for HTTP into the exact shape `handle(...)` expects.
     *
     * - Coerces `by` into either 'size' (default) or 'count'
     * - Casts `size` and `count` to integers and provides defaults
     *
     * @return array{by:'size'|'count', size:int, count:int}
     */
    private function normalizeOptions(ActionRequest $request): array
    {
        $by    = $request->input('by', 'size');
        $size  = (int) $request->input('size', 1200);
        $count = (int) $request->input('count', 3);

        return ['by' => $by === 'count' ? 'count' : 'size', 'size' => $size, 'count' => $count];
    }

    /**
     * Shape the HTTP response to match the selected envelope type:
     *  - For `EnvelopeV1Line`, the list key is `lines`
     *  - For `EnvelopeV1Url`,  the list key is `urls`
     *
     * Note: `handle(...)` always returns a `lines` array internally; this method
     * simply remaps the key for a friendlier HTTP surface.
     *
     * @param array{code:string,by:string,lines:array<int,string>,qr?:array} $res
     * @return array{code:string,by:string,lines?:array<int,string>,urls?:array<int,string>,qr?:array}
     */
    private function shapeHttpResponse(array $res, Envelope $envelope, string $code, string $by): array
    {
        $listKey = ($envelope instanceof \TruthCodec\Envelope\EnvelopeV1Line) ? 'lines' : 'urls';

        $out = [
            'code'   => $res['code'] ?? $code,
            'by'     => $res['by']   ?? $by,
            $listKey => $res['lines'] ?? [],
        ];
        if (isset($res['qr'])) {
            $out['qr'] = $res['qr'];
        }
        return $out;
    }

    /**
     * Tiny helper to instantiate collaborators by FQCN without IoC.
     *
     * @template T
     * @param class-string<T> $fqcn
     * @return T
     *
     * @throws \InvalidArgumentException if the class does not exist
     */
    private function new(string $fqcn)
    {
        if (!class_exists($fqcn)) {
            throw new \InvalidArgumentException("Class not found: {$fqcn}");
        }
        return new $fqcn();
    }

    /**
     * Strict JSON→array converter used by both `handle()` and `asController()`.
     *
     * @param string $json
     * @return array<string,mixed>
     *
     * @throws \InvalidArgumentException if not a valid JSON object (e.g., scalar or malformed)
     */
    private function jsonToArray(string $json): array
    {
        $data = json_decode($json, true);
        if (!is_array($data)) {
            throw new \InvalidArgumentException('Invalid JSON payload');
        }
        return $data;
    }
}
