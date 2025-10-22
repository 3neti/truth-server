# 🆔 Unique Document Identifier Plan
*For ballots, tests, surveys generated via omr-template*

This plan adds a **unique document ID** to every printed form (e.g., `BALLOT-ABC-001-PDF-147`), visible on the page and optionally encoded as a **QR code or barcode**. This ensures **each generated instance is traceable and distinct**, even if the same template is used.

---

## ✅ Why Use Unique Identifiers?

| Purpose | Benefit |
|---------|---------|
| 📌 Traceability | Every printed PDF has a unique fingerprint |
| 🧾 Auditability | Link scanned image to exact output |
| 🧑‍⚖️ Legal defensibility | Support election recounts, test grading, survey validation |
| 📚 Duplicate detection | Prevent double submissions |
| 🛡️ Chain of custody | Printed IDs stored, logged, and cross-checked |

---

## 📐 ID Format Recommendation

Use a compact, structured format:

```
<BLOCK-TYPE>-<PRECINCT|GROUP>-<SERIAL>

Examples:
- BALLOT-ABC-001-PDF-147
- SURVEY-X-ROOMA-PDF-008
- TEST-CLASS9-PDF-027
```

You can use UUIDs if needed, but short human-readable serials work well.

---

## 🧩 Implementation Steps

### ✅ 1. Add `document_id` to `TemplateData`

**File:** `TemplateData.php`

```php
class TemplateData extends Data
{
    public function __construct(
        public string $template_id,
        public string $document_type,
        public array $fiducials,
        public array $zones,
        public string $document_id, // e.g. BALLOT-ABC-001-PDF-147
        public ?array $qr = null,
        public string $size = 'A4',
        public int $dpi = 300,
    ) {}
}
```

---

### ✅ 2. Modify Handlebars Template to Show ID

**File:** `resources/templates/ballot-v1.hbs`

Add visible text + optional QR:

```handlebars
<!-- Show the document ID -->
<div style="position: absolute; bottom: 10mm; left: 10mm; font-size: 10pt;">
  ID: {{document_id}}
</div>

<!-- Optional QR code -->
{{#if qr}}
  <div style="position: absolute; bottom: 10mm; right: 10mm;">
    <img src="{{qr.base64}}" width="80" />
  </div>
{{/if}}
```

---

### ✅ 3. Add ID Generator Logic

**File:** `DocumentIdGenerator.php`

```php
class DocumentIdGenerator
{
    public static function generate(string $type, string $precinctOrGroup, int $serial): string
    {
        return strtoupper("{$type}-{$precinctOrGroup}-PDF-" . str_pad($serial, 3, '0', STR_PAD_LEFT));
    }
}
```

Or use UUIDs if randomness > readability.

---

### ✅ 4. Inject into BallotDesignData

**File:** your Laravel command or generator

```php
use App\Services\DocumentIdGenerator;

$serial = 147; // get from DB or incrementor
$docId = DocumentIdGenerator::generate('BALLOT', 'ABC-001', $serial);

$data = new BallotDesignData(
    template_id: 'ballot-v1',
    document_type: 'ballot',
    fiducials: [...],
    zones: [...],
    document_id: $docId,
    qr: [
      'base64' => app(GenerateQrCode::class)->run([
          'document_id' => $docId,
          'template_id' => 'ballot-v1'
      ])->base64
    ]
);
```

---

### ✅ 5. Output Mapping File with ID

**File:** `ballot-ABC-001-PDF-147.json`

```json
{
  "document_id": "BALLOT-ABC-001-PDF-147",
  "template_id": "ballot-v1",
  "precinct": "ABC-001",
  "fiducials": [...],
  "zones": [...],
  "generated_at": "2025-10-22T12:34:56+08:00"
}
```

---

### ✅ 6. Track Issued IDs (Optional)

Use DB table `generated_documents`:

| id | document_id | template_id | generated_by | generated_at |
|----|-------------|-------------|--------------|--------------|
| 1  | BALLOT-ABC-001-PDF-147 | ballot-v1 | system | 2025-10-22 12:34 |

---

## 🧪 Optional Enhancements

| Feature | Description |
|---------|-------------|
| ✅ QR encodes full metadata | e.g., `{"doc_id": "...", "template": "...", "hash": "..."}` |
| ✅ Digital signature or HMAC | Tamper-evident QR |
| ✅ Scanned image matching | Match document ID via OCR or QR in OMR pipeline |
| ✅ PDF hash log | Store SHA256 of PDF for forensic validation |

---

## 📦 Files Affected

| File | Change |
|------|--------|
| `TemplateData.php` | Add `document_id` |
| `ballot-v1.hbs` | Display ID + QR |
| `DocumentIdGenerator.php` | Utility class |
| `TemplateExporter.php` | Export filename as `ballot-<precinct>-PDF-###.pdf` |
| `zone_map.json` | Include document_id and timestamp |

---

## ✅ Summary

With this feature:
- Every form is **traceable**
- Appreciation pipeline can link back to original
- IDs can be **printed, encoded, logged, and audited**
- Supports versioning and accountability across domains

This is critical for elections, exams, and surveys — especially under public or legal scrutiny.

```
