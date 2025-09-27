<?php

namespace TruthQr\Stores;

use Illuminate\Support\Facades\Redis;
use TruthQr\Contracts\TruthStore;

/**
 * Redis-backed store.
 *
 * Keys:
 *  - {prefix}{code}:total              (int)
 *  - {prefix}{code}:part:{i}          (string)
 *  - {prefix}{code}:artifact          (string)
 *  - {prefix}{code}:artifact_ct       (string)
 */
final class RedisTruthStore implements TruthStore
{
    public function __construct(
        private readonly string $keyPrefix = 'truth:qr:',
        private readonly int $defaultTtl = 86400, // 24h
        private readonly ?string $connection = null // null = default
    ) {}

    private function r()
    {
        return $this->connection ? Redis::connection($this->connection) : Redis::connection();
    }

    private function k(string $code, string $suffix): string
    {
        return $this->keyPrefix . $code . ':' . $suffix;
    }

    public function initIfMissing(string $code, int $total, int $ttl = 0): void
    {
        $ttl = $ttl > 0 ? $ttl : $this->defaultTtl;
        $key = $this->k($code, 'total');
        if (!$this->r()->exists($key)) {
            $this->r()->set($key, (string) $total, 'EX', $ttl);
        }
    }

    public function putChunk(string $code, int $index, int $total, string $payloadFragment): void
    {
        $ttl = $this->defaultTtl;
        $this->initIfMissing($code, $total, $ttl);

        $totalKey = $this->k($code, 'total');
        $storedTotal = (int) ($this->r()->get($totalKey) ?? 0);
        if ($storedTotal === 0) {
            $this->r()->set($totalKey, (string) $total, 'EX', $ttl);
            $storedTotal = $total;
        } else {
            // ensure consistent total
            if ($storedTotal !== $total) {
                throw new \InvalidArgumentException("Chunks disagree on total for {$code} ({$storedTotal} vs {$total})");
            }
        }

        if ($index < 1 || $index > $storedTotal) {
            throw new \InvalidArgumentException("Chunk index {$index} out of range for total {$storedTotal}");
        }

        $partKey = $this->k($code, "part:{$index}");
        $this->r()->set($partKey, $payloadFragment, 'EX', $ttl);
    }

    public function getChunks(string $code): array
    {
        $total = (int) ($this->r()->get($this->k($code, 'total')) ?? 0);
        if ($total <= 0) return [];

        $out = [];
        for ($i = 1; $i <= $total; $i++) {
            $val = $this->r()->get($this->k($code, "part:{$i}"));
            if (is_string($val)) {
                $out[$i] = $val;
            }
        }
        return $out;
    }

    public function status(string $code): array
    {
        $total = (int) ($this->r()->get($this->k($code, 'total')) ?? 0);
        $have = array_keys($this->getChunks($code));

        $missing = [];
        if ($total > 0) {
            for ($i = 1; $i <= $total; $i++) {
                if (!in_array($i, $have, true)) $missing[] = $i;
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
        $ttl = $this->defaultTtl;
        $this->r()->set($this->k($code, 'artifact'), $content, 'EX', $ttl);
        $this->r()->set($this->k($code, 'artifact_ct'), $contentType, 'EX', $ttl);
    }

    public function getArtifact(string $code): ?array
    {
        $c  = $this->r()->get($this->k($code, 'artifact'));
        $ct = $this->r()->get($this->k($code, 'artifact_ct'));
        if (!is_string($c) || !is_string($ct)) return null;
        return ['content' => $c, 'content_type' => $ct];
    }

    public function forget(string $code): void
    {
        $total = (int) ($this->r()->get($this->k($code, 'total')) ?? 0);
        $keys = [$this->k($code, 'total'), $this->k($code, 'artifact'), $this->k($code, 'artifact_ct')];
        if ($total > 0) {
            for ($i = 1; $i <= $total; $i++) {
                $keys[] = $this->k($code, "part:{$i}");
            }
        }
        if (!empty($keys)) {
            $this->r()->del($keys);
        }
    }
}
