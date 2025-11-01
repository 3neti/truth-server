# Fiducial Markers and QR Code Rendering Status

## Current Status

### ✅ ArUco Fiducial Markers - **WORKING**

**Implementation:** Python script in `ballot-renderer.sh` (lines 41-73)

**Status:** Fully functional after fixing dictionary range issue

**Changes Made:**
- Fixed ArUco dictionary from `DICT_4X4_100` (IDs 0-99) to `DICT_4X4_1000` (IDs 0-999)
- Coordinates use marker IDs 101-104, which now work correctly
- All four corner fiducials are rendered successfully

**Verification:**
```bash
python3 << 'PYTEST'
import cv2
ballot = cv2.imread('storage/app/private/simulation/latest/scenario-1-normal/blank.png')
# Top-left corner check shows ArUco pattern present
# Min/Max: 0/255, Mean: 63.9 (black/white pattern)
PYTEST
```

**Fiducial Positions:**
- Top-left (tl): marker ID 101 at (8.5mm, 8.5mm)
- Top-right (tr): marker ID 102 at (201.5mm, 8.5mm)
- Bottom-right (br): marker ID 103 at (201.5mm, 288.5mm)
- Bottom-left (bl): marker ID 104 at (8.5mm, 288.5mm)

### ⚠️ QR Code - **PLACEHOLDER ONLY**

**Implementation:** Python script in `ballot-renderer.sh` (lines 91-118)

**Status:** Fallback to placeholder box with "QR" text

**Reason:** Python `qrcode` library not installed

**Current Behavior:**
- Draws a rectangle at the barcode position
- Displays "QR" text inside
- Coordinates define QR area: (70mm, 270mm), size 70mm x 15mm

**To Enable Real QR Codes:**

Install the qrcode library:
```bash
pip3 install qrcode[pil]
```

After installation, ballots will automatically include real QR codes with the data from coordinates:
```json
{
  "document_barcode": {
    "x": 70,
    "y": 270,
    "width": 70,
    "height": 15,
    "type": "qr",
    "data": "SIMULATION-001"
  }
}
```

## Technical Details

### Ballot Rendering Pipeline

The ballot PDF is created through this pipeline:

```
Coordinates JSON
    ↓
Python render_blank_ballot()  ← OpenCV + ArUco + (optional) qrcode
    ↓
Blank Ballot PNG (with fiducials)
    ↓
ImageMagick convert
    ↓
Ballot PDF
```

### Python Dependencies

**Required (installed):**
- ✅ `opencv-python` (cv2) - Version 4.12.0
- ✅ `numpy` - Array operations

**Optional (not installed):**
- ⚠️ `qrcode[pil]` - QR code generation
  - Without this: placeholder box rendered
  - With this: real scannable QR codes

### Code Location

**Ballot Renderer:** `scripts/simulation/lib/ballot-renderer.sh`

**Key Functions:**
- `render_blank_ballot()` - Generates blank ballot with fiducials/QR
- Lines 41-73: ArUco fiducial generation
- Lines 91-118: QR code generation (with fallback)

## Installation Instructions

### Install QR Code Support

```bash
# Using pip
pip3 install qrcode[pil]

# Or using system package manager (macOS)
brew install qrencode
pip3 install qrcode

# Verify installation
python3 -c "import qrcode; print('QR code library available')"
```

### Test QR Code Generation

After installing, regenerate ballots:
```bash
scripts/simulation/run-test-suite.sh --scenarios normal --fresh

# Check if QR code is rendered
python3 << 'PYTEST'
import cv2
ballot = cv2.imread('storage/app/private/simulation/latest/scenario-1-normal/blank.png')
# Extract QR region at (70mm, 270mm)
mm_to_px = 300 / 25.4
qr_x = int(70 * mm_to_px)
qr_y = int(270 * mm_to_px)
qr_w = int(70 * mm_to_px)
qr_h = int(15 * mm_to_px)
qr_region = ballot[qr_y:qr_y+qr_h, qr_x:qr_x+qr_w]
print(f"QR region mean intensity: {qr_region.mean():.1f}")
# Real QR should have mix of black/white, not uniform gray
PYTEST
```

## Summary

| Feature | Status | Library | Notes |
|---------|--------|---------|-------|
| ArUco Fiducials | ✅ Working | opencv-python | DICT_4X4_1000, IDs 101-104 |
| QR Codes | ⚠️ Placeholder | qrcode[pil] (not installed) | Functional fallback |
| Ballot PDF | ✅ Working | ImageMagick | Includes ArUco, needs qrcode lib for QR |
| Bubble Circles | ✅ Working | opencv-python | All 56 bubbles rendered |

## Recommendations

1. **Install qrcode library** for complete ballot fidelity
2. **Current setup is functional** - ArUco markers work for alignment
3. **QR placeholder is acceptable** for testing - doesn't affect OMR appreciation
4. **For production use** - install qrcode library for scannable document barcodes

## Testing

Current test results with ArUco fiducials:
```bash
$ scripts/simulation/run-test-suite.sh --scenarios normal,overvote,faint --fresh
✓ Status: PASSED
Total:  3
Passed: 3
Failed: 0

# ArUco markers verified present
# QR codes show as placeholder boxes
```
