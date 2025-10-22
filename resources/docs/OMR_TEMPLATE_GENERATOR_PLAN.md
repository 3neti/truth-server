# 🗳️ OMR Template Generator – Laravel Package Plan

This document outlines the architecture, capabilities, and implementation steps for a reusable Laravel package designed to generate printable, scan-friendly documents (e.g., **ballots, test papers, surveys**) using **Handlebars templating + DOMPDF rendering**, with zone mapping support for Optical Mark Recognition (OMR).

---

## 🧱 Package Name & Namespace

```
Package name: lbhurtado/omr-template
Namespace: LBHurtado\OMRTemplate
Root dir: packages/omr-template
```

---

## 🎯 Purpose

To enable generation of structured, auditable PDF documents with:
- Dynamic templating (Handlebars)
- Deterministic layouts for OMR
- Precise mark zone mapping output (JSON)
- Support for alignment anchors, QR codes, and variant layouts
- Compatibility with airgapped systems

---

## 🧩 Use Cases

| Domain | Example |
|--------|---------|
| Elections | Precinct ballot generation (with appreciation zones) |
| Education | Exam/test papers with multiple choice layouts |
| Research | Survey forms with Likert-scale fields |
| Feedback | Paper-based evaluation forms with scannable marks |
| KYC / Field Ops | Structured checklists for scanning & archival |

---

## 🏗️ Package Structure

```
omr-template/
├── composer.json
├── config/
│   └── omr-template.php
├── src/
│   ├── Data/
│   │   ├── TemplateData.php
│   │   ├── ZoneMapData.php
│   │   └── OutputBundle.php
│   ├── Services/
│   │   ├── TemplateRenderer.php
│   │   ├── TemplateExporter.php
│   │   └── HandlebarsEngine.php
│   └── OMRTemplateServiceProvider.php
├── resources/
│   └── templates/
│       ├── ballot-v1.hbs
│       ├── test-paper-v1.hbs
│       └── survey-v1.hbs
├── routes/
│   └── web.php (optional UI preview)
├── tests/
│   ├── Unit/
│   └── Feature/
```

---

## 🧾 Key Components

### ✅ `TemplateData`
DTO defining structure:
```php
class TemplateData extends Data
{
    public function __construct(
        public string $template_id,
        public string $document_type, // e.g., 'ballot', 'test', 'survey'
        public array $contests_or_sections, // contest/question groups
        public string $layout = 'A4',
        public ?array $qr = null // base64 image data
    ) {}
}
```

---

### ✅ `ZoneMapData`
Defines positional coordinates for OMR mark zones (ROIs).

```json
{
  "template_id": "ballot-v1",
  "document_type": "ballot",
  "zones": [
    {
      "section": "PRESIDENT",
      "code": "CAND001",
      "x": 105,
      "y": 402,
      "width": 50,
      "height": 50
    },
    ...
  ]
}
```

---

### ✅ `HandlebarsEngine`
Utility to render `.hbs` templates to HTML using the same syntax as `truth-renderer`.

```php
$html = (new HandlebarsEngine)->template($path)->render($data);
```

---

### ✅ `TemplateRenderer`
Handles HTML rendering + zone mapping generation.

```php
$html = app(TemplateRenderer::class)->render($templateData);
```

---

### ✅ `TemplateExporter`
Handles DOMPDF conversion + JSON export.

```php
$output = app(TemplateExporter::class)->export($html, $zoneMapData);
$output->savePdf('/output/ballot-001.pdf');
$output->saveJson('/output/ballot-001.json');
```

---

## ⚙️ Config (`config/omr-template.php`)

```php
return [
    'default_template_path' => resource_path('templates'),
    'output_path' => storage_path('omr-output'),
    'default_layout' => 'A4',
    'dpi' => 300,
];
```

---

## 🧪 Artisan Command (optional)

```bash
php artisan omr:generate ballot ABC-001
```

Uses:
- Template: `ballot-v1.hbs`
- Data: from `truth-election-php` or custom provider
- Output: PDF + JSON in `storage/omr-output/`

---

## 📤 Output Bundle

Each generation produces:

```
ballot-ABC-001.pdf          ✅ Printable document
ballot-ABC-001.json         ✅ Mark zone mapping
ballot-ABC-001.meta.json    ✅ Metadata (precinct, template_id, hash)
(optional) zip bundle       ✅ For transfer or archival
```

---

## 🔐 Security & Audit

- JSON output can be hashed (SHA256) and signed
- All generated outputs are deterministic and reproducible
- Optional QR includes `template_id`, `precinct_code`, `uuid` for traceability

---

## 🛠️ Integration Points

| Package | Integration |
|--------|-------------|
| `truth-election-php` | Supplies BallotData, CandidateData |
| `truth-qr-ui` | Generates QR base64 for inclusion in ballot |
| `truth-renderer` | Shares `HandlebarsEngine` if needed |
| `truth-validator` (future) | Can validate filled ballots against zone map |

---

## ✅ Advantages of Package Format

- Reusable across multiple apps (e.g., elections, test engines, surveys)
- Easy to version and publish to Packagist
- Works offline and airgapped
- Modular — logic, data, template separation
- Composer installable in any Laravel 10/11/12 app
- Testable and extensible

---

## 📦 Publishable Assets

```bash
php artisan vendor:publish --tag=omr-templates
php artisan vendor:publish --tag=omr-config
```

Includes:
- `resources/templates/ballot-v1.hbs`
- `config/omr-template.php`

---

## 🧠 Future Enhancements

- 🖱️ Drag-and-drop Vue template editor
- 📐 GUI zone mapping overlay (Konva.js or Fabric.js)
- 🧾 Import/export QTI or LMS formats for test papers
- 🔢 Versioned template registry (template-v1, v2...)
- 📁 Upload filled images → OMR appreciation pipeline (offline or web)

---

## 📚 References

- `barryvdh/laravel-dompdf` – PDF rendering
- `handlebars.php` or `truth-renderer` – Templating engine
- `spatie/laravel-data` – DTOs and transformers
- `LBHurtado\TruthRenderer` – Optional shared rendering logic

---

## ✅ Summary

This package will serve as the **core template generator** for:
- TRUTH™ Ballot Appreciation System
- Survey/feedback form pipelines
- Exam/test OMR workflows
- Offline airgapped precinct printing
- Future API-driven document rendering services

It is **domain-agnostic**, testable, extendable, and aligns with your Laravel ecosystem.

```bash
composer require lbhurtado/omr-template
```

Ready to print, scan, appreciate.
