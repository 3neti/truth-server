# Barcode Decoder Test Scenarios

## Overview

This document defines comprehensive test scenarios for the barcode decoder module to ensure robust document ID extraction across various conditions.

## Test Matrix

| Scenario ID | Description | Barcode Type | Expected Result | Priority |
|-------------|-------------|--------------|-----------------|----------|
| BT-01 | Successful QR decode (visual) | QR Code | `decoded: true, source: visual` | P0 |
| BT-02 | Successful Code128 decode | Code128 | `decoded: true, source: visual` | P1 |
| BT-03 | Successful PDF417 decode | PDF417 | `decoded: true, source: visual` | P2 |
| BT-04 | QR decode with 0° rotation | QR Code | `decoded: true, source: visual` | P0 |
| BT-05 | QR decode with 90° rotation | QR Code | `decoded: true, source: visual` | P0 |
| BT-06 | QR decode with 180° rotation | QR Code | `decoded: true, source: visual` | P0 |
| BT-07 | QR decode with 270° rotation | QR Code | `decoded: true, source: visual` | P0 |
| BT-08 | Metadata fallback (missing barcode) | QR Code | `decoded: true, source: metadata` | P0 |
| BT-09 | Metadata fallback (damaged barcode) | QR Code | `decoded: true, source: metadata` | P1 |
| BT-10 | Barcode with occlusion (partial) | QR Code | `decoded: true` (error correction) | P1 |
| BT-11 | Barcode with occlusion (severe) | QR Code | `decoded: true, source: metadata` | P1 |
| BT-12 | Faint QR code (low contrast) | QR Code | `decoded: true` or metadata | P1 |
| BT-13 | Oversized QR code | QR Code | `decoded: true, source: visual` | P2 |
| BT-14 | Undersized QR code (< 10mm) | QR Code | `decoded: false` or metadata | P2 |
| BT-15 | QR with incorrect positioning | QR Code | `decoded: false, source: metadata` | P1 |
| BT-16 | Multiple barcodes (only one valid) | QR Code | Extract correct document ID | P2 |
| BT-17 | Barcode with skew (< 15°) | QR Code | `decoded: true, source: visual` | P1 |
| BT-18 | Barcode with perspective distortion | QR Code | `decoded: true` or metadata | P2 |
| BT-19 | Low DPI scan (150 DPI) | QR Code | `decoded: true` or metadata | P2 |
| BT-20 | High DPI scan (600 DPI) | QR Code | `decoded: true, source: visual` | P2 |

## Scenario Details

### BT-01: Successful QR Decode (Visual) ✅ IMPLEMENTED

**Objective:** Verify QR code can be decoded visually under ideal conditions

**Setup:**
- Generate ballot with QR code (12mm × 12mm)
- Document ID: `BOKIAWAN-001-ballot-2025-10-28`
- Position: x=99mm, y=254mm
- Image quality: 300 DPI, high contrast

**Expected Result:**
```json
{
  "document_id": "BOKIAWAN-001-ballot-2025-10-28",
  "decoded": true,
  "decoder": "pyzbar",
  "confidence": 0.9,
  "source": "visual"
}
```

**Status:** ✅ Passing (tested with existing test suite)

---

### BT-04-07: QR Decode with Cardinal Rotations ✅ IMPLEMENTED

**Objective:** Verify QR codes decode correctly at all rotations

**Setup:**
- Generate ballots rotated at 0°, 90°, 180°, 270°
- Same QR code and positioning as BT-01
- Use rotation fixtures from scenario-8-cardinal-rotations

**Expected Result:**
- All rotations should decode successfully
- QR codes are rotation-independent

**Status:** ✅ Partial coverage (rotation scenarios exist, need barcode validation)

**Implementation:**
```bash
# Test rotated ballots with barcode decode
for angle in 0 90 180 270; do
  python3 appreciate.py \
    "scenario-8/rot_${angle}/blank_filled.png" \
    coordinates.json \
    --threshold 0.3 | \
    python3 -c "import sys, json; d=json.load(sys.stdin); \
      print(f'${angle}°: decoded={d[\"barcode\"][\"decoded\"]}, \
        decoder={d[\"barcode\"][\"decoder\"]}')"
done
```

---

### BT-08: Metadata Fallback (Missing Barcode) ✅ IMPLEMENTED

**Objective:** Verify system falls back to metadata when visual decode fails

