# Automatic Ballot & Survey Layout Engine

Automatic, data-driven PDF generation for ballots, surveys, and questionnaires with OMR-ready output.

## Overview

This system provides a declarative way to create OMR-ready PDFs from JSON specifications. It automatically handles:
- Layout and column management
- Page breaks and overflow
- OMR bubbles with precise coordinates
- Fiducial markers for orientation detection
- Timing marks for calibration
- 2D barcodes for document identification
- Coordinate export for OpenCV appreciation

## Quick Start

### 1. Create a JSON Specification

```json
{
  "document": {
    "title": "Sample Ballot",
    "unique_id": "BAL-2025-0000123",
    "layout": "2-col"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "PRESIDENT",
      "title": "President of the Philippines",
      "maxSelections": 1,
      "layout": "2-col",
      "choices": [
        { "code": "P-A1", "label": "Candidate A" },
        { "code": "P-A2", "label": "Candidate B" }
      ]
    }
  ]
}
```

### 2. Render Using CLI

```bash
php artisan omr:render resources/samples/sample-ballot.json
```

### 3. Render Programmatically

```php
use LBHurtado\OMRTemplate\Engine\SmartLayoutRenderer;

$spec = json_decode(file_get_contents('ballot.json'), true);
$renderer = new SmartLayoutRenderer();
$result = $renderer->render($spec);

// Output:
// $result['pdf'] - Path to generated PDF
// $result['coords'] - Path to coordinates JSON
// $result['document_id'] - Document unique ID
```

## Architecture

```
JSON Specification
       ↓
SmartLayoutRenderer
  ├─ LayoutContext (tracks position, margins, pages)
  ├─ OMRDrawer (draws bubbles, fiducials, timing marks)
  ├─ CoordinatesRegistry (tracks element positions)
  ├─ OverflowPaginator (handles page breaks)
  └─ Section Renderers
       ├─ MultipleChoiceRenderer
       ├─ RatingScaleRenderer
       ├─ MatrixRenderer (stub)
       └─ FreeTextRenderer (stub)
       ↓
PDF + coordinates.json
```

## Configuration

The system uses `config/omr-template.php`:

### Page Settings
```php
'page' => [
    'size' => 'A4',
    'orientation' => 'P',
    'margins' => ['l' => 18, 't' => 18, 'r' => 18, 'b' => 18],
    'dpi' => 300,
]
```

### Font Presets
```php
'fonts' => [
    'header' => ['family' => 'helvetica', 'style' => 'B', 'size' => 12],
    'body'   => ['family' => 'helvetica', 'style' => '', 'size' => 10],
    'small'  => ['family' => 'helvetica', 'style' => '', 'size' => 8],
]
```

### Layout Presets
```php
'layouts' => [
    '1-col' => ['cols' => 1, 'gutter' => 6, 'row_gap' => 3, 'cell_pad' => 2],
    '2-col' => ['cols' => 2, 'gutter' => 10, 'row_gap' => 3, 'cell_pad' => 2],
    '3-col' => ['cols' => 3, 'gutter' => 10, 'row_gap' => 2, 'cell_pad' => 2],
]
```

### OMR Settings
```php
'omr' => [
    'bubble' => [
        'diameter_mm' => 4.0,
        'stroke' => 0.2,
        'fill' => false,
        'label_gap_mm' => 2.0,
    ],
    'fiducials' => [
        'enable' => true,
        'size_mm' => 5.0,
        'positions' => ['tl','tr','bl','br'],
    ],
    'timing_marks' => [
        'enable' => true,
        'edges' => ['left','bottom'],
        'pitch_mm' => 5.0,
        'size_mm'  => 1.5,
    ],
    'barcode' => [
        'enable' => true,
        'type' => 'PDF417',
        'height_mm' => 10.0,
    ],
]
```

## Section Types

### Multiple Choice
```json
{
  "type": "multiple_choice",
  "code": "PRESIDENT",
  "title": "President",
  "maxSelections": 1,
  "layout": "2-col",
  "choices": [
    { "code": "P-A1", "label": "Candidate A" }
  ]
}
```

### Rating Scale
```json
{
  "type": "rating_scale",
  "code": "SAT-EXP",
  "title": "Rate your experience",
  "scale": [1, 2, 3, 4, 5],
  "question": "Overall satisfaction"
}
```

