# Barcode Decoder Guide

## Overview

The barcode decoder module (`barcode_decoder.py`) provides automatic document ID extraction from ballot images using QR codes, Code128, or PDF417 barcodes. It integrates seamlessly with the OMR appreciation workflow to enable ballottracking and audit trails.

## Features

- **Multi-format support**: QR codes, Code128, PDF417
- **Multi-decoder strategy**: pyzbar → pyzxing → ZXing CLI with automatic fallback
- **Type-specific ROI sizing**: Optimized extraction regions for different barcode types
- **Metadata fallback**: Uses template metadata when visual decode fails
- **Confidence scoring**: Reports decode confidence (0.0-1.0)
- **Source tracking**: Distinguishes between visual decode and metadata fallback

## Installation

### Python Dependencies

```bash
# Required: pyzbar for QR/Code128
pip install pyzbar

# Optional: pyzxing for PDF417 (requires Java)
pip install pyzxing

# Optional: ZXing CLI (system-level install)
# macOS: brew install zxing
# Ubuntu: apt-get install zxing
```

### System Dependencies

```bash
# macOS
brew install zbar

# Ubuntu/Debian
sudo apt-get install libzbar0

# For PDF417 support (pyzxing)
brew install openjdk  # macOS
sudo apt-get install default-jdk  # Ubuntu
```

## Module API

### `decode_barcode()`

Main function for barcode decoding with multi-decoder fallback.

```python
from barcode_decoder import decode_barcode
import cv2

# Load image
image = cv2.imread('ballot.png')

# Define barcode coordinates (from template)
barcode_coords = {
    'x': 99,          # X position in mm
    'y': 254,         # Y position in mm
    'type': 'QRCODE'  # Barcode type
}

# Decode with fallback
result = decode_barcode(
    image,
    barcode_coords,
    mm_to_px_ratio=11.811,              # 300 DPI conversion
    metadata_fallback='BALLOT-001'       # Fallback value
)

print(result)
# {
#   'document_id': 'BOKIAWAN-001-ballot-2025-10-28',
#   'decoder': 'pyzbar',
#   'confidence': 0.9,
#   'decoded': True,
#   'source': 'visual',
#   'rect': {'x': 1119, 'y': 2949, 'width': 572, 'height': 559}
# }
```

#### Parameters

- **image** (`np.ndarray`): Input ballot image in BGR format (from `cv2.imread`)
- **barcode_coords** (`Dict`): Barcode position and type
  - `x` (float): X coordinate in millimeters
  - `y` (float): Y coordinate in millimeters
  - `type` (str): Barcode type (`'QRCODE'`, `'CODE128'`, `'PDF417'`)
- **mm_to_px_ratio** (`float`, default=11.811): Conversion ratio for 300 DPI
- **metadata_fallback** (`str | None`, optional): Fallback document ID

#### Return Value

Dictionary with the following keys:

- **document_id** (`str | None`): Decoded document ID or `None`
- **decoder** (`str`): Which decoder succeeded (`'pyzbar'`, `'pyzxing'`, `'zxing_cli'`, `'metadata'`, or `'none'`)
- **confidence** (`float`): Confidence score (0.0-1.0)
- **decoded** (`bool`): Whether decode succeeded
- **source** (`str`): Decode source (`'visual'` or `'metadata'`)
- **rect** (`Dict | None`): ROI bounding box in pixels

### `extract_barcode_roi()`

Extract barcode region of interest with type-specific sizing.

```python
from barcode_decoder import extract_barcode_roi

roi, roi_rect = extract_barcode_roi(
    image,
    barcode_coords,
    mm_to_px_ratio=11.811,
    padding=50  # Extra pixels around region
)

# roi: np.ndarray - Extracted ROI image
# roi_rect: Dict - {'x': int, 'y': int, 'width': int, 'height': int}
```

### `preprocess_roi()`

Enhance barcode visibility with CLAHE and sharpening.

```python
from barcode_decoder import preprocess_roi

processed = preprocess_roi(
    roi,
    apply_clahe=True,    # Contrast enhancement
    apply_sharpen=True   # Sharpening filter
)
```

## CLI Usage

The module can be used as a standalone command-line tool:

```bash
# Basic usage (defaults to QR code at 99mm, 254mm)
python3 barcode_decoder.py ballot.png

# Specify coordinates
python3 barcode_decoder.py ballot.png 75 264

# Specify barcode type
python3 barcode_decoder.py ballot.png 99 254 QRCODE
python3 barcode_decoder.py ballot.png 50 280 CODE128
python3 barcode_decoder.py ballot.png 75 264 PDF417
```

Output is JSON:

