# Fiducial Marker Testing - Quick Start

## Overview

The test suite now generates ballots with **real fiducial markers** (ArUco, AprilTag, or black squares) that you can visually inspect in the test artifacts.

---

## 🚀 Quick Start

### 1. Run Tests with ArUco Markers

**No manual setup needed!** The test script automatically generates ArUco markers if missing.

```bash
# Set fiducial mode
export OMR_FIDUCIAL_MODE=aruco

# Run test suite (markers generated automatically if needed)
./scripts/test-omr-appreciation.sh
```

You'll see:
```
⚠ ArUco markers not found, generating...
✓ ArUco markers generated
```

### 2. View the Fiducial Markers

```bash
# Navigate to latest test run
cd storage/app/tests/omr-appreciation/latest

# View ballot with ArUco markers in corners
open scenario-4-fiducials/blank_with_fiducials.png
open scenario-4-fiducials/ballot_with_fiducials.pdf

# View filled ballot (with simulated marks)
open scenario-4-fiducials/blank_with_fiducials_filled.png

# Check fiducial detection logs
cat scenario-4-fiducials/fiducial_debug.log
```

---

## 📁 Scenario 4 Output

```
scenario-4-fiducials/
├── blank_with_fiducials.png         # ✨ See the ArUco markers here!
├── ballot_with_fiducials.pdf        # ✨ PDF with embedded markers
├── blank_with_fiducials_filled.png  # Ballot with filled bubbles
├── fiducial_debug.log               # Fiducial detection debug output
├── appreciation_output.txt          # Appreciation script output (if ran)
├── appreciation_error.txt           # Errors (if appreciation failed)
├── results.json                     # Appreciation results (if successful)
└── metadata.json                    # Test scenario metadata
```

---

## 🎨 What You'll See

### Black Squares (Default)
```
┌─────────────────────────────────┐
│ ■                             ■ │  ← 10mm black squares
│                                 │
│          BALLOT CONTENT         │
│                                 │
│ ■                             ■ │
└─────────────────────────────────┘
```

### ArUco Markers
```
┌─────────────────────────────────┐
│ [QR-101]               [QR-102] │  ← QR-like patterns
│                                 │     with unique IDs
│          BALLOT CONTENT         │
│                                 │
│ [QR-104]               [QR-103] │
└─────────────────────────────────┘
```

### AprilTag Markers
```
┌─────────────────────────────────┐
│ [TAG-0]                 [TAG-1] │  ← Similar to ArUco
│                                 │     but different encoding
│          BALLOT CONTENT         │
│                                 │
│ [TAG-3]                 [TAG-2] │
└─────────────────────────────────┘
```

---

## 🔄 Try Different Modes

### Test Black Squares (Default)
```bash
unset OMR_FIDUCIAL_MODE
./scripts/test-omr-appreciation.sh
cd storage/app/tests/omr-appreciation/latest
open scenario-4-fiducials/blank_with_fiducials.png
```

### Test ArUco Markers
```bash
export OMR_FIDUCIAL_MODE=aruco
./scripts/test-omr-appreciation.sh
cd storage/app/tests/omr-appreciation/latest
open scenario-4-fiducials/blank_with_fiducials.png
```

### Test AprilTag Markers
```bash
# First generate AprilTag markers
python3 scripts/generate_apriltag_markers.py

# Run tests
export OMR_FIDUCIAL_MODE=apriltag
./scripts/test-omr-appreciation.sh
cd storage/app/tests/omr-appreciation/latest
open scenario-4-fiducials/blank_with_fiducials.png
```

---

## 📊 Compare Modes Side-by-Side

```bash
# Run with black squares
unset OMR_FIDUCIAL_MODE
./scripts/test-omr-appreciation.sh
RUN1=$(readlink storage/app/tests/omr-appreciation/latest | xargs basename)

# Run with ArUco
export OMR_FIDUCIAL_MODE=aruco
./scripts/test-omr-appreciation.sh
RUN2=$(readlink storage/app/tests/omr-appreciation/latest | xargs basename)

# Compare visually
open storage/app/tests/omr-appreciation/runs/${RUN1}/scenario-4-fiducials/blank_with_fiducials.png
open storage/app/tests/omr-appreciation/runs/${RUN2}/scenario-4-fiducials/blank_with_fiducials.png
```

---

## 🔍 Inspect Fiducial Detection

### Check if Detection Worked

