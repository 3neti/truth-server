# Ballot Rotation Testing

## Overview

The OMR appreciation system has been enhanced to support ballot rotation detection and processing using ArUco fiducial markers. This document describes the current capabilities and future work.

## Current Status: ✅ Cardinal Rotations Fully Supported

### Working (100% accuracy):
- **0° (upright)**: Perfect detection
- **90° (clockwise)**: Perfect detection  
- **180° (inverted)**: Perfect detection
- **270° (counter-clockwise)**: Perfect detection

**Result: 4/8 rotations passing = 100% cardinal rotation support**

## Implementation Details

### Unified Canvas-Based Rotation
All rotations now use a consistent approach:
1. Calculate canvas size: `diagonal = sqrt(w² + h²) + 200px`
2. Center original image in white canvas
3. Rotate around canvas center
4. ArUco markers remain visible for detection

### Rotation-Aware Quality Metrics
The quality check system (`packages/omr-appreciation/omr-python/quality_metrics.py`) now includes rotation-aware shear tolerance:

- For rotations >10°, shear is expected to match rotation angle
- Tolerance: ±1° between detected rotation and shear
- This prevents diagonal rotations from being incorrectly flagged as "distorted"

### Code Changes
1. **`scripts/test-omr-appreciation.sh`**:
   - Unified `rotate_with_canvas()` function for all angles
   - Replaced special-case 90°/180°/270° logic with general rotation
   - Fixed border color to white (tuple) for proper fiducial visibility

2. **`packages/omr-appreciation/omr-python/quality_metrics.py`**:
   - Added rotation-aware shear threshold
   - Allows shear ≈ rotation for diagonal orientations

3. **`scripts/generate-overlay.php`**:
   - PHP script for generating overlays with candidate names
   - Used by rotation tests for consistent visualization

## Future Work: Diagonal Rotations (45°, 135°, 225°, 315°)

### Current Status: ⚠️ Not Supported
Diagonal rotations detect fiducials correctly (θ=45.00°) but fail vote appreciation due to **coordinate system mismatch**.

### The Challenge
When we rotate a ballot 45° with enlarged canvas:
- **Physical**: Fiducial markers move far from original corners
- **Expected**: Template coordinates assume markers at original positions
- **Result**: Massive reprojection error (~2400px) even though markers are detected

### Example Error Log
```
Quality: θ=+45.00° shear=44.98° ratio=1.000 reproj=2408.21px [RED]
Verdict: FAIL
```

The rotation (θ=45°) and shear (44.98°) are correct, but `reproj=2408px` indicates fiducials are ~2400 pixels away from expected positions.

### Proposed Solution (Future)
To support diagonal rotations, we need to:

1. **Dynamic Fiducial Coordinate Adjustment**
   - Calculate expected fiducial positions based on detected rotation angle
   - Transform template coordinates by same rotation + canvas offset
   - Pass adjusted coordinates to appreciation script

2. **OR: Pre-Alignment Rotation**
   - Detect rotation angle from fiducials
   - Rotate image back to 0° before appreciation
   - Apply appreciation to upright ballot
   - Transform results back to original orientation

3. **OR: Coordinate-Free Appreciation**
   - Use detected fiducials to establish ballot coordinate system
   - Appreciate relative to ballot's local coordinates (not canvas)
   - Independent of rotation or canvas position

### Complexity Estimate
- **Medium-High**: Requires changes to appreciation script's coordinate system
- **Estimated Effort**: 4-8 hours
- **Risk**: Might affect cardinal rotation accuracy if not careful

## Testing

### Run All Rotation Tests
```bash
bash scripts/test-omr-appreciation.sh
```

### View Results
```bash
cd storage/app/tests/omr-appreciation/latest/scenario-8-cardinal-rotations
ls -la  # Shows rot_000, rot_045, rot_090, etc.
```

### Check Individual Rotation
```bash
# View overlay for 90° rotation
open storage/app/tests/omr-appreciation/latest/scenario-8-cardinal-rotations/rot_090/overlay.png

# Check accuracy
cat storage/app/tests/omr-appreciation/latest/scenario-8-cardinal-rotations/rot_090/validation.json
```

## Conclusion

**Cardinal rotations (0°, 90°, 180°, 270°) are production-ready.** These cover the most common real-world scenarios where ballots are scanned in standard orientations.

**Diagonal rotations (45°, 135°, 225°, 315°) are deferred** as they require significant architectural changes to the coordinate system. They represent an edge case unlikely in production use, as users typically place ballots aligned with scanner edges.

The unified rotation approach and rotation-aware quality checks lay the groundwork for future diagonal support if needed.