### Matrix (Stub)
```json
{
  "type": "matrix",
  "code": "MATRIX",
  "title": "Matrix Question"
}
```

### Free Text (Stub)
```json
{
  "type": "free_text",
  "code": "COMMENTS",
  "title": "Additional Comments"
}
```

## Coordinates Export

The system exports precise coordinates for all OMR elements:

```json
{
  "fiducial": {
    "tl": { "x": 28.3465, "y": 28.3465, "width": 14.17325, "height": 14.17325 }
  },
  "timing_mark": {
    "left_0": { "x": 0, "y": 14.17325, "width": 4.251975, "height": 4.251975 }
  },
  "bubble": {
    "PRESIDENT_P-A1": {
      "x": 51.023622222222,
      "y": 90.7086,
      "center_x": 56.701447222222,
      "center_y": 96.386425,
      "radius": 5.677825,
      "diameter": 11.35565
    }
  },
  "barcode": {
    "document_barcode": {
      "x": 51.023622222222,
      "y": 254.4803333333333,
      "type": "PDF417",
      "data": "BAL-2025-0000123"
    }
  }
}
```

## Testing

### Standalone Test
```bash
php test-smart-layout.php
```

### Sample Files
- `resources/samples/sample-ballot.json` - Multi-section ballot
- `resources/samples/sample-survey.json` - Survey with rating scales

## Features

### ✓ Implemented (v0.1)
- Declarative JSON specifications
- Automatic layout and column management
- Multiple choice sections with configurable columns
- Rating scale sections
- OMR bubbles with precise coordinate tracking
- Fiducial markers for orientation detection
- Timing marks for calibration
- PDF417 barcode generation
- Coordinates JSON export
- Page management and margins
- Configurable fonts and spacing

### 🚧 Planned (v0.2+)
- Matrix/grid questions
- Free text areas with lines
- Automatic page breaks with continuation markers
- HTML preview mode
- Visual editor integration
- RTL (right-to-left) language support
- Custom fiducial patterns
- QR code support

## Printing Guidelines

For best OMR results:
1. **Print at 300 DPI or higher**
2. **Disable scaling** - Use "Actual Size" in print dialog
3. **Avoid printer margins/headers** - Use full page printing
4. **Use laser printer** for solid, consistent fiducial marks
5. **Use high-quality paper** to prevent bleed-through

## Directory Structure

```
packages/omr-template/
├─ config/omr-template.php              # Configuration
├─ src/
│  ├─ Contracts/
│  │  ├─ SectionRenderer.php            # Renderer interface
│  │  └─ CoordinatesSink.php            # Coordinates tracker interface
│  ├─ DTO/
│  │  ├─ DocumentSpec.php               # Document specification DTO
│  │  ├─ SectionSpec.php                # Section specification DTO
│  │  └─ ChoiceSpec.php                 # Choice specification DTO
│  ├─ Engine/
│  │  ├─ SmartLayoutRenderer.php        # Main orchestrator
│  │  ├─ LayoutContext.php              # Layout state tracker
│  │  ├─ OverflowPaginator.php          # Page break handler
│  │  ├─ CoordinatesRegistry.php        # Coordinates tracker
│  │  └─ OMRDrawer.php                  # OMR elements drawer
│  ├─ Renderers/
│  │  ├─ MultipleChoiceRenderer.php     # Multiple choice section
│  │  ├─ RatingScaleRenderer.php        # Rating scale section
│  │  ├─ MatrixRenderer.php             # Matrix questions (stub)
│  │  └─ FreeTextRenderer.php           # Free text areas (stub)
│  ├─ Support/
│  │  ├─ Measure.php                    # Unit conversion utilities
│  │  └─ TextWrap.php                   # Text wrapping utilities
│  └─ Commands/
│     └─ RenderOMRCommand.php           # CLI command
└─ resources/
   └─ samples/
      ├─ sample-ballot.json
      └─ sample-survey.json
```

## Credits

Based on the plan outlined in `AUTOMATIC_BALLOT_AND_SURVEY_LAYOUT_ENGINE.md`.

Built with:
- **TCPDF** for PDF generation
- **Spatie Laravel Data** for DTOs
- **Laravel** framework integration
