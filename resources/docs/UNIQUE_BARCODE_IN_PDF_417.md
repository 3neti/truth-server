# 🏷️ Unique Document Identifier as Barcode (e.g. `PDF-417`)
*Add barcode to printable PDF via `omr-template` package*

This plan adds a **1D barcode** (e.g., Code 128 or Code 39) encoding the document ID (e.g., `BALLOT-ABC-001-PDF-147`) into the generated PDF output — for fast machine-scanning and pairing during ballot appreciation or document intake.

---

## 🧩 Barcode Format Recommendation

| Format | Use Case | Length | Libraries |
|--------|----------|--------|-----------|
| **Code 128** | Compact, all ASCII | Up to 80 chars | ✅ Recommended |
| Code 39 | Simpler, longer barcodes | < 20 chars | Acceptable |
| QR Code | Already supported | Visual + machine use | Optional fallback |

> 📌 **Recommendation**: Use **Code 128** for all document IDs (clean, compact, widely supported).

---

## ✅ Implementation Plan

### 1. 📦 Add Barcode Generator Package (Laravel)

**Option A: Use `milon/barcode`**

```bash
composer require milon/barcode
```

- Supports rendering **base64 barcodes** as PNG or SVG
- Compatible with Blade or inline PHP

---

### 2. 🔧 Update `BallotDesignData` DTO

Add `barcode_base64` field to be passed into the Handlebars template:

```php
class BallotDesignData extends Data
{
    public function __construct(
        public string $document_id,
        public string $template_id,
        public array $zones,
        public array $fiducials,
        public string $document_type,
        public string $size = 'A4',
        public int $dpi = 300,
        public ?array $qr = null,
        public ?string $barcode_base64 = null
    ) {}
}
```

---

### 3. 🏗️ Generate Barcode in Service Layer

```php
use Milon\Barcode\Facades\DNS1D;

$barcodeBase64 = DNS1D::getBarcodePNG($documentId, 'C128', 2, 40, [0,0,0], true);

$designData = new BallotDesignData(
    document_id: $documentId,
    template_id: 'ballot-v1',
    zones: [...],
    fiducials: [...],
    document_type: 'ballot',
    barcode_base64: 'data:image/png;base64,' . $barcodeBase64,
);
```

> This will embed a PNG barcode inline in the HTML.

---

### 4. 🖨️ Modify Handlebars Template to Show Barcode

**File:** `resources/templates/ballot-v1.hbs`

Add barcode + document ID display (bottom or top):

```handlebars
<!-- Barcode -->
{{#if barcode_base64}}
  <div style="position: absolute; bottom: 12mm; left: 10mm;">
    <img src="{{barcode_base64}}" style="height: 40px;" />
  </div>
{{/if}}

<!-- Document ID (for human readers) -->
<div style="position: absolute; bottom: 6mm; left: 10mm; font-size: 10pt;">
  ID: {{document_id}}
</div>
```

---

### 5. ✅ Output Verification

After generation:
- Barcode should scan using any 1D barcode scanner or mobile app
- Text should match exactly: `BALLOT-ABC-001-PDF-147`
- Use barcode scanner in dev environment to test

---

### 6. 📦 Optional: Support Multiple Barcode Types

In config (`omr-template.php`):

```php
return [
  'barcode' => [
    'type' => 'C128', // or 'C39'
    'width_scale' => 2,
    'height' => 40,
    'font' => true
  ]
];
```

---

## 📦 Files Affected

| File | Purpose |
|------|---------|
| `BallotDesignData.php` | Add `barcode_base64` |
| `TemplateRenderer.php` or Controller | Generate barcode PNG |
| `ballot-v1.hbs` | Render `<img>` tag for barcode |
| `composer.json` | Add `milon/barcode` dependency |
| `config/omr-template.php` | (Optional) Add barcode config defaults |

---

## ✅ Summary

With this enhancement:

- Every document has a **human-readable** and **machine-readable** identifier
- You support **faster lookup**, **scan-based appreciation**, and **reconciliation**
- Works offline, printable, no third-party services needed

Example:
```
Document ID: BALLOT-ABC-001-PDF-147
Barcode: <embedded Code 128>
```

Can be scanned during intake, comparison, sorting, audit, or recount.

```

Let me know if you'd like this bundled into a README, scaffolded into your Laravel package, or auto-tested in a Dusk or unit test.
