# Distorted Ballot Appreciation Problem & Solutions

**Date:** 2025-10-29 (Updated after extensive testing)  
**Status:** ⚠️ CRITICAL BUG FOUND - Perspective Transform Breaks Detection  
**Priority:** 🔴 CRITICAL - Blocking all distortion testing

---

## 🎯 Problem Summary

**We need to accurately detect votes on ballots that have geometric distortions** (rotation, skew, perspective warp) from real-world scanning conditions.

### Current Status:

- ✅ **Perfect ballots work**: 100% accuracy without alignment (`--no-align`)
- ✅ **ArUco markers work**: Detected perfectly even on rotated ballots
- ✅ **Quality metrics work**: Rotation/shear/ratio computed correctly
- ❌ **Alignment BREAKS detection**: 100% → 0% accuracy when alignment enabled

### Critical Discovery:
**The `cv2.warpPerspective()` function transforms the image, but bubble coordinates still reference the original image positions. This causes ALL bubbles to be undetectable after alignment.**

---

## 📊 Test Results - What Actually Works and Doesn't

### PHP-Generated Ballots with ArUco Markers:

| Test Scenario | ArUco Detection | Alignment | Bubble Detection | Accuracy |
|---------------|-----------------|-----------|------------------|----------|
| Original ballot (--no-align) | N/A (skipped) | Disabled | ✅ Works | **100%** ✅ |
| Original ballot (with align) | ✅ 4 markers found | Enabled | ❌ BROKEN | **0%** ❌ |
| R1 +3° rotation | ✅ 4 markers found | Enabled | ❌ BROKEN | **0%** ❌ |
| R2 +10° rotation | ✅ 4 markers found | Enabled | ❌ BROKEN | **0%** ❌ |
| All distorted variants | ✅ Always works | Enabled | ❌ BROKEN | **0%** ❌ |

### Key Findings:

1. **ArUco markers work perfectly** - Even after rotation, all 4 corners detected with IDs 101-104
2. **Quality metrics work** - Correctly reports θ=+3.01° for R1, θ=+0.00° for upright
3. **Perspective transform calculated** - Matrix computed from detected fiducials
4. **Bubble detection breaks** - After `cv2.warpPerspective()`, bubbles not found

### The Bug:
**Warping the image moves pixels, but bubble coordinates aren't updated to match the new positions.**

---

## 🔍 Root Cause Analysis

### The Coordinate Mismatch Problem

**What happens:**
1. Original image has bubbles at known (x, y) coordinates from template
2. `cv2.warpPerspective()` transforms the image pixels to correct distortion
3. Bubbles have moved to NEW positions in the warped image
4. Detector still looks at OLD coordinates → finds nothing

**Example:**
```
Original Image:           Warped Image:
Bubble at (100, 200)  →   Bubble now at (95, 198)
                          But detector still checks (100, 200) ❌
```

### Why Black Square Detection Failed (Historical)

Before we discovered the coordinate mismatch bug, we found that black square fiducial detection was rotation-sensitive:

**Algorithm** (`image_aligner.py` lines 196-277):
```python
# Uses contour detection with aspect ratio check
aspect_ratio = float(w) / h
if 0.7 <= aspect_ratio <= 1.4:  # Expects roughly square
```

**Why It Failed:**
- When ballot rotates, black squares also rotate
- Rotated squares have tilted bounding boxes that fail aspect ratio check
- ArUco markers solved this by being rotation-invariant by design

---

## 💡 Solution Options

### Option A: Transform Coordinates Instead of Image (RECOMMENDED)

**Don't warp the image - transform the bubble coordinates to match the distorted ballot!**

```python
def align_image(image, fiducials, template, verbose=False):
    # Calculate INVERSE transform (template → actual image)
    inv_matrix = cv2.getPerspectiveTransform(dst_points, src_points)
    
    # Return original image + inverse matrix
    # Bubble detector will transform each coordinate before checking
    return image, quality_metrics, inv_matrix

# In mark_detector.py:
def transform_bubble_coords(coords, inv_matrix):
    points = np.array(coords, dtype=np.float32).reshape(-1, 1, 2)
    transformed = cv2.perspectiveTransform(points, inv_matrix)
    return transformed.reshape(-1, 2)
```

**Pros:**
- ✅ No image quality loss from warping
- ✅ Simpler approach - single coordinate transformation
- ✅ Faster processing

