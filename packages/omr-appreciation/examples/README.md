# OMR Appreciation Examples

This directory contains example scripts demonstrating OMR (Optical Mark Recognition) fiducial detection and alignment.

## Live Fiducial Appreciation Demo

**Script:** `live_fiducial_appreciation.py`

A real-time webcam-based fiducial marker detection and ballot alignment tool.

### Requirements

```bash
pip3 install opencv-python numpy

# Optional: For AprilTag support
pip3 install pupil-apriltags
# OR
pip3 install apriltag
```

### Quick Start

#### Test with ArUco Markers (Default)

```bash
cd packages/omr-appreciation/examples

# Basic ArUco detection (default IDs: 101-104)
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --dict DICT_6X6_250 \
  --ids 101,102,103,104 \
  --size 2480x3508

# Show aligned/warped view and save last frame
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --dict DICT_6X6_250 \
  --ids 101,102,103,104 \
  --size 2480x3508 \
  --show-warp \
  --save
```

#### Test with AprilTag Markers

```bash
# Requires apriltag or pupil-apriltags library
python3 live_fiducial_appreciation.py \
  --mode apriltag \
  --ids 0,1,2,3 \
  --size 2480x3508 \
  --show-warp
```

### Usage

```
python3 live_fiducial_appreciation.py [OPTIONS]

Options:
  --mode {aruco,apriltag}    Detection backend (default: aruco)
  --dict <DICT_NAME>         ArUco dictionary (e.g., DICT_6X6_250)
  --size <WxH>               Output canvas size (e.g., 2480x3508 for A4@300DPI)
  --ids <TL,TR,BR,BL>        Expected corner IDs (default: 101,102,103,104)
  --camera <INDEX>           Camera index (default: 0)
  --show-warp                Show warped/aligned view in separate window
  --save                     Save last warped frame to aligned_last.png
```

### What It Does

1. **Captures live video** from your webcam
2. **Detects fiducial markers** (ArUco or AprilTag) in each frame
3. **Computes homography** for perspective correction
4. **Shows detection status**:
   - Green boxes around detected markers with IDs
   - "H: OK" when ≥3 markers detected (homography computed)
   - "H: MISSING" when <3 markers detected
5. **Displays warped view** (optional) showing aligned ballot
6. **Saves aligned frame** (optional) for further processing

### Controls

- **ESC**: Exit application
- When `--save` is enabled, last warped frame is saved on exit

### Testing Workflow

#### 1. Verify ArUco Markers Generated

```bash
# Check if markers exist
ls -lh ../../../resources/fiducials/aruco/

# Generate if missing
cd ../../../scripts
python3 generate_aruco_markers.py --ids 101,102,103,104
```

#### 2. Print Test Ballot

Generate a test ballot with fiducial markers:

```bash
# Set environment for ArUco mode
export OMR_FIDUCIAL_MODE=aruco
export OMR_ARUCO_DICTIONARY=DICT_6X6_250

# Generate ballot (using your existing ballot generation command)
php artisan omr:generate-ballot
```

#### 3. Run Live Detection

Hold printed ballot up to camera and run the demo:

```bash
cd packages/omr-appreciation/examples
python3 live_fiducial_appreciation.py \
  --mode aruco \
  --ids 101,102,103,104 \
  --show-warp \
  --save
```

#### 4. Verify Detection Quality

Watch for:
- ✅ All 4 markers detected (green boxes with correct IDs)
- ✅ "H: OK" status displayed
- ✅ Warped view shows properly aligned ballot (if --show-warp enabled)
- ✅ Detection works at various angles and distances
- ✅ Detection robust to lighting conditions

#### 5. Test Skew/Rotation Robustness

While running the demo:
- Tilt ballot left/right (test rotation)
- Skew ballot (test shear)
- Move closer/farther (test scale)
- Change lighting (test robustness)

### Output

When using `--save`, the script generates:
- **aligned_last.png**: Last warped frame captured before exit

This image can be used for:
- Visual quality inspection
- Input to bubble detection pipeline
- Comparing different fiducial modes

### Troubleshooting

#### No Markers Detected

**Problem:** Green boxes don't appear on markers

**Solutions:**
1. Verify marker IDs match: `--ids 101,102,103,104`
2. Check dictionary matches: `--dict DICT_6X6_250`
3. Improve lighting (even, no glare)
4. Ensure markers fully visible (not cropped)
5. Move closer to camera
6. Print markers larger (20mm minimum)

#### Wrong IDs Detected

**Problem:** Detects different IDs than expected

**Solutions:**
1. Regenerate markers with correct IDs:
   ```bash
   python3 ../../../scripts/generate_aruco_markers.py --ids 101,102,103,104
   ```
2. Verify ballot PDF contains correct markers
3. Check environment variables match config

#### Homography Fails ("H: MISSING")

**Problem:** Fewer than 3 markers detected

**Solutions:**
1. Ensure all 4 corners visible in frame
2. Adjust camera angle to see full ballot
3. Improve print quality (300+ DPI)
4. Use matte paper (glossy causes reflections)

#### Warped View Distorted

**Problem:** Aligned view looks skewed or stretched

**Solutions:**
1. Verify `--size` matches ballot dimensions:
   - A4 @ 300 DPI: `2480x3508`
   - Letter @ 300 DPI: `2550x3300`
2. Ensure markers at correct positions on ballot
3. Check for marker detection errors (wrong IDs)

### Integration with OMR Pipeline

This demo script validates that fiducial detection works. To integrate into full OMR appreciation:

```bash
# After verifying live detection works, test with appreciation pipeline
cd ../../omr-python
python3 appreciate.py \
  --input ../examples/aligned_last.png \
  --template coordinates.json \
  --threshold 0.3
```

### See Also

- **[FIDUCIAL_TESTING_GUIDE.md](../../../resources/docs/omr-appreciation/FIDUCIAL_TESTING_GUIDE.md)** - Complete testing guide
- **[LIVE_DEMO_INTEGRATION_GUIDE.md](../../../resources/docs/omr-appreciation/LIVE_DEMO_INTEGRATION_GUIDE.md)** - Integration details
- **[APRILTAG_ARUCO_INTEGRATION.md](../../../resources/docs/omr-appreciation/APRILTAG_ARUCO_INTEGRATION.md)** - Configuration reference

---

**Last Updated:** 2025-10-28  
**Version:** 1.0
