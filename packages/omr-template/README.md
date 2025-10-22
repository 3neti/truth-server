# OMR Template Generator

A Laravel package for generating printable, scan-friendly documents (ballots, test papers, surveys) using Handlebars templating and DOMPDF rendering, with zone mapping support for Optical Mark Recognition (OMR).

## Features

- 📄 Dynamic templating with Handlebars
- 🎯 Deterministic layouts for OMR
- 📍 Precise mark zone mapping output (JSON)
- 🔒 Support for alignment anchors and QR codes
- 🌐 Compatible with airgapped systems
- 📦 Reusable across multiple applications

## Installation

```bash
composer require lbhurtado/omr-template
```

## Usage

### Publishing Assets

```bash
# Publish config
php artisan vendor:publish --tag=omr-config

# Publish templates
php artisan vendor:publish --tag=omr-templates
```

### Generating Documents

```bash
# Basic usage
php artisan omr:generate ballot-v1 ABC-001

# With data file
php artisan omr:generate ballot-v1 ABC-001 --data=path/to/data.json
```

### Programmatic Usage

```php
use LBHurtado\OMRTemplate\Data\TemplateData;
use LBHurtado\OMRTemplate\Data\ZoneMapData;
use LBHurtado\OMRTemplate\Services\TemplateRenderer;
use LBHurtado\OMRTemplate\Services\TemplateExporter;

// Create template data
$templateData = new TemplateData(
    template_id: 'ballot-v1',
    document_type: 'ballot',
    contests_or_sections: [
        [
            'title' => 'President',
            'instruction' => 'Vote for one',
            'candidates' => [
                ['name' => 'John Doe', 'party' => 'Party A'],
                ['name' => 'Jane Smith', 'party' => 'Party B'],
            ],
        ],
    ],
);

// Render HTML
$html = app(TemplateRenderer::class)->render($templateData);

// Create zone map
$zoneMap = new ZoneMapData(
    template_id: 'ballot-v1',
    document_type: 'ballot',
    zones: [
        [
            'section' => 'PRESIDENT',
            'code' => 'CAND001',
            'x' => 105,
            'y' => 402,
            'width' => 50,
            'height' => 50,
        ],
    ],
);

// Export to PDF and JSON
$output = app(TemplateExporter::class)->export($html, $zoneMap);
$output->saveAll(storage_path('omr-output/ballot-001'));
```

## Use Cases

| Domain | Example |
|--------|---------|
| Elections | Precinct ballot generation with appreciation zones |
| Education | Exam/test papers with multiple choice layouts |
| Research | Survey forms with Likert-scale fields |
| Feedback | Paper-based evaluation forms with scannable marks |
| KYC / Field Ops | Structured checklists for scanning & archival |

## Configuration

Edit `config/omr-template.php`:

```php
return [
    'default_template_path' => resource_path('templates'),
    'output_path' => storage_path('omr-output'),
    'default_layout' => 'A4',
    'dpi' => 300,
];
```

## Templates

Templates use Handlebars syntax. Example:

```handlebars
<div class="contest">
    <div class="contest-title">{{title}}</div>
    {{#each candidates}}
    <div class="candidate">
        <div class="mark-zone"></div>
        <strong>{{this.name}}</strong>
    </div>
    {{/each}}
</div>
```

### Included Templates

- `ballot-v1.hbs` - Election ballot template
- `test-paper-v1.hbs` - Exam/test paper template
- `survey-v1.hbs` - Survey form with Likert scales

## Output

Each generation produces:

```
ballot-ABC-001.pdf          ✅ Printable document
ballot-ABC-001.json         ✅ Mark zone mapping
ballot-ABC-001.meta.json    ✅ Metadata (hash, timestamp)
```

## Testing

```bash
cd packages/omr-template
composer install
vendor/bin/pest
```

## License

Proprietary