**Setup:**
- Generate ballot WITHOUT rendering QR code
- Coordinates still define barcode position
- Metadata includes fallback document ID

**Expected Result:**
```json
{
  "document_id": "CURRIMAO-001-ballot-2025-05-12",
  "decoded": true,
  "decoder": "metadata",
  "confidence": 1.0,
  "source": "metadata"
}
```

**Status:** ✅ Passing (observed in test runs with non-QR ballots)

---

### BT-09: Metadata Fallback (Damaged Barcode)

**Objective:** Verify fallback when barcode is damaged/unreadable

**Setup:**
- Generate ballot with QR code
- Apply damage simulation:
  - Random black/white noise over QR region
  - 30-50% of QR code area affected
- Ensure damage exceeds error correction capacity

**Implementation:**
```python
# Damage simulator
import cv2
import numpy as np

def damage_qr_region(image, barcode_coords, damage_pct=0.4):
    """Apply random damage to QR code region"""
    mm_to_px = 11.811
    x = int(barcode_coords['x'] * mm_to_px)
    y = int(barcode_coords['y'] * mm_to_px)
    size = int(15 * mm_to_px)  # 15mm QR code
    
    roi = image[y:y+size, x:x+size]
    mask = np.random.random(roi.shape[:2]) < damage_pct
    roi[mask] = np.random.choice([0, 255], size=mask.sum())
    
    return image
```

**Expected Result:**
- `decoded: true`
- `source: metadata`
- Overlay shows red rectangle (failed visual decode)

---

### BT-10: Barcode with Partial Occlusion

**Objective:** Test QR error correction with minor damage

**Setup:**
- Generate ballot with QR code
- Apply small occlusion (10-20% of area)
- Occlusion types:
  - Black rectangle overlay
  - White rectangle overlay
  - Smudge simulation

**Expected Result:**
- QR error correction should recover (Level M = 15% recovery)
- `decoded: true, source: visual`
- `confidence: >= 0.7`

---

### BT-11: Barcode with Severe Occlusion

**Objective:** Test fallback when occlusion exceeds error correction

**Setup:**
- Apply 40-60% occlusion to QR code
- Exceeds typical error correction capacity

**Expected Result:**
- `decoded: true`
- `source: metadata` (visual decode fails)

---

### BT-12: Faint QR Code (Low Contrast)

**Objective:** Test decode with poor print quality

**Setup:**
- Generate QR code with reduced contrast
- Simulate faint printing:
  - Black modules → dark gray (RGB 80, 80, 80)
  - White modules → light gray (RGB 200, 200, 200)

**Implementation:**
```python
def simulate_faint_qr(image, barcode_coords, fade_factor=0.6):
    """Reduce QR code contrast"""
    mm_to_px = 11.811
    x = int(barcode_coords['x'] * mm_to_px)
    y = int(barcode_coords['y'] * mm_to_px)
    size = int(15 * mm_to_px)
    
    roi = image[y:y+size, x:x+size].astype(float)
    # Fade towards mid-gray (127)
    roi = roi * (1 - fade_factor) + 127 * fade_factor
    image[y:y+size, x:x+size] = roi.astype(np.uint8)
    
    return image
```

**Expected Result:**
- Preprocessor (CLAHE + sharpening) should enhance
- `decoded: true` (possibly lower confidence)
- If fails: `source: metadata`

---

### BT-15: Incorrect Positioning

**Objective:** Test behavior when QR coordinates don't match actual position

**Setup:**
- Generate ballot with QR at x=99mm, y=254mm
- Configure coordinates with wrong position (x=80mm, y=270mm)
- ROI won't capture actual QR code

**Expected Result:**
- `decoded: true`
- `source: metadata`
- Overlay shows barcode rectangle in wrong location

---

### BT-17: Barcode with Skew

**Objective:** Test decode with ballot skew/rotation

**Setup:**
- Generate ballot rotated 5-10° (beyond alignment tolerance)
- QR code will be skewed relative to expected position

**Expected Result:**
- QR codes are rotation-invariant (should decode)
- Verify with skewed fixtures from scenario-5-quality-gates

**Implementation:**
```bash
# Use existing skew fixtures
python3 appreciate.py \
  "fixtures/filled-distorted/S1_shear_2deg.png" \
  coordinates.json \
  --threshold 0.3 --no-align
```

---

### BT-18: Perspective Distortion

**Objective:** Test decode with perspective transformation

