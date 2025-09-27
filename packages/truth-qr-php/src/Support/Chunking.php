<?php

namespace TruthQr\Support;

final class Chunking
{
    /**
     * Split a binary string into 1-based parts by max byte size.
     *
     * @param string $blob  Raw bytes (treat as binary; not multibyte-aware)
     * @param int    $bytes Maximum bytes per part (must be > 0)
     * @return array<int,string> 1-based parts: [1 => '...', 2 => '...', ...]
     */
    public static function splitBySize(string $blob, int $bytes): array
    {
        if ($bytes <= 0) {
            throw new \InvalidArgumentException('splitBySize: $bytes must be > 0');
        }
        if ($blob === '') {
            return [];
        }

        // str_split does exactly what we want and is binary-safe for plain strings.
        $pieces = str_split($blob, $bytes);

        // Re-index as 1-based.
        $out = [];
        foreach ($pieces as $i => $part) {
            $out[$i + 1] = $part;
        }
        return $out;
    }

    /**
     * Split a binary string into ~N equal parts (last part may be shorter).
     * Uses ceil to compute per-chunk size, then defers to splitBySize.
     *
     * @param string $blob  Raw bytes
     * @param int    $parts Desired number of parts (must be > 0)
     * @return array<int,string> 1-based parts
     */
    public static function splitByCount(string $blob, int $parts): array
    {
        if ($parts <= 0) {
            throw new \InvalidArgumentException('splitByCount: $parts must be > 0');
        }
        if ($blob === '') {
            return [];
        }

        $len   = strlen($blob);
        $bytes = (int) ceil($len / $parts);

        // If ceil produced 0 (only possible with empty input), return empty.
        if ($bytes <= 0) {
            return [];
        }

        return self::splitBySize($blob, $bytes);
    }
}
