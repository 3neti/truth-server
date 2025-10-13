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

        // QR Writer – using DTO configuration
        $writerFqcn = \TruthQr\Writers\BaconQrWriter::class;

        $writer = new $writerFqcn(
            fmt: $ctx->qrWriterFormat,
            size: $ctx->qrWriterSize,
            margin: $ctx->qrWriterMargin
        );

        // Encode payload with QR
        $result = app(EncodePayload::class)->handle(
            payload: $erArray,
            code: $code,
            serializer: $serializer,
            transport: $transport,
            envelope: $envelope,
            writer: $writer,
            opts: $ctx->getEncodeOptions()
        );

        $qrImages = $result['qr'] ?? [];

        // Persist to storage
        foreach ($qrImages as $i => $qr) {
            $filename = "{$ctx->folder}/qr_part_" . str_pad((string)($i + 1), 2, '0', STR_PAD_LEFT) . ".{$ctx->qrWriterFormat}";
            Storage::disk($ctx->disk)->put($filename, $qr);
        }

        return $next($ctx);
    }
}
