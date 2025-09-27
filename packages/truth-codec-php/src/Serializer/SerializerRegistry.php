<?php

namespace TruthCodec\Serializer;

use TruthCodec\Contracts\PayloadSerializer;

/**
 * Registry and lookup facility for available {@see PayloadSerializer} implementations.
 *
 * This class allows serializers (e.g. JSON, YAML) to be registered and retrieved
 * by a string key, enabling dependency injection and auto-detection logic.
 *
 * Typical usage:
 *
 * ```php
 * $reg = new SerializerRegistry([
 *     'json' => new JsonSerializer(),
 *     'yaml' => new YamlSerializer(),
 * ]);
 *
 * $json = $reg->get('json');   // Returns JsonSerializer instance
 * $yaml = $reg->get('yaml');   // Returns YamlSerializer instance
 * $all  = $reg->getMany(['json','yaml']); // Array of [JsonSerializer, YamlSerializer]
 * ```
 *
 * Keys are case-insensitive; internally they are normalized to lowercase.
 */
final class SerializerRegistry
{
    /**
     * Internal map of serializer name => serializer instance.
     *
     * @var array<string,PayloadSerializer>
     */
    private array $byName = [];

    /**
     * Construct a new registry with an optional preloaded set of serializers.
     *
     * @param array<string,PayloadSerializer> $serializers Map of name => serializer instance
     */
    public function __construct(array $serializers = [])
    {
        foreach ($serializers as $name => $s) {
            $this->register($name, $s);
        }
    }

    /**
     * Register (or overwrite) a serializer under a given name.
     *
     * @param string $name Human-readable identifier (e.g. "json", "yaml").
     * @param PayloadSerializer $serializer Serializer instance.
     */
    public function register(string $name, PayloadSerializer $serializer): void
    {
        $key = strtolower($name);
        $this->byName[$key] = $serializer;
    }

    /**
     * Check if a serializer with the given name exists in the registry.
     *
     * @param string $name Name to look up (case-insensitive).
     * @return bool True if registered, false otherwise.
     */
    public function has(string $name): bool
    {
        return array_key_exists(strtolower($name), $this->byName);
    }

    /**
     * Retrieve a serializer by name.
     *
     * @param string $name Name of the serializer (case-insensitive).
     * @return PayloadSerializer The registered serializer instance.
     *
     * @throws \InvalidArgumentException If the serializer name is not registered.
     */
    public function get(string $name): PayloadSerializer
    {
        $key = strtolower($name);
        if (!isset($this->byName[$key])) {
            throw new \InvalidArgumentException("Serializer '{$name}' is not registered.");
        }
        return $this->byName[$key];
    }

    /**
     * Retrieve multiple serializers in a specific order.
     *
     * Useful for auto-detection or fallback strategies.
     *
     * @param string[] $names Ordered list of serializer names.
     * @return PayloadSerializer[] Array of serializers in the requested order.
     *
     * @throws \InvalidArgumentException If any name is not registered.
     */
    public function getMany(array $names): array
    {
        return array_map(fn($n) => $this->get($n), $names);
    }
}