### Option B: Warp to Exact Template Dimensions

**Warp the image to match the template's exact pixel dimensions:**

```python
def align_image(image, fiducials, template, verbose=False):
    # Get template dimensions
    template_w = int(template.get('width', 210) * 11.811)  # A4 in px
    template_h = int(template.get('height', 297) * 11.811)
    
    # Warp to template size
    aligned = cv2.warpPerspective(image, matrix, (template_w, template_h))
    
    # Now coordinates match perfectly!
    return aligned, quality_metrics
```

**Pros:**
- ✅ Coordinates match warped image directly
- ✅ Standard approach in CV pipelines

**Cons:**
- ⚠️ Image quality degradation from resampling
- ⚠️ Requires exact template dimensions

### Option C: Update Coordinates After Warping

**Apply the perspective transform to every bubble coordinate:**

```python
# After warping image
def update_bubble_coordinates(template_coords, matrix):
    coords = np.array(template_coords, dtype=np.float32).reshape(-1, 1, 2)
    new_coords = cv2.perspectiveTransform(coords, matrix)
    return new_coords.reshape(-1, 2).tolist()
```

**Pros:**
- ✅ Conceptually simple

**Cons:**
- ⚠️ Need to modify coordinate structure throughout codebase
- ⚠️ More points of failure

---

## 📁 Key Files & Scripts

### Core Scripts

| Script | Purpose | Status |
|--------|---------|--------|
| `scripts/generate-omr-fixtures.sh` | Generate all test fixtures | ✅ Working |
| `scripts/add_fiducial_markers.py` | Add fiducials to ballots | ⚠️ Python ArUco generation broken |
| `scripts/synthesize_ballot_variants.py` | Create distorted variants | ✅ Working |
| `scripts/test-omr-appreciation.sh` | Run all test scenarios | ✅ Working |
| `scripts/compare_appreciation_results.py` | Validate against ground truth | ✅ Working |

### Appreciation Engine

| File | Purpose | Status |
|------|---------|--------|
| `packages/omr-appreciation/omr-python/appreciate.py` | Main appreciation script | ⚠️ Needs coordinate fix |
| `packages/omr-appreciation/omr-python/image_aligner.py` | Fiducial detection & alignment | ⚠️ **CRITICAL BUG HERE** |
| `packages/omr-appreciation/omr-python/mark_detector.py` | Bubble detection | ⚠️ Needs coordinate transform |
| `packages/omr-appreciation/omr-python/quality_metrics.py` | Alignment quality metrics | ✅ Working |

### Test Fixtures

| Fixture Directory | Contents | Status |
|-------------------|----------|--------|
| `storage/app/tests/omr-appreciation/fixtures/filled-ballot-base.png` | Base filled ballot | ✅ Exists |
| `storage/app/tests/omr-appreciation/fixtures/filled-ballot-with-fiducials.png` | With black squares | ✅ Exists (but detection fails on rotation) |
| `storage/app/tests/omr-appreciation/fixtures/filled-distorted/` | 9 distorted (no fiducials) | ✅ Exists |
| `storage/app/tests/omr-appreciation/fixtures/filled-distorted-fiducial/` | 9 distorted (black squares) | ✅ Exists (detection fails) |
| PHP-generated ballots with ArUco | From Laravel ballot generation | ✅ Working (use these!) |
| `storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json` | Expected marks | ✅ Exists |

**Note:** PHP-generated ballots include ArUco markers by default. Use these instead of Python-generated ArUco ballots which have detection issues.

---

## 🚀 Quick Start Guide

### To Test Current State:

```bash
# Run full test suite
bash scripts/test-omr-appreciation.sh

# Check results
cd storage/app/tests/omr-appreciation/latest
cat README.md
cat scenario-6-distortion/summary.json
cat scenario-7-fiducial-alignment/summary.json
```

### To Reproduce the Bug:

```bash
# Get PHP-generated ballot with ArUco markers
bash scripts/test-omr-appreciation.sh
BALLOT="storage/app/tests/omr-appreciation/latest/scenario-1-normal/blank_filled.png"
COORDS="storage/app/tests/omr-appreciation/latest/template/coordinates.json"

# Test WITHOUT alignment (works perfectly)
cd packages/omr-appreciation/omr-python
python3 appreciate.py "$BALLOT" "$COORDS" --threshold 0.3 --no-align
# Result: 100% accuracy ✅

# Test WITH alignment (totally broken)
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py "$BALLOT" "$COORDS" --threshold 0.3
# Result: 0% accuracy ❌ - ArUco detected but bubbles not found
```