```json
{
  "document_id": "BOKIAWAN-001-ballot-2025-10-28",
  "decoder": "pyzbar",
  "confidence": 0.9,
  "rect": {
    "x": 1119,
    "y": 2949,
    "width": 572,
    "height": 559
  },
  "decoded": true,
  "source": "visual"
}
```

## Integration with Appreciation Workflow

The barcode decoder is automatically integrated into `appreciate.py`:

```bash
# Run appreciation (automatically decodes barcode)
python3 appreciate.py ballot.png coordinates.json --threshold 0.3
```

Output includes barcode metadata:

```json
{
  "document_id": "BOKIAWAN-001-ballot-2025-10-28",
  "template_id": "TEMPLATE-001",
  "barcode": {
    "decoded": true,
    "decoder": "pyzbar",
    "confidence": 0.9,
    "source": "visual"
  },
  "results": [...]
}
```

### Coordinates Format

The barcode decoder reads from the `barcode` section of `coordinates.json`:

```json
{
  "barcode": {
    "document_barcode": {
      "x": 99,
      "y": 254,
      "type": "QRCODE",
      "data": "BALLOT-001-fallback"
    }
  }
}
```

## Supported Barcode Types

### QR Code (Recommended)

**Advantages:**
- Square shape (space-efficient)
- High data capacity (up to 4,296 alphanumeric characters)
- Built-in error correction (up to 30%)
- Rotation-independent
- Wide decoder support (pyzbar)

**Configuration:**
- ROI size: 40mm × 40mm
- Recommended size: 12-15mm square
- Positioning: Bottom center of ballot

**Example:**
```python
barcode_coords = {'x': 99, 'y': 254, 'type': 'QRCODE'}
```

### Code128

**Advantages:**
- Compact 1D barcode
- High density (alphanumeric)
- Native pyzbar support

**Limitations:**
- Requires horizontal alignment
- Less robust to damage

**Configuration:**
- ROI size: 60mm × 20mm
- Typical size: 40mm × 10mm

### PDF417

**Advantages:**
- High capacity 2D barcode
- Stacked format (compact vertically)

**Limitations:**
- Requires pyzxing or ZXing CLI (not supported by pyzbar)
- Java dependency for pyzxing
- Larger size requirement

**Configuration:**
- ROI size: 100mm × 30mm
- Typical size: 80mm × 25mm

## Configuration

### Ballot Template Configuration

Configure barcode in `config/omr-template.php`:

```php
'barcode' => [
    'enabled' => true,
    'type' => 'QRCODE',           // 'QRCODE', 'CODE128', 'PDF417'
    'width_scale' => 3,            // Width scaling (QR module size)
    'height' => 3,                 // Height scaling
    'bottom_offset_mm' => 25,      // Vertical position from bottom
],
```

### Overlay Visualization

Barcode regions are automatically visualized in overlay images with color coding:

- **Green** (`lime`): Successful visual decode
- **Yellow**: Metadata fallback
- **Red**: Failed decode

## Decoder Priority

The system tries decoders in this order:

1. **pyzbar**: Fast, native library (QR, Code128, Code39, EAN, UPC)
2. **pyzxing**: Java-based ZXing wrapper (PDF417, all formats)
3. **ZXing CLI**: System-level ZXing (all formats)
4. **Metadata**: Template fallback (always succeeds)

## Troubleshooting

### QR Code Not Decoding

**Symptom:** `decoded: false` with `decoder: none`

**Solutions:**
1. Check QR code size (minimum 12mm recommended)
2. Verify quiet zone (white space around code)
3. Check image DPI (300 DPI recommended)
4. Verify coordinates match actual QR position
5. Check contrast (QR should be black on white)

**Debug:**
```bash
# Extract and save ROI for manual inspection
python3 -c "
import cv2
from barcode_decoder import extract_barcode_roi
img = cv2.imread('ballot.png')
coords = {'x': 99, 'y': 254, 'type': 'QRCODE'}
roi, _ = extract_barcode_roi(img, coords)
cv2.imwrite('/tmp/qr_roi.png', roi)
print('ROI saved to /tmp/qr_roi.png')
"
open /tmp/qr_roi.png
```

### Metadata Fallback Always Used

**Symptom:** `source: metadata` even when QR code is visible

**Causes:**
- pyzbar not installed
- QR code too small (< 10mm)
- Poor image quality
- Incorrect coordinates

**Verify Installation:**
```bash
python3 -c "from pyzbar import pyzbar; print('pyzbar: OK')"
```

### PDF417 Not Working

**Symptom:** PDF417 barcodes not decoding

**Cause:** pyzbar doesn't support PDF417

