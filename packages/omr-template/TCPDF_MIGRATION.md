# TCPDF Migration Guide

## Overview

This package has been migrated from **DOMPDF** to **TCPDF** for improved precision in OMR ballot rendering, especially for fiducial markers and barcode generation.

## What Changed

### Dependencies

**Removed:**
- `dompdf/dompdf` - HTML-to-PDF renderer
- `milon/barcode` - External barcode library

**Added:**
- `elibyy/tcpdf-laravel` - Laravel wrapper for TCPDF with native barcode support

### Services Updated

1. **TemplateExporter** (`src/Services/TemplateExporter.php`)
   - Now uses `TCPDF` instead of `Dompdf`
   - PDF generation uses `writeHTML()` method
   - Output method changed from `output()` to `Output('', 'S')`

2. **BarcodeGenerator** (`src/Services/BarcodeGenerator.php`)
   - Now uses TCPDF's built-in barcode classes
   - `TCPDFBarcode` for 1D barcodes (Code39, Code128)
   - `TCPDF2DBarcode` for 2D barcodes (PDF417, QR Code)
   - Returns SVG format by default instead of HTML

3. **OutputBundle** (`src/Data/OutputBundle.php`)
   - Property type changed from `Dompdf` to `TCPDF`
   - `savePdf()` method updated to use TCPDF's `Output('', 'S')` method

### New Services

**OMRTemplateGenerator** (`src/Services/OMRTemplateGenerator.php`)
- Purpose-built service for generating OMR ballots with TCPDF
- Supports fiducial markers, PDF417 barcodes, OMR bubbles
- Direct PDF generation without HTML intermediary
- Methods:
  - `generate(array $data)` - Basic generation
  - `generateWithConfig(array $data, array $config)` - With custom config
  - `generatePdfOutput(array $data)` - Returns PDF as string

## Benefits of TCPDF

### 1. **Pixel-Perfect Precision**
TCPDF allows direct drawing of shapes (circles, rectangles) at exact mm coordinates:
```php
$pdf->Circle(30, 50, 2.5, 0, 360, 'D'); // Hollow circle at exact position
$pdf->Rect(10, 10, 10, 10, 'F'); // Filled rectangle (fiducial marker)
```

### 2. **Native Barcode Support**
No external dependencies needed for barcodes:
```php
// 2D Barcode (PDF417)
$pdf->write2DBarcode($content, 'PDF417', 10, 260, 80, 20);

// 1D Barcode (Code128)
$barcodeObj = new \TCPDFBarcode($content, 'C128');
```

### 3. **Better OMR Compatibility**
Direct coordinate control ensures consistent rendering across:
- Different printers
- Various scanning devices
- Multiple PDF viewers

### 4. **Calibration Support**
Precise coordinate system for OpenCV calibration:
- 300 DPI target resolution
- 1 mm ≈ 11.811 pixels
- Predictable fiducial marker positions

## Migration Checklist for Developers

If you have custom code using the old package:

### ✅ Update Type Hints
```php
// Old
use Dompdf\Dompdf;
function process(Dompdf $pdf) { }

// New
use TCPDF;
function process(TCPDF $pdf) { }
```

### ✅ Update PDF Output Calls
```php
// Old (DOMPDF)
$pdfContent = $dompdf->output();

// New (TCPDF)
$pdfContent = $pdf->Output('', 'S');
```

### ✅ Update Barcode Generation
```php
// Old (milon/barcode)
$barcode = (new DNS1D())->getBarcodeHTML($content, 'C128', 2, 40);

// New (TCPDF)
$barcodeObj = new \TCPDFBarcode($content, 'C128');
$barcode = $barcodeObj->getBarcodeSVGcode(2, 40, 'black');
```

### ✅ Direct PDF Generation (Optional)
For OMR templates, consider using `OMRTemplateGenerator` directly:

```php
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

$generator = new OMRTemplateGenerator();
$path = $generator->generate([
    'identifier' => 'BALLOT-2024-001',
    'fiducials' => [
        ['x' => 10, 'y' => 10, 'width' => 10, 'height' => 10],
        ['x' => 190, 'y' => 10, 'width' => 10, 'height' => 10],
        ['x' => 10, 'y' => 277, 'width' => 10, 'height' => 10],
        ['x' => 190, 'y' => 277, 'width' => 10, 'height' => 10],
    ],
    'barcode' => [
        'content' => 'BALLOT-2024-001',
        'type' => 'PDF417',
        'x' => 10,
        'y' => 260,
        'width' => 80,
        'height' => 20,
    ],
    'bubbles' => [
        ['x' => 30, 'y' => 50, 'radius' => 2.5],
        ['x' => 30, 'y' => 60, 'radius' => 2.5],
    ],
]);
```

## Testing

Run the test suite to verify TCPDF integration:

```bash
cd packages/omr-template
composer test
```

Tests cover:
- Basic PDF generation
- Fiducial marker rendering
- Barcode embedding (PDF417)
- Text element positioning
- Custom configuration options
- Sample layout rendering

## Sample Layout

A reference layout is provided at `resources/templates/sample_layout.json` demonstrating:
- Fiducial marker placement
- PDF417 barcode configuration
- OMR bubble coordinates
- Text element positioning
- Metadata structure

## Calibration for OpenCV

### Coordinate System
- **Unit**: millimeters (mm)
- **Origin**: Top-left corner (0, 0)
- **A4 Size**: 210mm × 297mm
- **DPI**: 300 (for scanning)

### Pixel Conversion
```python
# PDF mm to OpenCV pixels @ 300 DPI
pixels_per_mm = 300 / 25.4  # ≈ 11.811
x_pixels = x_mm * pixels_per_mm
y_pixels = y_mm * pixels_per_mm
```

### Fiducial Markers (Default Positions)
| Position      | mm Coords     | px Coords @ 300dpi |
|---------------|---------------|--------------------|
| Top-left      | (10, 10)      | (118, 118)         |
| Top-right     | (190, 10)     | (2244, 118)        |
| Bottom-left   | (10, 277)     | (118, 3272)        |
| Bottom-right  | (190, 277)    | (2244, 3272)       |

## Backwards Compatibility

The existing `TemplateExporter` service continues to work with HTML templates via Handlebars, now rendering through TCPDF's `writeHTML()` method. Most existing code should work without changes.

## Further Reading

- [TCPDF Documentation](https://tcpdf.org/docs/)
- [OMR Template Rendering Plan](../../resources/docs/OMR_TEMPLATE_RENDERING_PLAN.md)
- [Fiducial Implementation Guide](FIDUCIAL_IMPLEMENTATION.md)
