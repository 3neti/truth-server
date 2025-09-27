<?php

namespace TruthQr\Publishing;

use TruthQr\Contracts\TruthQrWriter;
use TruthQr\TruthQrPublisher;

/**
 * TruthQrPublisherFactory
 *
 * - Holds package/app-level defaults (from config)
 * - Normalizes caller options (supports 'strategy' alias for 'by')
 * - Merges defaults + per-call options
 * - Delegates to TruthQrPublisher
 */
final class TruthQrPublisherFactory
{
    public function __construct(
        private readonly TruthQrPublisher $publisher,
        private readonly array $defaults = [] // e.g. ['strategy'=>'count','count'=>2,'size'=>800]
    ) {}

    /**
     * Publish envelope URLs using merged defaults + per-call options.
     *
     * @param array<string,mixed> $payload
     * @param string              $code
     * @param array<string,mixed> $options
     *   Accepted keys:
     *     - by        : 'count' | 'size'  (alias: 'strategy')
     *     - count     : int (when by=count)
     *     - size      : int (when by=size)
     *
     * @return string[] URLs
     */
    public function publish(array $payload, string $code, array $options = []): array
    {
        $merged = $this->merge($options);

        return $this->publisher->publish($payload, $code, [
            'by'    => $merged['by'],
            'count' => $merged['count'],
            'size'  => $merged['size'],
        ]);
    }

    /**
     * Publish QR images using an explicit writer.
     *
     * @param array<string,mixed> $payload
     * @param string              $code
     * @param TruthQrWriter       $writer
     * @param array<string,mixed> $options
     * @return array<int,string> [1 => <binary>, 2 => <binary>, ...]
     */
    public function publishQrImages(
        array $payload,
        string $code,
        TruthQrWriter $writer,
        array $options = []
    ): array {
        $merged = $this->merge($options);

        return $this->publisher->publishQrImages(
            $payload,
            $code,
            $writer,
            [
                'by'    => $merged['by'],
                'count' => $merged['count'],
                'size'  => $merged['size'],
            ]
        );
    }

    /**
     * Merge config defaults with per-call options and normalize keys.
     * Allows using either 'strategy' or 'by' (both map to the same concept).
     *
     * Precedence: per-call $options override $this->defaults.
     *
     * @param  array<string,mixed> $options
     * @return array{by:string,count:int,size:int}
     */
    private function merge(array $options): array
    {
        // Normalize defaults then options, then merge (options win)
        $base = $this->normalize($this->defaults);
        $opts = $this->normalize($options);
        $merged = array_replace($base, $opts);

        // Harden final merged options with safe fallbacks
        $by = $merged['by'] ?? 'size';
        if ($by !== 'count' && $by !== 'size') {
            $by = 'size';
        }

        $count = isset($merged['count']) ? max(1, (int) $merged['count']) : 3;
        $size  = isset($merged['size'])  ? max(1, (int) $merged['size'])  : 800;

        return ['by' => $by, 'count' => $count, 'size' => $size];
    }

    /**
     * Accept both 'strategy' and 'by'. Keep only normalized keys.
     *
     * @param array<string,mixed> $opts
     * @return array{by?:string,count?:int,size?:int}
     */
    private function normalize(array $opts): array
    {
        $out = $opts;

        // Accept 'strategy' as alias for 'by'
        if (isset($out['strategy']) && !isset($out['by'])) {
            $out['by'] = $out['strategy'];
        }

        // Whitelist recognized keys
        $keep = ['by', 'count', 'size'];
        foreach (array_keys($out) as $k) {
            if (!in_array($k, $keep, true)) {
                unset($out[$k]);
            }
        }

        return $out;
    }
}
