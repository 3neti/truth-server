<?php

namespace TruthQr\Assembly;

use TruthQr\Assembly\Contracts\TruthAssemblerContract;
use TruthQr\Contracts\TruthStore;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Contracts\Envelope;

/**
 * TruthAssembler
 *
 * Orchestrates ingesting TRUTH envelope lines/URLs, persisting chunk
 * fragments, and assembling the final decoded payload once complete.
 *
 * Public API (contract):
 *  - ingestLine(string $line): array{code,total,received,missing,complete}
 *  - status(string $code): array{code,total,received,missing,complete}
 *  - isComplete(string $code): bool
 *  - artifact(string $code): array{mime:string,body:string}
 *
 * Extra helper:
 *  - assemble(string $code): array<string,mixed>  (returns decoded payload)
 */
final class TruthAssembler implements TruthAssemblerContract
{
    public function __construct(
        private readonly TruthStore $store,
        private readonly Envelope $envelope,               // EnvelopeV1Line or EnvelopeV1Url
        private readonly TransportCodec $transport,        // e.g., Base64UrlTransport, GzipTransport, Identity
        private readonly PayloadSerializer $serializer     // e.g., AutoDetectSerializer
    ) {}

    /**
     * Ingest a single TRUTH envelope string (line or URL).
     *
     * @return array{
     *   code:string,
     *   total:int|null,
     *   received:int,
     *   missing:int[],
     *   complete:bool
     * }
     */
    public function ingestLine(string $line): array
    {
        // Envelope returns [code, index, total, payloadPart]
        [$code, $i, $n, $frag] = $this->envelope->parse($line);

        // Initialize & persist chunk
        $this->store->initIfMissing($code, $n);
        $this->store->putChunk($code, $i, $n, $frag);

        // Compose status with "complete"
        $s = $this->store->status($code);
        $s['complete'] = $this->store->isComplete($code);
        return $s;
    }

    /**
     * Status snapshot for a code (with "complete" flag).
     *
     * @return array{
     *   code:string,
     *   total:int|null,
     *   received:int,
     *   missing:int[],
     *   complete:bool
     * }
     */
    public function status(string $code): array
    {
        $s = $this->store->status($code);
        $s['complete'] = $this->store->isComplete($code);
        return $s;
    }

    /** True if all fragments for $code are present. */
    public function isComplete(string $code): bool
    {
        return $this->store->isComplete($code);
    }

    /**
     * Assemble the final payload for $code when complete, cache it, and return it (decoded array).
     *
     * @return array<string,mixed>
     *
     * @throws \RuntimeException if not complete yet
     * @throws \InvalidArgumentException if decode fails
     */
    public function assemble(string $code): array
    {
        if (!$this->isComplete($code)) {
            throw new \RuntimeException("Cannot assemble '{$code}': missing parts");
        }

        // Join fragments by index order
        $parts = $this->store->getChunks($code);
        ksort($parts);
        $packed = implode('', $parts);

        // transport decode (base64url/gzip/identity) → serializer decode (json/yaml)
        $blob = $this->transport->decode($packed);
        $payload = $this->serializer->decode($blob);

        // Cache serialized artifact as-is (blob keeps chosen format)
        $this->store->setArtifact(
            code: $code,
            content: $blob,
            contentType: $this->mimeFor($this->serializer->format())
        );

        return $payload;
    }

    /**
     * Return a ready-to-serve artifact for $code.
     * If the set is complete but not yet cached, assemble and cache now.
     *
     * @return array{content_type:string, content:string}|null
     *
     * @throws \RuntimeException if the set is not complete
     */
    public function artifact(string $code): array
    {
        if (!$this->isComplete($code)) {
            throw new \RuntimeException('Artifact not ready');
        }

        // If already cached, return it
        $cached = $this->store->getArtifact($code);
        if (is_array($cached) && isset($cached['content'], $cached['content_type'])) {
            return ['mime' => $cached['content_type'], 'body' => $cached['content']];
        }

        // Otherwise, assemble (this will cache), then read back
        $this->assemble($code);

        $cached = $this->store->getArtifact($code);
        if (!is_array($cached) || !isset($cached['content'], $cached['content_type'])) {
            throw new \RuntimeException('Artifact cache missing after assemble');
        }

        return ['mime' => $cached['content_type'], 'body' => $cached['content']];
    }

    /** Map serializer format → MIME. */
    private function mimeFor(string $format): string
    {
        return match (strtolower($format)) {
            'json'        => 'application/json',
            'yaml', 'yml' => 'application/yaml',
            default       => 'text/plain',
        };
    }
}