**Setup:**
- Apply perspective warp to ballot
- Simulates camera angle during scanning
- QR code will appear trapezoidal instead of square

**Expected Result:**
- pyzbar should handle moderate perspective
- Severe distortion → metadata fallback

---

### BT-19/20: DPI Variation

**Objective:** Test decode across different scan resolutions

**Setup:**
- Generate ballots at 150 DPI, 300 DPI, 600 DPI
- Same physical QR size (12mm)
- Pixel size varies: ~71px, ~142px, ~283px

**Expected Results:**
- 150 DPI: May fail for small QR (< 12mm)
- 300 DPI: ✅ Optimal
- 600 DPI: ✅ Excellent (higher quality)

---

## Test Implementation

### Directory Structure

```
tests/Feature/BarcodeDecoder/
├── BarcodeBasicTest.php
├── BarcodeRotationTest.php
├── BarcodeFallbackTest.php
├── BarcodeDamageTest.php
└── BarcodeQualityTest.php

storage/app/tests/barcode/
├── fixtures/
│   ├── perfect/
│   │   ├── qr-12mm.png
│   │   ├── qr-15mm.png
│   │   └── code128.png
│   ├── rotated/
│   │   ├── qr-000deg.png
│   │   ├── qr-090deg.png
│   │   ├── qr-180deg.png
│   │   └── qr-270deg.png
│   ├── damaged/
│   │   ├── qr-partial-damage.png
│   │   ├── qr-severe-damage.png
│   │   └── qr-faint.png
│   └── dpi/
│       ├── qr-150dpi.png
│       ├── qr-300dpi.png
│       └── qr-600dpi.png
└── results/
```

### Test Harness

```php
<?php
// tests/Feature/BarcodeDecoder/BarcodeBasicTest.php

namespace Tests\Feature\BarcodeDecoder;

use Tests\TestCase;

class BarcodeBasicTest extends TestCase
{
    public function test_bt01_successful_qr_decode_visual()
    {
        $ballotPath = storage_path('app/tests/barcode/fixtures/perfect/qr-12mm.png');
        $coordsPath = storage_path('app/tests/barcode/coordinates.json');
        
        $output = $this->runAppreciation($ballotPath, $coordsPath);
        $result = json_decode($output, true);
        
        $this->assertTrue($result['barcode']['decoded']);
        $this->assertEquals('visual', $result['barcode']['source']);
        $this->assertEquals('pyzbar', $result['barcode']['decoder']);
        $this->assertGreaterThanOrEqual(0.7, $result['barcode']['confidence']);
    }
    
    public function test_bt08_metadata_fallback_missing_barcode()
    {
        $ballotPath = storage_path('app/tests/barcode/fixtures/damaged/no-qr.png');
        $coordsPath = storage_path('app/tests/barcode/coordinates.json');
        
        $output = $this->runAppreciation($ballotPath, $coordsPath);
        $result = json_decode($output, true);
        
        $this->assertTrue($result['barcode']['decoded']);
        $this->assertEquals('metadata', $result['barcode']['source']);
        $this->assertNotEmpty($result['document_id']);
    }
    
    protected function runAppreciation(string $ballot, string $coords): string
    {
        $command = sprintf(
            'python3 packages/omr-appreciation/omr-python/appreciate.py %s %s --threshold 0.3 --no-align',
            escapeshellarg($ballot),
            escapeshellarg($coords)
        );
        
        return shell_exec($command);
    }
}
```

### Fixture Generator

