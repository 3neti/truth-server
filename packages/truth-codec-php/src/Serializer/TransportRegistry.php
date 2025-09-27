<?php

namespace TruthCodec\Serializer;

use TruthCodec\Contracts\TransportCodec;

/**
 * Registry for transport codecs (e.g. none, base64url, base64url+gzip).
 */
final class TransportRegistry
{
    /** @var array<string,TransportCodec> */
    private array $byName = [];

    /**
     * @param array<string,TransportCodec> $codecs initial map: name => codec
     */
    public function __construct(array $codecs = [])
    {
        foreach ($codecs as $name => $codec) {
            $this->register($name, $codec);
        }
    }

    public function register(string $name, TransportCodec $codec): void
    {
        $key = strtolower($name);
        $this->byName[$key] = $codec;
    }

    public function has(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->byName);
    }

    public function get(string $name): TransportCodec
    {
        $key = strtolower($name);
        if (!isset($this->byName[$key])) {
            throw new \InvalidArgumentException("Transport '{$name}' is not registered.");
        }
        return $this->byName[$key];
    }
}
