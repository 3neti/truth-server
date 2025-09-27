<?php

use TruthCodec\Contracts\PayloadSerializer;
use TruthCodec\Serializer\AutoDetectSerializer;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Serializer\SerializerRegistry;
use TruthCodec\Serializer\YamlSerializer;

beforeEach(function () {
    // Bind concrete serializers
    app()->singleton(JsonSerializer::class, fn () => new JsonSerializer());
    app()->singleton(YamlSerializer::class, fn () => new YamlSerializer());

    // Bind a registry with named serializers
    app()->singleton(SerializerRegistry::class, function () {
        return new SerializerRegistry([
            'json' => app(JsonSerializer::class),
            'yaml' => app(YamlSerializer::class),
        ]);
    });

    // Default PayloadSerializer resolves to AutoDetect(JSON, YAML) with JSON as primary encoder
    app()->bind(PayloadSerializer::class, function () {
        /** @var SerializerRegistry $reg */
        $reg = app(SerializerRegistry::class);

        $candidates = $reg->getMany(['json', 'yaml']);
        $primary    = $reg->get('json'); // encode() should use JSON by default

        return new AutoDetectSerializer($candidates, $primary);
    });
});

it('encodes with the primary (JSON) serializer', function () {
    /** @var PayloadSerializer $s */
    $s = app(PayloadSerializer::class);

    $txt = $s->encode(['b' => 2, 'a' => 1]); // canonical JSON sorts keys
    expect($txt)->toBe('{"a":1,"b":2}');
});

it('auto-decodes JSON payloads', function () {
    /** @var PayloadSerializer $s */
    $s = app(PayloadSerializer::class);

    $arr = $s->decode('{"a":1,"b":[2,3]}');
    expect($arr)->toBe(['a' => 1, 'b' => [2,3]]);
});

it('auto-decodes YAML payloads', function () {
    /** @var PayloadSerializer $s */
    $s = app(PayloadSerializer::class);

    $yaml = <<<YML
    ---
    a: 1
    b:
      - 2
      - 3
    YML;

    $arr = $s->decode($yaml);
    expect($arr)->toBe(['a' => 1, 'b' => [2,3]]);
});

it('prefers JSON when string looks like JSON (sniffing)', function () {
    // Rebind to prove order can be changed; JSON should still be chosen by sniff.
    app()->bind(PayloadSerializer::class, function () {
        $reg = app(SerializerRegistry::class);
        $candidates = $reg->getMany(['yaml', 'json']); // reversed order
        // Keep JSON as primary for encode; not relevant to decode sniffing here
        return new AutoDetectSerializer($candidates, $reg->get('json'));
    });

    /** @var PayloadSerializer $s */
    $s = app(PayloadSerializer::class);

    // Looks like JSON (starts with '{'), so JSON is tried first by the sniffer
    $text = '{"a":1}';
    $out  = $s->decode($text);
    expect($out)->toBe(['a' => 1]);
});

it('prefers YAML when string looks like YAML (sniffing)', function () {
    // Use default binding from beforeEach()
    /** @var PayloadSerializer $s */
    $s = app(PayloadSerializer::class);

    $yaml = "---\na: 1\n";
    $out  = $s->decode($yaml);
    expect($out)->toBe(['a' => 1]);
});

it('throws a helpful error when no serializer can decode', function () {
    /** @var PayloadSerializer $s */
    $s = app(PayloadSerializer::class);

    expect(fn () => $s->decode("not: valid: [json, or: yaml"))
        ->toThrow(InvalidArgumentException::class, 'Auto-detect decode failed');
});

it('registry returns serializers by name and order', function () {
    /** @var SerializerRegistry $reg */
    $reg = app(SerializerRegistry::class);

    expect($reg->has('json'))->toBeTrue();
    expect($reg->has('yaml'))->toBeTrue();

    $many = $reg->getMany(['yaml', 'json']);
    expect($many)->toHaveCount(2);
    expect($many[0])->toBeInstanceOf(YamlSerializer::class);
    expect($many[1])->toBeInstanceOf(JsonSerializer::class);
});