### To Test Fix (Once Implemented):

```bash
# After implementing coordinate transformation fix:

# 1. Test upright ballot with alignment
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py upright.png coords.json --threshold 0.3
# Expected: 100% accuracy (same as --no-align)

# 2. Test rotated ballot
python3 synthesize_ballot_variants.py --input upright.png --output distorted/
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py distorted/R1_rotation_+3deg.png coords.json --threshold 0.3
# Expected: ≥98% accuracy

# 3. Test all 9 distortions
bash scripts/test-omr-appreciation.sh
# Expected: All distorted ballots ≥95% accuracy
```

---

## 🔬 Detailed Test Evidence

### Test 1: Original PHP Ballot WITHOUT Alignment
```bash
python3 appreciate.py blank_filled.png coordinates.json --threshold 0.3 --no-align
```
**Result:** ✅ 100% accuracy (5/5 marks detected)

### Test 2: Original PHP Ballot WITH ArUco Alignment
```bash
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py blank_filled.png coordinates.json --threshold 0.3
```
**Output:**
```
Quality: θ=+0.00° shear=0.02° ratio=1.000 reproj=1274.01px [RED]
```
**ArUco Detection:** ✅ 4 markers found (IDs: 101, 102, 103, 104)  
**Result:** ❌ 0% accuracy (0/5 marks detected, all false negatives)

### Test 3: Rotated Ballot (+3°) WITH ArUco Alignment
```bash
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py R1_rotation_+3deg.png coordinates.json --threshold 0.3
```
**Output:**
```
Quality: θ=+3.01° shear=3.01° ratio=1.000 reproj=1274.01px [RED]
Detected: 4 markers
IDs: [103 101 102 104]  # ✅ All corners detected despite rotation!
```
**Result:** ❌ 0% accuracy (0/5 marks detected, all false negatives)

**Conclusion:** ArUco works perfectly, quality metrics correct, but warped image loses all bubbles.

---

## 🛠️ Fixes Applied So Far

### ✅ Fix 1: ArUco API Update (OpenCV 4.7+)
**File:** `image_aligner.py` lines 101-119  
**Problem:** Old API `cv2.aruco.detectMarkers()` removed in OpenCV 4.7+  
**Solution:** Use new `cv2.aruco.ArucoDetector().detectMarkers()`  
**Status:** ✅ Fixed and tested

### ✅ Fix 2: Tuple Unpacking
**File:** `appreciate.py` line 63  
**Problem:** `aligned_image = align_image()` assigned tuple to variable  
**Solution:** `aligned_image, quality_metrics = align_image()`  
**Status:** ✅ Fixed and tested

### ✅ Fix 3: Output Pollution
**File:** `image_aligner.py` lines 341, 348  
**Problem:** Quality metrics printed to stdout, breaking JSON  
**Solution:** Print to stderr: `print(..., file=sys.stderr)`  
**Status:** ✅ Fixed and tested

### ❌ Fix 4: Perspective Transform Coordinate Mismatch
**File:** `image_aligner.py` lines 352-355  
**Problem:** Warped image breaks bubble coordinate alignment  
**Solution:** See "Solution Options" above  
**Status:** ❌ **CRITICAL - NEEDS IMPLEMENTATION**

---

## 📋 Implementation Tasks

### Phase 1: Fix Coordinate Mismatch (CRITICAL - BLOCKING)

**Choose ONE solution path:**

#### Path A: Coordinate Transformation (Recommended)
- [ ] Modify `align_image()` to return inverse matrix instead of warped image
- [ ] Update `appreciate.py` to pass inverse matrix to mark detector
- [ ] Modify `mark_detector.py` to transform coordinates before detection
- [ ] Test on upright ballot → expect 100%
- [ ] Test on R1 rotated ballot → expect ≥98%

#### Path B: Template-Space Warping
- [ ] Get template dimensions from config
- [ ] Modify `align_image()` to warp to exact template size
- [ ] Verify coordinates match warped image
- [ ] Test on all distortions

