# TRUTH Codec — Cheat Sheet

A quick, practical reference for `truth-codec-php` usage. Keep this beside your editor.

---

## Install (Laravel)

```bash
composer require your-org/truth-codec-php
php artisan vendor:publish --tag=truth-codec-config
```

Config file: `config/truth-codec.php`

---

## Key Concepts

- **Envelope (v1)**: wraps each chunk (line or URL form)
- **Serializer**: JSON (canonical) or YAML (round-trippable)
- **Transport**: encodes serialized text for constrained media (Base64-URL / raw / gzip+base64)
- **Encoder**: splits payload → envelope lines
- **Decoder**: parses one line → header+payload part
- **Assembler**: validates headers, collects parts, reassembles + decodes

---

## IoC Bindings (defaults)

```php
app(\TruthCodec\Contracts\PayloadSerializer::class)  // AutoDetect(JSON,YAML) with JSON primary
app(\TruthCodec\Contracts\TransportCodec::class)     // Base64UrlTransport
app(\TruthCodec\Contracts\Envelope::class)           // EnvelopeV1Line ("ER|v1|...")
```

Switch to URL envelope (deep links / web links) in `config/truth-codec.php`:

```php
'v1' => [
  'driver' => 'url',        // 'line' or 'url'
  'prefix' => 'ER',         // logical family: ER/BAL/CANVASS/...
  'scheme' => 'truth',      // deep-link scheme
  'web_base' => null,       // e.g. 'https://truth.example/ingest'
  'payload_param' => 'c',   // query key for payload
  'version_param' => 'v',   // alt key for version
],
```

---

## Encode → QR Lines

```php
use TruthCodec\Encode\ChunkEncoder;

/** @var ChunkEncoder $enc */
$enc = app(ChunkEncoder::class);

$payload = [
  'type' => 'ER',
  'code' => 'CURRIMAO-001',
  'data' => ['hello' => 'world'],
];

$lines = $enc->encodeToChunks($payload, $payload['code'], chunkSize: 800);
// $lines[0] => "ER|v1|CURRIMAO-001|1/4|AAABBB..."
```

> For URL envelopes, lines will look like `truth://v1/ER/CODE/1/4?c=...` or `https://.../ingest?...` depending on config.

---

## Ingest (Decode + Assemble)

```php
use TruthCodec\Decode\ChunkDecoder;
use TruthCodec\Decode\ChunkAssembler;

/** @var ChunkDecoder $dec */
$dec = app(ChunkDecoder::class);
/** @var ChunkAssembler $asm */
$asm = app(ChunkAssembler::class);

foreach ($lines as $line) {
  $hdr = $dec->parseLine($line);  // [code, index, total, payloadPart]
  $asm->add($hdr);                 // throws on code/total mismatch, out-of-range, duplicates
}

if ($asm->isComplete()) {
  $payload = $asm->assemble();     // array<string,mixed>
}
```

---

## Switching Serializers / Transport

```php
use TruthCodec\Serializer\JsonSerializer;
use TruthCodec\Serializer\YamlSerializer;
use TruthCodec\Serializer\AutoDetectSerializer;
use TruthCodec\Transport\Base64UrlTransport;
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Decode\ChunkAssembler;

$auto = new AutoDetectSerializer([new JsonSerializer(), new YamlSerializer()], new JsonSerializer());
$transport = new Base64UrlTransport();

$enc = new ChunkEncoder($auto, $transport);
$asm = new ChunkAssembler($auto, $transport);
```

---

## URL Envelope Quick Reference

**Deep link:**  
`truth://v1/<prefix>/<code>/<i>/<n>?c=<part>`

**Web link:**  
`https://host/path?truth=v1&prefix=<prefix>&code=<code>&i=<i>&n=<n>&c=<part>`

- `prefix`: ER/BAL/CANVASS (resolve in your app)
- For web links, your route should accept these params and append the `c` part to the assembler.

---

## Minimal Pest Tests

```php
use TruthCodec\Encode\ChunkEncoder;
use TruthCodec\Decode\{ChunkAssembler, ChunkDecoder};
use TruthCodec\Serializer\{JsonSerializer, YamlSerializer, AutoDetectSerializer};
use TruthCodec\Transport\Base64UrlTransport;

it('roundtrips ER payload (JSON)', function () {
  $codec = new AutoDetectSerializer([new JsonSerializer(), new YamlSerializer()], new JsonSerializer());
  $tx    = new Base64UrlTransport();
  $enc   = new ChunkEncoder($codec, $tx);
  $dec   = new ChunkDecoder(app(\TruthCodec\Contracts\Envelope::class));
  $asm   = new ChunkAssembler($codec, $tx);

  $payload = ['type'=>'ER','code'=>'XYZ','data'=>['hello'=>'world']];
  $lines   = $enc->encodeToChunks($payload, 'XYZ', 400);

  foreach ($lines as $line) $asm->add($dec->parseLine($line));

  expect($asm->isComplete())->toBeTrue();
  expect($asm->assemble())->toEqual($payload);
});
```

---

## Troubleshooting

- **“Chunks disagree on code/total”** → mixed sets; reset and rescan the correct group.
- **“Chunk index out of range”** → header tampered (e.g., `4/3`); reject.
- **Complete but assemble fails** → payload fragment corrupted; rescan the offending QR.
- **Auto-detect decode failed** → payload not JSON/YAML; check your transport (e.g., Base64Url) and QR size.

---

## Best Practices

- Keep chunk size ≤ **800 bytes** for robust QR scanning (adjust per printer/scanner).
- Prefer **JSON** as primary serializer for canonical signatures; allow YAML for human IO.
- Always include `type` + `code` in payload for quick routing and app UX.
- For web links, show **progress** (received indices, total, missing) and auto-finish on last scan.
- Consider signing the **serialized** payload (pre-transport) for reproducible verification.

---

## Quick API Map

- `TruthCodec\Contracts\Envelope`: build/parse lines (Line/URL)
- `TruthCodec\Contracts\PayloadSerializer`: encode/decode (JSON/YAML/Auto)
- `TruthCodec\Contracts\TransportCodec`: encode/decode transport (Base64Url, Gzip+Base64)
- `TruthCodec\Encode\ChunkEncoder`: payload → lines
- `TruthCodec\Decode\ChunkDecoder`: line → header
- `TruthCodec\Decode\ChunkAssembler`: headers → payload

---

Happy shipping! 🚀
