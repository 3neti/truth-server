<?php

namespace TruthCodec\Envelope;

use TruthCodec\Contracts\Envelope;

/**
 * URL-based V1 envelope.
 *
 * Supported forms:
 *  • Deep link: truth://v1/<prefix>/<code>/<i>/<n>?c=<payload>
 *  • Web link : https://host/path?truth=v1&prefix=...&code=...&i=...&n=...&c=...
 *
 * Effective prefix/version precedence (highest → lowest):
 *  1) Runtime overrides supplied to the instance (e.g., via constructor or setters).
 *  2) Class constants (PREFIX_OVERRIDE / VERSION_OVERRIDE), if present.
 *  3) Laravel config: truth-codec.envelope.{prefix,version}.
 *  4) This class' defaults from prefix() / version().
 *
 * URL behavior is further controlled by config (truth-codec.url.*):
 *  - scheme        : deep-link scheme (default "truth")
 *  - web_base      : when non-null, emit https URL instead of deep link
 *  - payload_param : query key for the payload (default "c")
 *  - version_param : fallback query key for version when "truth" is not used (default "v")
 */
final class EnvelopeV1Url implements Envelope
{
    use EnvelopeV1Common;

    /**
     * Optional constructor to set runtime overrides explicitly (constructor-driven style).
     * If nulls are passed, normal precedence applies.
     */
    public function __construct(?string $prefix = null, ?string $version = null)
    {
        if (is_string($prefix) && $prefix !== '') {
            $this->prefixOverrideRuntime = $prefix;
        }
        if (is_string($version) && $version !== '') {
            $this->versionOverrideRuntime = $version;
        }
    }

    public function prefix(): string { return 'ER'; }
    public function version(): string { return 'v1'; }
    public function transport(): string { return 'url'; }

    public function header(string $code, int $index, int $total, string $payloadPart): string
    {
        $this->assertIndexTotal($index, $total);

        $pfx = $this->configuredPrefix();
        $ver = $this->configuredVersion();

        $scheme       = $this->cfg('url.scheme', 'truth');
        $webBase      = $this->cfg('url.web_base', null);
        $payloadParam = $this->cfg('url.payload_param', 'c');

        if ($webBase === null) {
            // truth://v1/<prefix>/<code>/<i>/<n>?c=payload
            $path = sprintf(
                '%s/%s/%s/%d/%d',
                rawurlencode($ver),
                rawurlencode($pfx),
                rawurlencode($code),
                $index,
                $total
            );
            $qs = http_build_query([$payloadParam => $payloadPart], '', '&', PHP_QUERY_RFC3986);
            return sprintf('%s://%s?%s', $scheme, $path, $qs);
        }

        // https://…?truth=v1&prefix=ER&code=…&i=…&n=…&c=…
        $q = [
            'truth'       => $ver,
            'prefix'      => $pfx,
            'code'        => $code,
            'i'           => $index,
            'n'           => $total,
            $payloadParam => $payloadPart,
        ];
        $qs = http_build_query($q, '', '&', PHP_QUERY_RFC3986);
        return rtrim($webBase, '/') . '?' . $qs;
    }

    public function parse(string $encoded): array
    {
        $pfx = $this->configuredPrefix();
        $ver = $this->configuredVersion();

        $scheme       = $this->cfg('url.scheme', 'truth');
        $payloadParam = $this->cfg('url.payload_param', 'c');
        $versionParam = $this->cfg('url.version_param', 'v');

        // Deep-link: truth://…
        if (str_starts_with($encoded, $scheme . '://')) {
            $u = parse_url($encoded);
            if (!$u || !isset($u['host'])) {
                throw new \InvalidArgumentException('Invalid deep-link URL.');
            }

            $path = $u['host'] . ($u['path'] ?? '');
            $parts = array_values(array_filter(explode('/', $path), 'strlen'));
            if (count($parts) < 5) {
                throw new \InvalidArgumentException('Invalid deep-link path segments.');
            }

            [$v, $p, $code, $i, $n] = $parts;

            if ($v !== $ver)    throw new \InvalidArgumentException('Envelope version mismatch.');
            if ($p !== $pfx)    throw new \InvalidArgumentException('Envelope prefix mismatch.');

            if (!isset($u['query'])) {
                throw new \InvalidArgumentException('Missing deep-link query.');
            }
            parse_str($u['query'], $q);

            $payload = $q[$payloadParam] ?? null;
            if (!is_string($payload)) {
                throw new \InvalidArgumentException('Missing payload segment.');
            }

            $i = (int) $i;
            $n = (int) $n;
            $this->assertIndexTotal($i, $n);

            return [$code, $i, $n, $payload];
        }

        // Web URL: http(s)://…
        if (str_starts_with($encoded, 'http://') || str_starts_with($encoded, 'https://')) {
            $u = parse_url($encoded);
            if (!$u || !isset($u['query'])) {
                throw new \InvalidArgumentException('Invalid web URL envelope.');
            }
            parse_str($u['query'], $q);

            $v = $q['truth'] ?? $q[$versionParam] ?? null;
            $c = $q['code'] ?? null;
            $i = isset($q['i']) ? (int) $q['i'] : null;
            $n = isset($q['n']) ? (int) $q['n'] : null;
            $p = $q['prefix'] ?? null;
            $payload = $q[$payloadParam] ?? null;

            if ($v !== $ver || $p !== $pfx || !is_string($c) || !is_string($payload) || !is_int($i) || !is_int($n)) {
                throw new \InvalidArgumentException('Missing/invalid URL envelope params.');
            }

            $this->assertIndexTotal($i, $n);
            return [$c, $i, $n, $payload];
        }

        throw new \InvalidArgumentException('Unsupported envelope string (not a recognized URL).');
    }

    /** Convenience config reader under the package namespace. */
    private function cfg(string $key, $default = null)
    {
        if (function_exists('config')) {
            return config("truth-codec.$key", $default);
        }
        return $default;
    }
}
