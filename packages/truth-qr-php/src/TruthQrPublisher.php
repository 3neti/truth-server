<?php

namespace TruthQr;

use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthQr\Contracts\TruthQrWriter;
use TruthCodec\Contracts\Envelope;
use TruthQr\Support\Chunking;

final class TruthQrPublisher
{
    public function __construct(
        private readonly PayloadSerializer $serializer,
        private readonly TransportCodec $transport,
        private readonly Envelope $envelope
    ) {}

    /**
     * @param array<string,mixed> $payload
     * @param array{by:'count'|'size',count:int,size:int} $options
     * @return string[] URLs (or lines)
     */
    public function publish(array $payload, string $code, array $options = []): array
    {
        // Do not impose separate defaults here; factory already merged them.
        $by    = $options['by']    ?? 'size';
        $count = (int)($options['count'] ?? 3);
        $size  = (int)($options['size']  ?? 800);

        $blob   = $this->serializer->encode($payload);
        $packed = $this->transport->encode($blob);

        $parts = $by === 'count'
            ? Chunking::splitByCount($packed, max(1, $count))
            : Chunking::splitBySize($packed, max(1, $size));

        $n = count($parts);
        $urls = [];
        foreach ($parts as $i => $part) { // $i is 1-based from Chunking
            $urls[] = $this->envelope->header($code, $i, $n, $part);
        }
        return $urls;
    }

    /**
     * Convenience: publish lines/URLs and immediately render QR images using the given writer.
     *
     * @param array<string,mixed> $payload
     * @param string $code
     * @param TruthQrWriter $writer
     * @param array{
     *   by?: 'size'|'count',
     *   size?: int,
     *   count?: int
     * } $options
     *
     * @return array<int,string> keyed the same as lines; writer-dependent binary/strings
     */
    public function publishQrImages(array $payload, string $code, TruthQrWriter $writer, array $options = []): array
    {
        $lines = $this->publish($payload, $code, $options);
        return $writer->write($lines);
    }
}
