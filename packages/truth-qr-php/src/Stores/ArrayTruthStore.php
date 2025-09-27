<?php

namespace TruthQr\Stores;

use TruthQr\Contracts\TruthStore;

/**
 * In-memory store for tests/dev.
 * Not shared across processes; discarded per PHP request.
 */
final class ArrayTruthStore implements TruthStore
{
    /** @var array<string,int> */
    private array $totals = [];

    /** @var array<string,array<int,string>> */
    private array $parts = [];

    /** @var array<string,array{content:string,content_type:string}> */
    private array $artifacts = [];

    public function __construct(private readonly int $defaultTtl = 0) {}

    public function initIfMissing(string $code, int $total, int $ttl = 0): void
    {
        if (!isset($this->totals[$code])) {
            $this->totals[$code] = $total;
            $this->parts[$code]  = [];
        }
    }

    public function putChunk(string $code, int $index, int $total, string $payloadFragment): void
    {
        if (!isset($this->totals[$code])) {
            $this->initIfMissing($code, $total, $this->defaultTtl);
        }
        // Never downgrade total, but if we had a placeholder 0 we could set it here.
        $this->totals[$code] = max($this->totals[$code], $total);
        if ($index < 1 || $index > $this->totals[$code]) {
            throw new \InvalidArgumentException("Chunk index {$index} out of range for total {$this->totals[$code]}");
        }
        $this->parts[$code][$index] = $payloadFragment;
    }

    public function getChunks(string $code): array
    {
        return $this->parts[$code] ?? [];
    }

    public function status(string $code): array
    {
        $total = $this->totals[$code] ?? 0;
        $have  = array_keys($this->parts[$code] ?? []);
        $missing = [];
        if ($total > 0) {
            for ($i = 1; $i <= $total; $i++) {
                if (!in_array($i, $have, true)) {
                    $missing[] = $i;
                }
            }
        }

        return [
            'code'     => $code,
            'total'    => $total,
            'received' => count($have),
            'missing'  => $missing,
        ];
    }

    public function isComplete(string $code): bool
    {
        $st = $this->status($code);
        return $st['total'] > 0 && $st['received'] === $st['total'];
    }

    public function setArtifact(string $code, string $content, string $contentType): void
    {
        $this->artifacts[$code] = [
            'content_type' => $contentType,
            'content'      => $content,
        ];
    }

    public function getArtifact(string $code): ?array
    {
        $a = $this->artifacts[$code] ?? null;
        if (!$a) return null;

        // Backward-compat shim (if any legacy entries exist)
        if (isset($a['mime']) || isset($a['body'])) {
            return [
                'content_type' => $a['mime']  ?? 'application/octet-stream',
                'content'      => $a['body']  ?? '',
            ];
        }

        return $a;
    }

    public function forget(string $code): void
    {
        unset($this->totals[$code], $this->parts[$code], $this->artifacts[$code]);
    }
}
