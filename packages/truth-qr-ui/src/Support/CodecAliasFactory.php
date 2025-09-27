<?php

declare(strict_types=1);

namespace TruthQrUi\Support;

use InvalidArgumentException;

// truth-codec-php
use TruthCodec\Contracts\Envelope;
use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Contracts\TransportCodec;
use TruthCodec\Envelope\EnvelopeV1Line;
use TruthCodec\Envelope\EnvelopeV1Url;
use TruthCodec\Serializer\AutoDetectSerializer;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Serializer\SerializerRegistry;
use TruthCodec\Serializer\YamlSerializer;
use TruthCodec\Transport\Base64UrlDeflateTransport;
use TruthCodec\Transport\Base64UrlGzipTransport;
use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Transport\NoopTransport;

// truth-qr-php
use TruthQr\Contracts\TruthQrWriter;
use TruthQr\Writers\BaconQrWriter;
use TruthQr\Writers\EndroidQrWriter;
use TruthQr\Writers\NullQrWriter;

/**
 * CodecAliasFactory
 *
 * Translate short aliases into concrete collaborators for HTTP/controller surfaces.
 * Core actions remain constructor-driven; controllers can call this for user-friendly params.
 *
 * Examples:
 *  - serializers: "json", "yaml", "auto"
 *  - transports : "none", "base64url", "b64url", "base64url+deflate", "b64url+deflate", "base64url+gzip"
 *  - envelopes  : "v1line", "v1url"
 *  - writers    : "bacon(svg)", "bacon(png)", "endroid(svg)", "endroid(png)", "null(svg)"
 *                  optional tunables: bacon(svg,size=512,margin=16)
 */
final class CodecAliasFactory
{
    // ---------- Serializers ----------
    public static function makeSerializer(string $alias): PayloadSerializer
    {
        $key = self::norm($alias);
        $reg = app(SerializerRegistry::class);
        $delegates = $reg->getMany(['json','yaml']);
        if (count($delegates) === 0) {
            throw new InvalidArgumentException('Auto serializer requires at least one delegate (json/yaml).');
        }
        $preferred = $reg->get('json') ?? $delegates[0];

        return match ($key) {
            'json' => new JsonSerializer(),
            'yaml', 'yml' => new YamlSerializer(),
            'auto', 'autodetect' => new AutoDetectSerializer($delegates, $preferred),
            default => throw new InvalidArgumentException("Unknown serializer alias: {$alias}"),
        };
    }

    // ---------- Transports ----------
    public static function makeTransport(string $alias): TransportCodec
    {
        $key = self::norm($alias);
        return match ($key) {
            'none', 'identity'      => new NoopTransport(),
            'base64url', 'b64url'   => new Base64UrlTransport(),
            'base64url+deflate', 'b64url+deflate', 'deflate' => new Base64UrlDeflateTransport(),
            'base64url+gzip', 'b64url+gzip', 'gzip'          => new Base64UrlGzipTransport(),
            default => throw new InvalidArgumentException("Unknown transport alias: {$alias}"),
        };
    }

    // ---------- Envelopes ----------
    /**
     * @param string $alias  e.g. 'v1line' | 'v1url' | 'line' | 'url'
     * @param array{prefix?:string, version?:string} $opts  optional constructor overrides
     */
    public static function makeEnvelope(string $alias, array $opts = []): Envelope
    {
        $key = self::norm($alias);

        // If either override is provided, we’ll pass both (nulls coalesce below).
        $hasOverrides = array_key_exists('prefix', $opts) || array_key_exists('version', $opts);
        $prefix  = $opts['prefix']  ?? null;
        $version = $opts['version'] ?? null;

        return match ($key) {
            'v1line', 'line', 'er|v1|line' => $hasOverrides
                ? new EnvelopeV1Line($prefix, $version)   // constructor takes nulls fine → library resolves
                : new EnvelopeV1Line(),

            'v1url', 'url', 'truth://v1'   => $hasOverrides
                ? new EnvelopeV1Url($prefix, $version)
                : new EnvelopeV1Url(),

            default => throw new InvalidArgumentException("Unknown envelope alias: {$alias}"),
        };
    }

    // ---------- Writers ----------
    /**
     * @param string $spec e.g. "bacon(svg)", "endroid(png)", "null(svg)",
     *                     or with tunables: "bacon(svg,size=512,margin=16)"
     */
    public static function makeWriter(string $spec): TruthQrWriter
    {
        [$driver, $args] = self::parseSpec($spec); // ['bacon', ['svg', 'size'=>512, 'margin'=>16]]

        $fmt    = self::firstStringArg($args) ?? 'svg';
        $size   = (int)($args['size']   ?? 512);
        $margin = (int)($args['margin'] ?? 16);

        return match ($driver) {
            'null', 'noop' => new NullQrWriter($fmt),
            'bacon' => self::newBacon($fmt, $size, $margin),
            'endroid' => self::newEndroid($fmt, $size, $margin),
            default => throw new InvalidArgumentException("Unknown writer driver: {$driver}"),
        };
    }

    // ---------- Internals ----------
    private static function newBacon(string $fmt, int $size, int $margin): TruthQrWriter
    {
        if (!in_array($fmt, ['svg','png','eps'], true)) {
            throw new InvalidArgumentException("Bacon writer supports svg|png|eps (got {$fmt}).");
        }
        if (!class_exists(\BaconQrCode\Writer::class)) {
            throw new InvalidArgumentException("bacon/qr-code not installed.");
        }
        return new BaconQrWriter(fmt: $fmt, size: $size, margin: $margin);
    }

    private static function newEndroid(string $fmt, int $size, int $margin): TruthQrWriter
    {
        if (!in_array($fmt, ['svg','png'], true)) {
            throw new InvalidArgumentException("Endroid writer supports svg|png (got {$fmt}).");
        }
        if (!class_exists(\Endroid\QrCode\Builder\Builder::class)) {
            throw new InvalidArgumentException("endroid/qr-code not installed.");
        }
        return new EndroidQrWriter(fmt: $fmt, size: $size, margin: $margin);
    }

    private static function norm(string $s): string
    {
        return strtolower(trim($s));
    }

    /**
     * Parse a spec like "bacon(svg,size=512,margin=16)".
     *
     * @return array{0:string,1:array} [driver, args]
     */
    private static function parseSpec(string $spec): array
    {
        $spec = trim($spec);
        if ($spec === '') {
            throw new InvalidArgumentException('Empty writer spec.');
        }

        if (!str_contains($spec, '(')) {
            return [self::norm($spec), []];
        }

        // driver(args)
        $driver = substr($spec, 0, strpos($spec, '('));
        $inside = rtrim(substr($spec, strpos($spec, '(') + 1), ')');
        $driver = self::norm($driver);

        $args = [];
        foreach (array_filter(array_map('trim', explode(',', $inside))) as $token) {
            if (str_contains($token, '=')) {
                [$k, $v] = array_map('trim', explode('=', $token, 2));
                $args[$k] = ctype_digit($v) ? (int)$v : $v;
            } else {
                // positional string (e.g. format)
                $args[] = $token;
            }
        }

        return [$driver, $args];
    }

    /** return the first positional string argument from parseSpec() args */
    private static function firstStringArg(array $args): ?string
    {
        foreach ($args as $k => $v) {
            if (is_int($k) && is_string($v) && $v !== '') {
                return $v;
            }
        }
        return null;
    }
}
