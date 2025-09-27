<?php

declare(strict_types=1);

namespace TruthQrUi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Lorisleiva\Actions\ActionRequest;
use TruthQrUi\Actions\EncodePayload;
use TruthQrUi\Support\CodecAliasFactory;
use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthQr\Contracts\TruthQrWriter;

/**
 * EncodeController
 *
 * Thin HTTP controller for the Playground.
 *
 * Responsibilities:
 * - Accept friendly alias params (serializer/transport/envelope + writer)
 *   and optional envelope overrides (prefix, version).
 * - Optionally accept FQCNs as a fallback (serializer_fqcn, transport_fqcn, envelope_fqcn, writer_fqcn).
 * - Normalize payload, code and chunking options.
 * - Call the constructor-driven EncodePayload::handle() with explicit collaborators.
 * - Shape the list key to 'urls' (for URL envelope) or 'lines' (for line envelope).
 */
final class EncodeController
{
    public function __invoke(ActionRequest $request)
    {
        return $this->store($request);
    }

    public function store(ActionRequest $request): JsonResponse
    {
        // --- Collaborators via alias (preferred) or FQCN (fallback) ---
        $envAlias = $request->input('envelope');   // e.g., v1url | v1line
        $txAlias  = $request->input('transport');  // e.g., base64url+deflate
        $serAlias = $request->input('serializer'); // e.g., json | yaml | auto
        $wSpec    = $request->input('writer');     // e.g., bacon(svg,size=256) | endroid(png)

        $envPrefix  = $request->string('envelope_prefix')->value();  // optional
        $envVersion = $request->string('envelope_version')->value(); // optional

        $serFqcn = (string) $request->input('serializer_fqcn', \TruthCodec\Serializer\JsonSerializer::class);
        $txFqcn  = (string) $request->input('transport_fqcn',  \TruthCodec\Transport\Base64UrlDeflateTransport::class);
        $envFqcn = (string) $request->input('envelope_fqcn',   \TruthCodec\Envelope\EnvelopeV1Url::class);
        $wrFqcn  = $request->input('writer_fqcn'); // null → no images

        /** @var PayloadSerializer $serializer */
        $serializer = is_string($serAlias) && $serAlias !== ''
            ? CodecAliasFactory::makeSerializer($serAlias)
            : new $serFqcn();

        /** @var TransportCodec $transport */
        $transport = is_string($txAlias) && $txAlias !== ''
            ? CodecAliasFactory::makeTransport($txAlias)
            : new $txFqcn();

        // Envelope: alias path supports prefix/version overrides directly
        /** @var Envelope $envelope */
        $envelope = is_string($envAlias) && $envAlias !== ''
            ? CodecAliasFactory::makeEnvelope($envAlias, [
                'prefix'  => $envPrefix,
                'version' => $envVersion,
            ])
            : new $envFqcn($envPrefix, $envVersion); // FQCN path: our Envelope classes accept ctor overrides

        // --- QR writer controls (optional) ---
        $includeQr    = filter_var($request->input('include_qr', false), FILTER_VALIDATE_BOOL);
        $writerSpec   = $request->string('writer')->trim()->value();         // e.g. "bacon(svg,size=256,margin=8)"
        $writerFqcn   = $request->string('writer_fqcn')->trim()->value();    // optional FQCN fallback
        $writerFmt    = $request->string('writer_fmt')->trim()->value() ?: 'svg';
        $writerSize   = (int) $request->input('writer_size', 256);
        $writerMargin = (int) $request->input('writer_margin', 16);

        $writer = null;

        if ($includeQr) {
            try {
                if ($writerSpec !== '') {
                    // Preferred: alias spec via factory
                    $writer = \TruthQrUi\Support\CodecAliasFactory::makeWriter($writerSpec);
                } elseif ($writerFqcn !== '') {
                    // Fallback: instantiate FQCN (be tolerant to ctor signatures)
                    if (!class_exists($writerFqcn)) {
                        return response()->json(['error' => "Writer class not found: {$writerFqcn}"], 422);
                    }

                    // Try common modern ctor: __construct(fmt, size, margin)
                    try {
                        $writer = new $writerFqcn(fmt: $writerFmt, size: $writerSize, margin: $writerMargin);
                    } catch (\ArgumentCountError|\TypeError) {
                        // Fallback: no-arg + fluent setters if present
                        $tmp = new $writerFqcn();
                        if (method_exists($tmp, 'withFormat'))  { $tmp = $tmp->withFormat($writerFmt); }
                        if (method_exists($tmp, 'withSize'))    { $tmp = $tmp->withSize($writerSize); }
                        if (method_exists($tmp, 'withMargin'))  { $tmp = $tmp->withMargin($writerMargin); }
                        $writer = $tmp;
                    }
                }
                // else: include_qr is true but no writer specified → leave $writer = null (action will skip QR)
            } catch (\InvalidArgumentException $e) {
                return response()->json(['error' => $e->getMessage()], 422);
            } catch (\Throwable $e) {
                report($e);
                return response()->json(['error' => 'Invalid writer configuration'], 422);
            }
        }
//        /** @var TruthQrWriter|null $writer */
//        $writer = null;
//        if (is_string($wSpec) && $wSpec !== '') {
//            // Human-friendly writer spec, e.g. "bacon(svg,size=256)"
//            $writer = CodecAliasFactory::makeWriter($wSpec);
//        } elseif (is_string($wrFqcn) && $wrFqcn !== '') {
//            // FQCN writer: instantiate with defaults (format/size/margin are class defaults)
//            $writer = new $wrFqcn();
//        }

        // --- Options ---
        $by    = $request->input('by', 'size');
        $size  = (int) $request->input('size', 1200);
        $count = (int) $request->input('count', 3);
        $opts  = ['by' => $by === 'count' ? 'count' : 'size', 'size' => $size, 'count' => $count];

        // --- Payload + code validation (non-empty array or JSON object) ---
        if (!$request->has('payload')) {
            return response()->json(['error' => 'payload is required'], 422);
        }
        $payload = $request->input('payload');
        if (is_string($payload)) {
            try {
                $payload = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                return response()->json(['error' => 'payload must be a valid JSON object string or an array'], 422);
            }
        }
        if (!is_array($payload) || $payload === []) {
            return response()->json(['error' => 'payload must be a non-empty array or valid JSON object'], 422);
        }
        $code = (string) ($request->input('code') ?? ($payload['code'] ?? 'ER-'.bin2hex(random_bytes(3))));

        // --- Execute action (explicit collaborators) ---
        $res = app(EncodePayload::class)->handle(
            payload:    $payload,
            code:       $code,
            serializer: $serializer,
            transport:  $transport,
            envelope:   $envelope,
            writer:     $writer,
            opts:       $opts
        );

        // --- Shape HTTP response list key based on envelope transport ---
        $listKey = $envelope->transport() === 'line' ? 'lines' : 'urls';

        $out = [
            'code'   => $res['code'] ?? $code,
            'by'     => $res['by']   ?? $opts['by'],
            $listKey => $res['lines'] ?? [],
        ];

        if (isset($res['qr']) && is_array($res['qr'])) {
            $out['qr'] = $this->normalizeQrPayloads($res['qr']);
        }

        return response()->json($out);
    }

    /**
     * Normalize QR binaries for JSON transport:
     *  - Leave SVG strings as-is (<?xml ...>).
     *  - Convert binary PNGs (and any non-UTF8) to data URLs.
     *
     * @param  array<int,string> $qr
     * @return array<int,string>
     */
    private function normalizeQrPayloads(array $qr): array
    {
        foreach ($qr as $k => $v) {
            if (!is_string($v)) {
                unset($qr[$k]);
                continue;
            }

            // SVGs are already UTF-8 text
            if (str_starts_with($v, '<?xml')) {
                continue;
            }

            // Detect PNG signature: \x89PNG\r\n\x1a\n
            $isPng = isset($v[0]) && substr($v, 0, 8) === "\x89PNG\r\n\x1a\n";
            if ($isPng) {
                $qr[$k] = 'data:image/png;base64,' . base64_encode($v);
                continue;
            }

            // Anything not valid UTF-8 → generic base64 data URL
            if (!mb_check_encoding($v, 'UTF-8')) {
                $qr[$k] = 'data:application/octet-stream;base64,' . base64_encode($v);
            }
        }

        // ensure 0-based sequential array for predictable clients
        return array_values($qr);
    }
}
