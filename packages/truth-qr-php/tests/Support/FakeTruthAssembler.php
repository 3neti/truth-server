<?php
// packages/truth-qr-php/tests/Support/FakeTruthAssembler.php

namespace TruthQr\Tests\Support;

use TruthQr\Assembly\Contracts\TruthAssemblerContract;

/**
 * Minimal in-memory fake for feature tests.
 * - Parses only pipe lines: PREFIX|v1|CODE|i/N|payload
 * - “Completes” when we’ve seen all indices [1..N]
 * - Artifact is a small JSON stub when complete.
 */
final class FakeTruthAssembler implements TruthAssemblerContract
{
    /** @var array<string, array{total:int|null, parts:array<int,true>}> */
    private array $byCode = [];

    public function ingestLine(string $line): array
    {
        // split fast: PREFIX|v1|CODE|i/N|payload
        $parts = explode('|', $line, 5);
        if (count($parts) < 5) {
            throw new \InvalidArgumentException('Invalid line');
        }
        [$pfx, $ver, $code, $idxTot] = $parts;

        if (!preg_match('~^(\d+)\/(\d+)$~', $idxTot, $m)) {
            throw new \InvalidArgumentException('Invalid i/N');
        }
        $i = (int)$m[1];
        $n = (int)$m[2];

        if (!isset($this->byCode[$code])) {
            $this->byCode[$code] = ['total' => $n, 'parts' => []];
        } else {
            // lock total once set
            if ($this->byCode[$code]['total'] !== null && $this->byCode[$code]['total'] !== $n) {
                throw new \InvalidArgumentException('Total mismatch for code');
            }
            $this->byCode[$code]['total'] = $n;
        }

        if ($i < 1 || $i > $n) {
            throw new \InvalidArgumentException('Index out of range');
        }

        $this->byCode[$code]['parts'][$i] = true;

        return $this->status($code);
    }

    public function status(string $code): array
    {
        $state = $this->byCode[$code] ?? ['total' => null, 'parts' => []];
        $total = $state['total'];
        $parts = $state['parts'];

        $received = count($parts);
        $missing = [];

        if ($total !== null) {
            for ($k = 1; $k <= $total; $k++) {
                if (!isset($parts[$k])) $missing[] = $k;
            }
        }

        return [
            'code'     => $code,
            'total'    => $total,
            'received' => $received,
            'missing'  => $missing,
            'complete' => $total !== null && $received === $total,
        ];
    }

    public function isComplete(string $code): bool
    {
        $s = $this->status($code);
        return $s['complete'] === true;
    }

    public function artifact(string $code): array
    {
        if (!$this->isComplete($code)) {
            throw new \RuntimeException('Artifact not ready');
        }

        // simple JSON stub
        $body = json_encode(['ok' => true, 'code' => $code], JSON_UNESCAPED_SLASHES);
        if ($body === false) {
            throw new \RuntimeException('JSON error');
        }

        return ['mime' => 'application/json', 'body' => $body];
    }
}
