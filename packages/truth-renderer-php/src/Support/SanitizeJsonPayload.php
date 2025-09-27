<?php

namespace TruthRenderer\Support;

use ColinODell\Json5\Json5Decoder;

class SanitizeJsonPayload
{
    protected Json5Decoder $decoder;

    /**
     * Sanitize and parse a JSON or JSON5 payload string into an associative array.
     */
    public function toArray(string $raw): ?array
    {
        $clean = $this->sanitize($raw);

        try {
            return Json5Decoder::decode($clean, true);
        } catch (\Throwable $e) {
            logger()->warning('[SanitizeJsonPayload] Failed to decode JSON', [
                'message' => $e->getMessage(),
                'snippet' => mb_substr($clean, 0, 500) . (strlen($clean) > 500 ? '...' : ''),
            ]);
            return null;
        }
    }

    /**
     * Sanitize raw JSON string.
     */
    public function sanitize(string $raw): string
    {
        $clean = trim($raw);

        // Remove BOM
        $clean = preg_replace('/^\xEF\xBB\xBF/', '', $clean);

        // Normalize line endings
        $clean = str_replace(["\r\n", "\r"], "\n", $clean);

        // Replace control characters except \n, \r, \t
        $clean = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $clean);

        // Optionally decode HTML entities inside SVG
        $clean = preg_replace_callback('/"(<svg.*?<\/svg>)"/is', function ($matches) {
            $svg = html_entity_decode($matches[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
            return '"' . addslashes($svg) . '"';
        }, $clean);

        return $clean;
    }

    /**
     * Pass-through for string version (re-encodes clean JSON as pretty string)
     */
    public function toJson(string $raw): ?string
    {
        $array = $this->toArray($raw);
        return $array ? json_encode($array, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) : null;
    }
}
