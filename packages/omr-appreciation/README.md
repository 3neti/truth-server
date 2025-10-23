# OMR Appreciation Package

**OMR Mark Detection and Appreciation**: Detect marks on scanned documents using fiducial alignment and pixel analysis.

## Features

- ✅ **Fiducial Detection**: Automatically detect 4 corner alignment markers
- ✅ **Image Alignment**: Basic scaling/alignment based on fiducials
- ✅ **Mark Detection**: Detect filled marks in defined zones using pixel density
- ✅ **Confidence Scoring**: Calculate confidence levels for each detection
- ✅ **Artisan Command**: Easy CLI interface for document appreciation

## Installation

This package is part of the Truth monorepo and is installed via Composer:

```bash
composer require lbhurtado/omr-appreciation
```

## Usage

### Via Artisan Command

```bash
php artisan omr:appreciate {image} {template} [options]
```

**Arguments:**
- `image`: Path to scanned image file (JPG, PNG, GIF)
- `template`: Path to template JSON file (from `omr:generate` output)

**Options:**
- `--output=path`: Save results to file instead of stdout
- `--threshold=0.3`: Fill threshold for mark detection (0.0-1.0, default: 0.3)

### Example

```bash
# Generate a template first
php artisan omr:generate ballot-v1 BAL-001 --data=ballot-data.json

# This creates: storage/omr-output/BAL-001.json

# Scan and appreciate the filled ballot
php artisan omr:appreciate scanned-ballot.jpg storage/omr-output/BAL-001.json --output=results.json
```

### Programmatic Usage

```php
use LBHurtado\OMRAppreciation\Services\AppreciationService;

$appreciationService = app(AppreciationService::class);

$templateData = json_decode(file_get_contents('template.json'), true);
$result = $appreciationService->appreciate('scanned-image.jpg', $templateData);

// Result structure:
[
    'document_id' => 'BALLOT-ABC-001-PDF-147',
    'template_id' => 'ballot-v1',
    'fiducials_detected' => [...],
    'marks' => [
        [
            'id' => 'PRES_001',
            'x' => 100,
            'y' => 200,
            'width' => 20,
            'height' => 20,
            'filled' => true,
            'confidence' => 0.85,
            'fill_ratio' => 0.62
        ],
        ...
    ],
    'summary' => [
        'total_zones' => 10,
        'filled_count' => 3,
        'unfilled_count' => 7,
        'average_confidence' => 0.78
    ]
]
```

## How It Works

### 1. Fiducial Detection

The system scans the image for the 4 black square fiducial markers printed in the corners of the document. These are used for alignment and scaling.

**Fiducial Format Support:** The system automatically handles both fiducial formats:
- Array format: `[{"id": "top_left", "x": 100, "y": 100}, ...]` (output from `omr:generate`)
- Associative format: `{"top_left": {"x": 100, "y": 100}, ...}`

### 2. Image Alignment

Based on detected fiducials, the system scales and aligns the scanned image to match the template dimensions. This compensates for slight variations in scanning/photo capture.

### 3. Mark Detection

For each zone defined in the template:
- Extract the region of interest (ROI)
- Convert to grayscale
- Count "dark" pixels (brightness < 127)
- Calculate fill ratio = dark pixels / total pixels
- Mark as "filled" if fill ratio ≥ threshold (default 0.3)

### 4. Confidence Calculation

Confidence score indicates how clearly the mark is filled or unfilled:
- High confidence (>0.8): Clear fill or clear empty
- Medium confidence (0.5-0.8): Somewhat clear
- Low confidence (<0.5): Ambiguous, may need manual review

## Limitations

- **Perspective Correction**: Current implementation uses basic scaling. For full perspective transform, consider using ImageMagick or OpenCV with PHP bindings.
- **Image Quality**: Best results with high-quality scans (300+ DPI) and good lighting
- **Fiducial Requirements**: All 4 fiducials must be clearly visible
- **Mark Types**: Optimized for filled boxes/ovals, may not work well with checkmarks or X marks

## Future Enhancements

- Full perspective correction using ImageMagick
- PDF417/Code128 barcode reading for automatic document ID extraction
- Confidence-based manual review UI
- Support for different mark styles (checkmarks, X, etc.)
- Webcam/mobile capture support
- Batch processing of multiple documents

## Requirements

- PHP 8.2+
- GD extension (ext-gd)
- Laravel 12+
- lbhurtado/omr-template package

## Testing

```bash
cd packages/omr-appreciation
composer install
vendor/bin/pest
```

**Test Coverage:** 16 tests (79 assertions)
- Unit tests: FiducialDetector, MarkDetector, AppreciationService
- Feature tests: Full workflow, Command integration
- Fiducial format compatibility (array and associative)

## License

Proprietary
