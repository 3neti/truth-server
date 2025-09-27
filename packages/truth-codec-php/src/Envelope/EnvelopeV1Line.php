<?php

namespace TruthCodec\Envelope;

use TruthCodec\Contracts\Envelope;

/**
 * V1 line (pipe-delimited) envelope:
 *   <PREFIX>|v1|<CODE>|<i>/<N>|<payload>
 *
 * How the effective prefix/version are chosen (highest → lowest):
 *  1) Runtime overrides supplied to the instance (e.g., via constructor or setters).
 *  2) Class constants (PREFIX_OVERRIDE / VERSION_OVERRIDE), if present.
 *  3) Laravel config: truth-codec.envelope.{prefix,version}.
 *  4) This class' defaults from prefix() / version().
 */
final class EnvelopeV1Line implements Envelope
{
    use EnvelopeV1Common;

    /**
     * Optional constructor to set runtime overrides explicitly (constructor-driven style).
     * If nulls are passed, normal precedence applies.
     */
    public function __construct(?string $prefix = null, ?string $version = null)
    {
        if (is_string($prefix) && $prefix !== '') {
            $this->prefixOverrideRuntime = $prefix; // from trait
        }
        if (is_string($version) && $version !== '') {
            $this->versionOverrideRuntime = $version; // from trait
        }
    }

    /** Default logical family token. */
    public function prefix(): string
    {
        return 'ER';
    }

    /** Envelope semantic version token. */
    public function version(): string
    {
        return 'v1';
    }

    /** Human-friendly transport form. */
    public function transport(): string
    {
        return 'line';
    }

    public function header(string $code, int $index, int $total, string $payloadPart): string
    {
        $this->assertIndexTotal($index, $total);

        $pfx = $this->configuredPrefix();
        $ver = $this->configuredVersion();

        return sprintf('%s|%s|%s|%d/%d|%s', $pfx, $ver, $code, $index, $total, $payloadPart);
    }

    public function parse(string $encoded): array
    {
        $parts = explode('|', $encoded, 5);
        if (count($parts) < 5) {
            throw new \InvalidArgumentException('Invalid envelope line (expected 5 segments).');
        }

        [$pfx, $ver, $code, $idxTot, $payload] = $parts;

        if ($pfx !== $this->configuredPrefix() || $ver !== $this->configuredVersion()) {
            throw new \InvalidArgumentException('Unsupported envelope prefix/version.');
        }

        if (!preg_match('~^(\d+)\/(\d+)$~', $idxTot, $m)) {
            throw new \InvalidArgumentException('Invalid i/N segment.');
        }

        $i = (int) $m[1];
        $n = (int) $m[2];
        $this->assertIndexTotal($i, $n);

        return [$code, $i, $n, $payload];
    }
}
