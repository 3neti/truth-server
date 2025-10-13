<?php

namespace TruthElection\Pipes;

use TruthCodec\Transport\Base64UrlDeflateTransport;
use TruthCodec\Serializer\JsonSerializer;
use TruthElection\Data\FinalizeErContext;
use TruthCodec\Envelope\EnvelopeV1Line;
use Illuminate\Support\Facades\Storage;
use TruthQrUi\Actions\EncodePayload;
use Closure;

final class EncodeElectionReturnLines
{
    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $erArray = $ctx->er->toArray();

        $result = app(EncodePayload::class)->handle(
            payload: $erArray,
            code: $ctx->er->code,
            serializer: new JsonSerializer(),
            transport: new Base64UrlDeflateTransport(),
            envelope: new EnvelopeV1Line(),
            writer: null,
            opts: $ctx->getEncodeOptions()
        );

        $lines = $result['lines'] ?? [];

        Storage::disk($ctx->disk)->put(
            "{$ctx->folder}/encoded_lines.txt",
            implode(PHP_EOL, $lines)
        );

        return $next($ctx);
    }
}
