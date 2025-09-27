# Truth Codec (PHP)

> 🔑 Deterministic encoding/decoding for election returns, ballots, canvasses, or any domain DTOs — chunked into transport-safe envelopes (line or URL) for QR codes and constrained mediums.

---

## Features

- **Deterministic Serialization**
    - Canonical JSON (sorted keys, stable encoding).
    - YAML support via Symfony YAML.
    - Auto-detect decoding between JSON/YAML.

- **Envelope v1 (Pluggable Formats)**
    - `EnvelopeV1Line`: traditional `ER|v1|<code>|i/N|<payload>` format.
    - `EnvelopeV1Url`: deep-link (`truth://v1/...`) or web URL (`https://...?...`) transport.

- **Chunk Encode/Decode**
    - Split serialized payloads into fixed-size fragments (safe for QR codes).
    - Collect, validate, and reassemble chunks into the original payload.
    - Strong guards: mismatched codes, totals, duplicates, or corruption trigger exceptions.

- **Laravel Integration**
    - Service provider wires serializers & registry.
    - `PayloadSerializer` resolves to `AutoDetectSerializer` (JSON, YAML).
    - Envelope prefix/version configurable via `config/truth-codec.php`.

---

## Installation

```bash
composer require your-vendor/truth-codec
```

---

## Usage

### Encode Payload to Chunks

```php
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Transport\Base64UrlTransport;

$payload = [
    'type' => 'ER',
    'code' => 'XYZ',
    'data' => ['hello' => 'world'],
];

$encoder = new ChunkEncoder(new JsonSerializer(), new Base64UrlTransport());
$chunks = $encoder->encodeToChunks($payload, 'XYZ', 400);

// $chunks[0] = "ER|v1|XYZ|1/2|..."
```

### Decode and Assemble

```php
use TruthCodec\Decode\ChunkDecoder;
use TruthCodec\Decode\ChunkAssembler;
use TruthCodec\Serializer\AutoDetectSerializer;
use TruthCodec\Transport\Base64UrlTransport;

$decoder   = new ChunkDecoder();
$assembler = new ChunkAssembler(new AutoDetectSerializer([...]), new Base64UrlTransport());

foreach ($chunks as $line) {
    $assembler->add($decoder->parseLine($line));
}

if ($assembler->isComplete()) {
    $payload = $assembler->assemble(); // original array
}
```

---

## Envelope Formats

### Line Envelope

```php
use TruthCodec\Envelope\EnvelopeV1Line;

$env = new EnvelopeV1Line('ER');
$line = $env->makeLine('XYZ', 1, 3, 'payload-part');
// ER|v1|XYZ|1/3|payload-part

[$code,$i,$n,$payload] = $env->parseLine($line);
```

### URL Envelope

```php
use TruthCodec\Envelope\EnvelopeV1Url;

$env = new EnvelopeV1Url(prefix: 'ER', webBase: 'https://truth.example/ingest');
$url = $env->makeLine('XYZ', 2, 5, 'payload-part');
// https://truth.example/ingest?truth=v1&prefix=ER&code=XYZ&i=2&n=5&c=payload-part
```

---

## Laravel Config

Publish config:

```bash
php artisan vendor:publish --tag=truth-codec-config
```

Configurable values in `config/truth-codec.php`:

```php
return [
    'envelope' => [
        'prefix'  => 'ER',
        'version' => 'v1',
    ],
    'auto_detect_order' => ['json', 'yaml'],
    'primary'           => 'json',
];
```

---

## Testing

Run all Pest tests:

```bash
./vendor/bin/pest
```

---

## Roadmap

- ✅ Phase 1: Core (contracts, serializers, envelopes, encoder/decoder).
- ⏳ Phase 2: Frontend TRUTH assembler (Vue).
- ⏳ Phase 3: Writers (QR sets, JSON/YAML export).
- ⏳ Phase 4: Security (signatures, replay protection).
- ⏳ Phase 5: Migration (legacy `ER|v1` → new `TRUTH|v1`).

---

## License

MIT © 2025
