<?php

declare(strict_types=1);

namespace TruthElection\Pipes;

use Closure;
use Illuminate\Support\Facades\Storage;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;
use TruthCodec\Envelope\EnvelopeV1Line;
use TruthElection\Data\FinalizeErContext;
use TruthQrUi\Actions\EncodePayload;

final class GenerateElectionReturnQRCodes
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $erArray = $ctx->er->toArray();
        $code = $ctx->er->code;

        // Collaborators
        $serializer = new JsonSerializer();
        $transport  = new Base64UrlDeflateTransport();
        $envelope   = new EnvelopeV1Line();

        // QR Writer – using modern constructor signature (fmt, size, margin)
        $writerFqcn = \TruthQr\Writers\BaconQrWriter::class;
        $writerFmt = 'svg';
        $writerSize = 512;
        $writerMargin = 16;

        $writer = new $writerFqcn(
            fmt: $writerFmt,
            size: $writerSize,
            margin: $writerMargin
        );

        // Encode payload with QR
        $result = app(EncodePayload::class)->handle(
            payload: $erArray,
            code: $code,
            serializer: $serializer,
            transport: $transport,
            envelope: $envelope,
            writer: $writer,
            opts: ['by' => 'size', 'size' => $ctx->maxChars]
        );

        $qrImages = $result['qr'] ?? [];

        // Persist to storage
        foreach ($qrImages as $i => $qr) {
            $filename = "{$ctx->folder}/qr_part_" . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . ".{$writerFmt}";
            Storage::disk($ctx->disk)->put($filename, $qr);
        }

        return $next($ctx);
    }
}
