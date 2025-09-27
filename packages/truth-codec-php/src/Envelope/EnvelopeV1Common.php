<?php

namespace TruthCodec\Envelope;

/**
 * Shared config and guards for Envelope v1.
 *
 * Precedence for resolving the effective prefix and version (highest → lowest):
 *  1) Runtime overrides supplied to the concrete implementation (e.g., via constructor or setters).
 *  2) Runtime override via class constants: PREFIX_OVERRIDE / VERSION_OVERRIDE (if defined).
 *  3) Laravel config keys: truth-codec.envelope.prefix / truth-codec.envelope.version.
 *  4) Implementation defaults returned by prefix() / version().
 *
 * Concrete envelopes must implement transport(): "line" or "url".
 */
trait EnvelopeV1Common
{
    /** @var string|null Runtime override set at construction or via fluent setter */
    private ?string $prefixOverrideRuntime = null;

    /** @var string|null Runtime override set at construction or via fluent setter */
    private ?string $versionOverrideRuntime = null;

    /** Fluent, immutable override for the logical family token (e.g., "ER"). */
    public function withPrefix(string $prefix): static
    {
        $clone = clone $this;
        $clone->prefixOverrideRuntime = $prefix;
        return $clone;
    }

    /** Fluent, immutable override for the semantic version (e.g., "v1"). */
    public function withVersion(string $version): static
    {
        $clone = clone $this;
        $clone->versionOverrideRuntime = $version;
        return $clone;
    }

    /** Resolve effective prefix with full precedence. */
    protected function configuredPrefix(): string
    {
        // (1) runtime override
        if (is_string($this->prefixOverrideRuntime) && $this->prefixOverrideRuntime !== '') {
            return $this->prefixOverrideRuntime;
        }

        // (2) class constant override
        $const = static::class.'::PREFIX_OVERRIDE';
        $override = \defined($const) ? \constant($const) : null;
        if (is_string($override) && $override !== '') {
            return $override;
        }

        // (3) app config
        if (function_exists('config')) {
            $cfg = config('truth-codec.envelope.prefix');
            if (is_string($cfg) && $cfg !== '') {
                return $cfg;
            }
        }

        // (4) default from implementation
        return $this->prefix();
    }

    /** Resolve effective version with full precedence. */
    protected function configuredVersion(): string
    {
        // (1) runtime override
        if (is_string($this->versionOverrideRuntime) && $this->versionOverrideRuntime !== '') {
            return $this->versionOverrideRuntime;
        }

        // (2) class constant override
        $const = static::class.'::VERSION_OVERRIDE';
        $override = \defined($const) ? \constant($const) : null;
        if (is_string($override) && $override !== '') {
            return $override;
        }

        // (3) app config
        if (function_exists('config')) {
            $cfg = config('truth-codec.envelope.version');
            if (is_string($cfg) && $cfg !== '') {
                return $cfg;
            }
        }

        // (4) default from implementation
        return $this->version();
    }

    /** Guard for 1 ≤ index ≤ total and total ≥ 1 */
    protected function assertIndexTotal(int $index, int $total): void
    {
        if ($index < 1 || $total < 1 || $index > $total) {
            throw new \InvalidArgumentException("Invalid chunk index/total: {$index}/{$total}");
        }
    }
}