#### Path C: Post-Warp Coordinate Update
- [ ] Compute forward transform of all bubble coordinates
- [ ] Update coordinate array throughout detection pipeline
- [ ] Test on all fixtures

### Phase 2: Validation
- [ ] Test upright PHP ballot with alignment → expect 100%
- [ ] Test R1 (+3°) with alignment → expect ≥98%
- [ ] Test R2 (+10°) with alignment → expect ≥95%
- [ ] Test all 9 distorted variants
- [ ] Compare vs `--no-align` baseline

### Phase 3: Integration
- [ ] Update test scenarios to validate alignment on all distortions
- [ ] Document the fix in code comments
- [ ] Add regression tests for coordinate transformation
- [ ] Update this document with final results

---

## 🔍 Debugging Commands

### Test ArUco Detection on Any Image
```bash
python3 -c "
import cv2
img = cv2.imread('your_image.png')
gray = cv2.cvtColor(img, cv2.COLOR_BGR2GRAY)
aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)
params = cv2.aruco.DetectorParameters()
detector = cv2.aruco.ArucoDetector(aruco_dict, params)
corners, ids, _ = detector.detectMarkers(gray)
print(f'Detected: {len(corners) if corners else 0} markers')
if ids is not None: print(f'IDs: {ids.flatten()}')
"
```

### Compare With/Without Alignment
```bash
# Without alignment (baseline)
python3 appreciate.py ballot.png coords.json --threshold 0.3 --no-align > no_align.json
python3 compare_appreciation_results.py --result no_align.json --truth ground_truth.json --output report1.json --verbose

# With alignment (broken)
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py ballot.png coords.json --threshold 0.3 > with_align.json
python3 compare_appreciation_results.py --result with_align.json --truth ground_truth.json --output report2.json --verbose
```

### Create Distorted Test Ballots
```bash
# Get PHP-generated ballot with ArUco
bash scripts/test-omr-appreciation.sh
cp storage/app/tests/omr-appreciation/latest/scenario-1-normal/blank_filled.png test_ballot.png

# Create distorted versions
python3 scripts/synthesize_ballot_variants.py --input test_ballot.png --output distorted/

# Test each variant
for f in distorted/*.png; do
  echo "Testing: $f"
  OMR_FIDUCIAL_MODE=aruco python3 appreciate.py "$f" coords.json --threshold 0.3
done
```

---

## 📈 Success Criteria

### Minimum Viable Solution:
- ✅ U0 (upright): 100% accuracy
- ✅ R1 (+3° rotation): ≥98% accuracy
- ✅ S1 (2° shear): ≥98% accuracy
- ⚠️ R2 (+10° rotation): ≥95% accuracy (acceptable with warning)

### Ideal Solution:
- All 9 distorted fixtures: ≥98% accuracy
- Consistent ArUco detection across all distortion types
- Fast processing (<2 seconds per ballot)
- No image quality degradation

---

## 🆘 HELP NEEDED

### Critical Bug: Coordinate Mismatch After Perspective Transform

**Symptom:** Bubble detection works perfectly without alignment (100%), but fails completely with alignment (0%).

**Evidence:**
- ArUco markers detected ✅
- Quality metrics computed correctly ✅  
- Perspective transform applied ✅
- Bubble detection fails ❌

**Root Cause:** The `cv2.warpPerspective()` call transforms the image pixels but bubble coordinates still reference the original image positions.

**Where to Fix:**
- **Primary:** `packages/omr-appreciation/omr-python/image_aligner.py` - Function `align_image()` (lines 280-357)
- **Also needs:** `packages/omr-appreciation/omr-python/appreciate.py` - Coordinate passing
- **Possibly:** `packages/omr-appreciation/omr-python/mark_detector.py` - Coordinate usage

**Recommended Approach:** 
Transform bubble coordinates instead of the image (see "Solution Options" section for detailed implementations).

**Test Cases:**
```bash
# Should work:
python3 appreciate.py ballot.png coords.json --no-align  # 100% ✅

# Currently broken:
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py ballot.png coords.json  # 0% ❌

# Goal after fix:
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py rotated.png coords.json  # 98%+ ✅
```

---

**Last Updated:** 2025-10-29  
**Next Action:** Implement coordinate transformation fix (Solution Option A recommended)  
**Priority:** CRITICAL - Blocking all distortion testing