```php
<?php
// tests/Helpers/BarcodeFixtureGenerator.php

namespace Tests\Helpers;

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;

class BarcodeFixtureGenerator
{
    public static function generateQrFixtures(): void
    {
        $basePath = storage_path('app/tests/barcode/fixtures');
        
        // Perfect QR codes (different sizes)
        self::generateQr('BALLOT-001-12mm', $basePath . '/perfect/qr-12mm.png', 12);
        self::generateQr('BALLOT-002-15mm', $basePath . '/perfect/qr-15mm.png', 15);
        
        // Rotated QR codes
        foreach ([0, 90, 180, 270] as $angle) {
            $id = sprintf('BALLOT-ROT-%03d', $angle);
            $path = sprintf('%s/rotated/qr-%03ddeg.png', $basePath, $angle);
            self::generateRotatedQr($id, $path, 12, $angle);
        }
        
        // Damaged QR codes
        self::generateDamagedQr('BALLOT-DAMAGED-001', 
            $basePath . '/damaged/qr-partial-damage.png', 0.2);
        self::generateDamagedQr('BALLOT-DAMAGED-002', 
            $basePath . '/damaged/qr-severe-damage.png', 0.5);
    }
    
    protected static function generateQr(string $data, string $path, int $sizeMm): void
    {
        $qr = QrCode::create($data)
            ->setSize($sizeMm * 11.811)  // Convert mm to pixels at 300 DPI
            ->setMargin(0);
        
        $writer = new PngWriter();
        $result = $writer->write($qr);
        $result->saveToFile($path);
    }
    
    protected static function generateRotatedQr(
        string $data, 
        string $path, 
        int $sizeMm, 
        int $angle
    ): void {
        // Generate QR, then rotate with canvas
        $tempPath = $path . '.tmp.png';
        self::generateQr($data, $tempPath, $sizeMm);
        
        // Rotate using Imagick
        $imagick = new \Imagick($tempPath);
        $imagick->rotateImage(new \ImagickPixel('white'), $angle);
        $imagick->writeImage($path);
        $imagick->destroy();
        
        unlink($tempPath);
    }
    
    protected static function generateDamagedQr(
        string $data, 
        string $path, 
        float $damagePct
    ): void {
        // Generate perfect QR, then damage it with Python
        $tempPath = $path . '.tmp.png';
        self::generateQr($data, $tempPath, 12);
        
        $pythonScript = <<<PYTHON
import cv2
import numpy as np

img = cv2.imread('$tempPath')
h, w = img.shape[:2]

# Apply random damage
mask = np.random.random((h, w)) < $damagePct
img[mask] = np.random.choice([0, 255], size=mask.sum())

cv2.imwrite('$path', img)
PYTHON;
        
        file_put_contents('/tmp/damage_qr.py', $pythonScript);
        shell_exec('python3 /tmp/damage_qr.py');
        unlink($tempPath);
    }
}
```

## Running Tests

### Full Test Suite

```bash
# Run all barcode tests
php artisan test tests/Feature/BarcodeDecoder/

# Run specific scenario
php artisan test --filter=test_bt01_successful_qr_decode_visual

# Generate fixtures first
php artisan tinker --execute="Tests\Helpers\BarcodeFixtureGenerator::generateQrFixtures()"
```

### Manual Testing

```bash
# Test specific fixture
python3 packages/omr-appreciation/omr-python/barcode_decoder.py \
  storage/app/tests/barcode/fixtures/perfect/qr-12mm.png \
  99 254 QRCODE

# Test with appreciation workflow
python3 packages/omr-appreciation/omr-python/appreciate.py \
  storage/app/tests/barcode/fixtures/rotated/qr-090deg.png \
  storage/app/tests/barcode/coordinates.json \
  --threshold 0.3 --no-align
```

## Success Criteria

| Priority | Minimum Pass Rate |
|----------|-------------------|
| P0 | 100% (all must pass) |
| P1 | 90% (critical scenarios) |
| P2 | 70% (nice-to-have) |

### P0 Scenarios (Must Pass)
- BT-01: Visual QR decode
- BT-04-07: Cardinal rotations
- BT-08: Metadata fallback

### P1 Scenarios (Critical)
- BT-09: Damaged barcode fallback
- BT-10: Partial occlusion
- BT-12: Faint QR code
- BT-15: Incorrect positioning
- BT-17: Skew tolerance

### P2 Scenarios (Nice-to-Have)
- BT-02-03: Alternative barcode types
- BT-13-14: Size variation
- BT-16: Multiple barcodes
- BT-18-20: Advanced scenarios

## Future Enhancements

1. **Automated fixture generation**: Generate all fixtures programmatically
2. **Performance benchmarking**: Track decode times across scenarios
3. **Stress testing**: Test with 1000+ ballot images
4. **Real-world validation**: Test with actual scanned ballots
5. **Decoder comparison**: Benchmark pyzbar vs pyzxing vs ZXing CLI
6. **Error recovery metrics**: Track fallback rates in production

## See Also

- [Barcode Decoder Guide](../BARCODE_DECODER_GUIDE.md)
- [OMR Appreciation Test Plan](OMR_APPRECIATION_TEST_PLAN_REVISED.md)
- [OMR Ground Truth Configuration](../OMR_GROUND_TRUTH_CONFIGURATION.md)