**Solutions:**
1. Install pyzxing: `pip install pyzxing`
2. Install Java: `brew install openjdk`
3. Or switch to QR code (recommended)

### ROI Position Incorrect

**Symptom:** Barcode visualization shows wrong position

**Debug:**
```python
# Check coordinates conversion
mm_to_px = 300 / 25.4  # 11.811
x_mm, y_mm = 99, 254
x_px = x_mm * mm_to_px  # Should be ~1169
y_px = y_mm * mm_to_px  # Should be ~3000
print(f'Expected position: ({x_px:.0f}, {y_px:.0f}) pixels')
```

## Performance

### Decode Times (Typical)

- **pyzbar (QR)**: 10-50ms
- **pyzbar (Code128)**: 5-20ms
- **pyzxing (PDF417)**: 100-300ms (Java overhead)
- **ZXing CLI**: 200-500ms (process spawn overhead)

### Optimization Tips

1. Use QR codes for best performance (pyzbar native)
2. Avoid pyzxing/ZXing CLI if speed is critical
3. Cache decoded IDs when processing multiple images
4. Use appropriate ROI size (larger = slower)

## Examples

### Example 1: Batch Processing

```python
import cv2
from barcode_decoder import decode_barcode
import json
from pathlib import Path

ballot_dir = Path('ballots/')
results = []

for ballot_path in ballot_dir.glob('*.png'):
    image = cv2.imread(str(ballot_path))
    result = decode_barcode(
        image,
        {'x': 99, 'y': 254, 'type': 'QRCODE'},
        metadata_fallback=ballot_path.stem
    )
    results.append({
        'file': ballot_path.name,
        'document_id': result['document_id'],
        'decoder': result['decoder']
    })

print(json.dumps(results, indent=2))
```

### Example 2: Custom Preprocessing

```python
from barcode_decoder import extract_barcode_roi, preprocess_roi
from pyzbar import pyzbar
import cv2

image = cv2.imread('ballot.png')
coords = {'x': 99, 'y': 254, 'type': 'QRCODE'}

# Extract and preprocess
roi, _ = extract_barcode_roi(image, coords)
processed = preprocess_roi(roi, apply_clahe=True, apply_sharpen=True)

# Custom thresholding
_, binary = cv2.threshold(processed, 127, 255, cv2.THRESH_BINARY)

# Decode
barcodes = pyzbar.decode(binary)
for bc in barcodes:
    print(f'Decoded: {bc.data.decode("utf-8")}')
```

### Example 3: Verify Barcode Quality

```python
from barcode_decoder import decode_barcode
import cv2

image = cv2.imread('ballot.png')
coords = {'x': 99, 'y': 254, 'type': 'QRCODE'}

result = decode_barcode(image, coords)

if result['decoded']:
    if result['source'] == 'visual':
        if result['confidence'] >= 0.9:
            print('✓ Excellent decode')
        elif result['confidence'] >= 0.7:
            print('⚠ Acceptable decode')
        else:
            print('⚠ Low confidence decode')
    else:
        print('⚠ Using metadata fallback (visual decode failed)')
else:
    print('✗ Decode failed')
```

## Testing

### Unit Testing

```bash
# Test barcode decoder directly
cd packages/omr-appreciation/omr-python
python3 barcode_decoder.py ../../../storage/omr-output/BALLOT-001.png 99 254 QRCODE
```

### Integration Testing

```bash
# Run full OMR test suite (includes barcode decode)
bash scripts/test-omr-appreciation.sh
```

### Visual Verification

```bash
# Generate overlay with barcode visualization
python3 appreciate.py ballot.png coordinates.json --threshold 0.3 > results.json
php scripts/generate-overlay.php ballot.png results.json coordinates.json overlay.png
open overlay.png
```

Check for:
- Green rectangle around QR code (successful decode)
- Document ID displayed below QR code
- Decoder name and confidence percentage

## Best Practices

1. **Use QR codes** for maximum compatibility and performance
2. **Size appropriately**: Minimum 12mm, recommended 15mm
3. **Ensure quiet zone**: 4 modules of white space around QR code
4. **Position carefully**: Bottom center, avoid fiducial markers
5. **Test early**: Verify decode works before printing ballots
6. **Monitor fallback rate**: High metadata fallback rate indicates issues
7. **Include metadata**: Always provide fallback document ID

## See Also

- [OMR Appreciation Test Plan](simulation/OMR_APPRECIATION_TEST_PLAN_REVISED.md)
- [OMR Ground Truth Configuration](OMR_GROUND_TRUTH_CONFIGURATION.md)
- [PDF417 Capture Implementation](simulation/PDF417_CAPTURE_IMPLEMENTATION.md)