```bash
cd storage/app/tests/omr-appreciation/latest/scenario-4-fiducials

# Check detection log
cat fiducial_debug.log

# Check metadata
cat metadata.json | jq
```

### Example metadata.json

```json
{
  "scenario": "fiducials",
  "description": "Fiducial marker detection and alignment test",
  "fiducial_mode": "aruco",
  "fiducial_corners_detected": 4,
  "bubbles_filled": [
    "PRESIDENT_LD_001",
    "VICE-PRESIDENT_VD_002",
    "SENATOR_JD_001"
  ],
  "timestamp": "2025-01-28T14:30:25+00:00",
  "note": "Check blank_with_fiducials.png to see the fiducial markers"
}
```

---

## 🎯 What to Look For

### In blank_with_fiducials.png

✅ **Markers in all 4 corners** (top-left, top-right, bottom-left, bottom-right)  
✅ **Clear, high-contrast patterns** (not blurry or faded)  
✅ **Proper spacing from edges** (~10mm margin)  
✅ **White quiet zone** around each marker

### In ballot_with_fiducials.pdf

✅ **Open in PDF reader** to see high-resolution markers  
✅ **Zoom in to inspect details** of ArUco/AprilTag patterns  
✅ **Print test page** to verify print quality

---

## 🛠️ Advanced: Manual Marker Generation

**Optional** - The test script generates markers automatically, but you can generate them manually if needed:

### Generate ArUco Markers (200px, optimized)
```bash
python3 scripts/generate_aruco_markers.py --size 200
```

### Generate ArUco Markers (400px, default)
```bash
python3 scripts/generate_aruco_markers.py
```

**Output:**
```
✓ marker_101.png (TL) - 3.8 KB
✓ marker_102.png (TR) - 3.5 KB
✓ marker_103.png (BR) - 3.6 KB
✓ marker_104.png (BL) - 3.9 KB
```

### Generate AprilTag Markers
```bash
python3 scripts/generate_apriltag_markers.py
```

---

## 🐛 Troubleshooting

### Markers Not Visible

**Problem:** No markers in corners of blank_with_fiducials.png

**Solution:**
```bash
# Check if markers exist
ls -la storage/app/fiducial-markers/aruco/

# If empty, they should have been auto-generated
# Check test output for generation errors

# Manually regenerate if needed
python3 scripts/generate_aruco_markers.py --size 200

# For AprilTag
python3 scripts/generate_apriltag_markers.py
```

### Wrong Marker Type

**Problem:** Shows black squares instead of ArUco

**Solution:**
```bash
# Check environment variable
echo $OMR_FIDUCIAL_MODE

# Clear Laravel config cache
php artisan config:clear

# Re-run tests
export OMR_FIDUCIAL_MODE=aruco
./scripts/test-omr-appreciation.sh
```

### Detection Failed

**Problem:** fiducial_debug.log shows errors

**Solution:**
```bash
# Check OpenCV installation
python3 -c "import cv2; print(cv2.aruco)"

# Install if missing
pip3 install opencv-contrib-python

# Check marker files
ls -lh resources/fiducials/aruco/
```

---

## 📚 Next Steps

1. **Visual Verification** - Inspect the generated markers in PNG/PDF
2. **Print Test** - Print the PDF and scan it to test real-world detection
3. **Production Deployment** - If satisfied, use ArUco for production ballots

### For Production Use

```bash
# 1. Set in .env
echo "OMR_FIDUCIAL_MODE=aruco" >> .env

# 2. Generate ballots (markers embedded automatically)
php artisan omr:generate-ballot

# 3. Test with real scanned ballot
python3 packages/omr-appreciation/omr-python/appreciate.py \
  scanned_ballot.png \
  coords.json \
  --threshold 0.3
```

---

## 📖 Full Documentation

- **[FIDUCIAL_TESTING_GUIDE.md](resources/docs/omr-appreciation/FIDUCIAL_TESTING_GUIDE.md)** - Complete testing guide
- **[APRILTAG_ARUCO_INTEGRATION.md](resources/docs/omr-appreciation/APRILTAG_ARUCO_INTEGRATION.md)** - Integration details
- **[FIDUCIAL_IMPLEMENTATION_SUMMARY.md](resources/docs/omr-appreciation/FIDUCIAL_IMPLEMENTATION_SUMMARY.md)** - Implementation summary

---

**Last Updated:** 2025-01-28  
**Status:** ✅ Ready to use
