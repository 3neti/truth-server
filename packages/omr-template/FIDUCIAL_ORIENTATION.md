# Fiducial Marker Orientation Detection

## Overview

Fiducial markers are black squares at page corners that enable OpenCV to detect and correct ballot orientation (0°, 90°, 180°, 270°).

## Features

✅ **3 Fiducial Layouts**:
- `default` - Symmetrical (basic alignment)
- `asymmetrical_right` - Right side offset
- `asymmetrical_diagonal` - Diagonal pattern

✅ **Orientation Detection** - Automatic rotation correction  
✅ **OpenCV Compatible** - Calibration data export  
✅ **Config-Based** - Easy layout switching  

## Configuration

### Available Layouts

```php
// config/omr-template.php

'fiducials' => [
    'default' => [
        'top_left' => ['x' => 10, 'y' => 10],
        'top_right' => ['x' => 190, 'y' => 10],
        'bottom_left' => ['x' => 10, 'y' => 277],
        'bottom_right' => ['x' => 190, 'y' => 277],
    ],
    'asymmetrical_right' => [
        'top_left' => ['x' => 10, 'y' => 10],
        'top_right' => ['x' => 180, 'y' => 12],      // Offset
        'bottom_left' => ['x' => 10, 'y' => 277],
        'bottom_right' => ['x' => 180, 'y' => 270],  // Offset
    ],
],

'marker_size' => 10,  // 10mm x 10mm
'default_fiducial_layout' => 'default',
```

### Environment Variable

```bash
# .env
OMR_FIDUCIAL_LAYOUT=asymmetrical_right
```

## Usage

### Generate PDF with Specific Layout

```php
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

$generator = new OMRTemplateGenerator();

// Use specific layout
$data = [
    'identifier' => 'BALLOT-001',
    'bubbles' => [...]
];

$pdf = $generator->generateWithFiducialLayout(
    $data, 
    'asymmetrical_right'
);
```

### Get Fiducials for Layout

```php
$fiducials = $generator->getFiducialsForLayout('asymmetrical_right');
// Returns array of 4 fiducials with positions
```

## Orientation Detection

### PHP Helper

```php
use LBHurtado\OMRTemplate\Services\FiducialOrientationHelper;

$helper = new FiducialOrientationHelper();

// Detect orientation from scanned positions
$detectedPoints = [
    ['x' => 190, 'y' => 277],
    ['x' => 10, 'y' => 10],
    ['x' => 190, 'y' => 10],
    ['x' => 10, 'y' => 277],
];

$sorted = $helper->sortFiducialsByPosition($detectedPoints);
$orientation = $helper->determineOrientation($sorted);
// Returns: 0, 90, 180, or 270 degrees
```

### Export Calibration for Python

```php
$fiducials = $generator->getFiducialsForLayout('asymmetrical_right');
$json = $helper->exportCalibrationJson($fiducials, 300);

file_put_contents('calibration.json', $json);
```

Output:
```json
{
    "dpi": 300,
    "conversion_factor": 11.811023622047,
    "fiducials_mm": {
        "top_left": {"x": 10, "y": 10},
        "top_right": {"x": 180, "y": 12},
        "bottom_left": {"x": 10, "y": 277},
        "bottom_right": {"x": 180, "y": 270}
    },
    "fiducials_px": {
        "top_left": {"x": 118, "y": 118},
        "top_right": {"x": 2126, "y": 142},
        "bottom_left": {"x": 118, "y": 3272},
        "bottom_right": {"x": 2126, "y": 3189}
    }
}
```

## Python Integration

```python
import cv2
import json
import numpy as np

# Load calibration
with open('calibration.json') as f:
    calibration = json.load(f)

# Detect fiducials in scanned image
image = cv2.imread('scanned_ballot.jpg', cv2.IMREAD_GRAYSCALE)
_, thresh = cv2.threshold(image, 127, 255, cv2.THRESH_BINARY_INV)
contours, _ = cv2.findContours(thresh, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

# Find square contours (fiducials)
anchors = []
for cnt in contours:
    approx = cv2.approxPolyDP(cnt, 0.02 * cv2.arcLength(cnt, True), True)
    if len(approx) == 4 and cv2.contourArea(cnt) > 500:
        x, y, w, h = cv2.boundingRect(cnt)
        anchors.append((x + w // 2, y + h // 2))

# Determine orientation
# Sort anchors and compare with expected positions
# Apply rotation if needed
```

## Coordinate System

- **Unit**: Millimeters (mm)
- **Origin**: Top-left (0, 0)
- **Page**: A4 (210mm × 297mm)
- **DPI**: 300
- **Conversion**: 1 mm ≈ 11.811 pixels

### Example Positions

| Layout | Corner | MM | Pixels @ 300 DPI |
|--------|--------|----|------------------|
| Default | Top-left | (10, 10) | (118, 118) |
| Default | Top-right | (190, 10) | (2244, 118) |
| Asymmetrical | Top-right | (180, 12) | (2126, 142) |

## Helper Methods

### Coordinate Conversion

```php
$pixels = $helper->mmToPixels(10, 300);  // 118px
$mm = $helper->pixelsToMm(118, 300);     // 10mm
```

### Centroid Calculation

```php
$centroid = $helper->calculateCentroid($points);
// Returns ['x' => float, 'y' => float]
```

### Asymmetry Detection

```php
$isAsymmetric = $helper->isAsymmetricPattern($fiducials);
// Returns true if pattern enables orientation detection
```

## Testing

```bash
composer test --filter=FiducialOrientationHelperTest
```

Tests cover:
- ✅ MM ↔ Pixel conversion
- ✅ Layout retrieval
- ✅ Fiducial sorting
- ✅ Orientation detection
- ✅ Centroid calculation
- ✅ Asymmetry detection
- ✅ Calibration export
- ✅ PDF generation with layouts

## Summary

| Component | Purpose |
|-----------|---------|
| Config | Define fiducial layouts |
| OMRTemplateGenerator | Generate PDFs with layouts |
| FiducialOrientationHelper | Detect orientation, export calibration |
| Python | Apply corrections based on detected markers |

Fiducial orientation detection ensures accurate OMR processing regardless of scan orientation! 🎯
