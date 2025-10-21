<?php

namespace TruthElection\Pipes;

use TruthCodec\Transport\Base64UrlDeflateTransport;
use Illuminate\Support\Facades\{Log, Storage};
use TruthCodec\Serializer\JsonSerializer;
use TruthElection\Data\FinalizeErContext;
use TruthCodec\Envelope\EnvelopeV1Line;
use TruthQrUi\Actions\EncodePayload;
use TruthQr\Writers\BaconQrWriter;
use Closure;


class GenerateElectionReturnPayload
{
    public string $filename = 'election_return_payload.json';

    public array $payload;

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function handle(FinalizeErContext $ctx, Closure $next): FinalizeErContext
    {
        $this->generatePayload($ctx);
        $this->renderPayload($ctx);

        return $next($ctx);
    }

    private function generatePayload(FinalizeErContext $ctx): void
    {
        $minifiedER = $ctx->getMinifiedElectionReturn();
        $erArray = $minifiedER->toArray();
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

        $qrMeta = app(EncodePayload::class)->handle(
            payload: $erArray,
            code: $code,
            serializer: $serializer,
            transport: $transport,
            envelope: $envelope,
            writer: $writer,
            opts: $ctx->getEncodeOptions()
        );

        $payload = [
            'templateName' => 'core:precinct/er_qr/template',
            'format' => 'pdf',
            'data' => [
                'tallyMeta' => $ctx->er,
                'qrMeta' => $qrMeta,
            ],
        ];

        $this->setPayload($payload);
    }

    private function getPath(FinalizeErContext $ctx): string
    {
        return "{$ctx->folder}/{$this->filename}";
    }

    protected function renderPayload(FinalizeErContext $ctx)
    {
        Storage::disk($ctx->disk)->put(
            $this->getPath($ctx),
            json_encode($this->getPayload(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );

        Log::info("[GenerateElectionReturnPayload] PDF saved successfully.", [
            'path' => $this->getPath($ctx),
        ]);
    }
}
