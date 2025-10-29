# Distorted Ballot Appreciation Problem & Solutions

**Date:** 2025-10-29  
**Status:** Partially Working - Need Help with Fiducial Detection  
**Priority:** High - Critical for real-world ballot scanning

---

## 🎯 Problem Summary

**We need to accurately detect votes on ballots that have geometric distortions** (rotation, skew, perspective warp) from real-world scanning conditions. Currently:

- ✅ **Perfect ballots work**: 100% accuracy when upright and aligned
- ❌ **Distorted ballots fail**: 0% accuracy with even minor distortions (3° rotation)
- ⚠️ **Fiducial alignment partially works**: Framework in place but detection fails

---

## 📊 Current Test Results

### Scenario 6: No Fiducials (--no-align)
| Fixture | Distortion | Accuracy | Status |
|---------|------------|----------|--------|
| U0 | None (upright) | 100% | ✅ PASS |
| R1 | +3° rotation | 0% | ❌ FAIL |
| R2 | +10° rotation | 0% | 0% | ❌ FAIL |
| R3 | -20° rotation | 0% | ❌ FAIL |
| S1 | 2° shear | 0% | ❌ FAIL |
| S2 | 6° shear | 0% | ❌ FAIL |
| P1-P3 | Perspective | 0% | ❌ FAIL |

**Finding:** Without alignment, even tiny distortions (3°) cause total failure because bubble coordinates don't match their physical positions.

### Scenario 7: With Fiducials (alignment enabled)
| Fixture | Distortion | Fiducial Detection | Accuracy | Status |
|---------|------------|-------------------|----------|--------|
| U0 | None (upright) | ✅ Detected | Unknown | ⚠️ Runs but needs validation |
| R1 | +3° rotation | ❌ **FAILS** | N/A | ❌ FAIL |
| R2 | +10° rotation | ❌ **FAILS** | N/A | ❌ FAIL |
| R3 | -20° rotation | ❌ **FAILS** | N/A | ❌ FAIL |
| S1 | 2° shear | ❌ **FAILS** | N/A | ❌ FAIL |
| S2 | 6° shear | ❌ **FAILS** | N/A | ❌ FAIL |
| P1-P3 | Perspective | ❓ Unknown | N/A | ⚠️ Needs investigation |

**Critical Issue:** Black square fiducial detection fails when ballot is rotated/skewed.

---

## 🔍 Root Cause Analysis

### Problem 1: Black Square Detection is Not Rotation-Invariant

**Current Algorithm** (`image_aligner.py` lines 196-277):
```python
# Uses contour detection with aspect ratio check
aspect_ratio = float(w) / h
if 0.7 <= aspect_ratio <= 1.4:  # Expects roughly square
```

**Why It Fails:**
- When ballot rotates 3°, black squares also rotate 3°
- Rotated squares have tilted bounding boxes
- Aspect ratio check fails: rotated square's bounding box is no longer square-shaped
- Contour-based detection expects axis-aligned squares

**Example:**
```
Upright Square:     Rotated 3° Square:
┌─────┐            ◢─────◣
│     │            │     │  ← Bounding box is now wider/taller
│  ■  │            │  ■  │
│     │            │     │
└─────┘            ◥─────◤
(1:1 ratio)        (1.2:1 ratio) ← REJECTED by algorithm
```

### Problem 2: Output Stream Pollution (FIXED ✅)

Quality metrics were printing to stdout, breaking JSON parsing. **Fixed by redirecting to stderr.**

### Problem 3: Tuple Unpacking Bug (FIXED ✅)

`align_image()` returns `(image, metrics)` but was assigned to single variable. **Fixed with proper unpacking.**

---

## 💡 Proposed Solutions

### Solution A: Switch to ArUco Markers (RECOMMENDED)

**Why ArUco is Better:**
- ✅ **Rotation-invariant** - Designed for this exact use case
- ✅ **Unique IDs** - Each corner has distinct marker (101-104)
- ✅ **Robust** - Works under perspective distortion
- ✅ **Mature** - Well-tested OpenCV implementation

**Implementation Status:**
- ✅ ArUco generation script exists: `scripts/generate_aruco_markers.py`
- ✅ ArUco detection code exists: `image_aligner.py` lines 87-144
- ✅ Fixture generation script ready: `scripts/add_fiducial_markers.py --mode aruco`
- ❌ **Need to generate ArUco-marked fixtures and test**

**Action Items:**
1. Generate ArUco-marked filled ballots
2. Generate distorted variants with ArUco markers
3. Run scenario-7 tests with ArUco fixtures
4. Validate ≥98% accuracy on distorted ballots

### Solution B: Improve Black Square Detection

**Approaches:**
1. **Rotation-invariant features:**
   - Use Hu moments (rotation-invariant)
   - Use circular Hough transform (detects circular/square patterns)
   
2. **Multi-angle detection:**
   - Try detecting at 0°, 90°, 180°, 270°
   - Rotate image before detection
   
3. **Template matching:**
   - Use normalized cross-correlation
   - Rotation-invariant template matching

**Cons:**
- More complex than ArUco
- May still fail under combined distortions
- Reinventing the wheel (ArUco solves this)

### Solution C: Hybrid Approach

Use both black squares AND ArUco:
- Black squares for simple cases (fast)
- ArUco fallback for rotated cases (robust)
- Already partially implemented in `detect_fiducials()`

---

## 📁 Key Files & Scripts

### Core Scripts

| Script | Purpose | Status |
|--------|---------|--------|
| `scripts/generate-omr-fixtures.sh` | Generate all test fixtures | ✅ Working |
| `scripts/add_fiducial_markers.py` | Add fiducials to ballots | ✅ Working |
| `scripts/synthesize_ballot_variants.py` | Create distorted variants | ✅ Working |
| `scripts/test-omr-appreciation.sh` | Run all test scenarios | ✅ Working |
| `scripts/compare_appreciation_results.py` | Validate against ground truth | ✅ Working |

### Appreciation Engine

| File | Purpose | Status |
|------|---------|--------|
| `packages/omr-appreciation/omr-python/appreciate.py` | Main appreciation script | ✅ Fixed |
| `packages/omr-appreciation/omr-python/image_aligner.py` | Fiducial detection & alignment | ⚠️ Needs work |
| `packages/omr-appreciation/omr-python/mark_detector.py` | Bubble detection | ✅ Working |
| `packages/omr-appreciation/omr-python/quality_metrics.py` | Alignment quality metrics | ✅ Working |

### Test Fixtures

| Fixture Directory | Contents | Status |
|-------------------|----------|--------|
| `storage/app/tests/omr-appreciation/fixtures/filled-ballot-base.png` | Base filled ballot | ✅ Exists |
| `storage/app/tests/omr-appreciation/fixtures/filled-ballot-with-fiducials.png` | With black squares | ✅ Exists |
| `storage/app/tests/omr-appreciation/fixtures/filled-distorted/` | 9 distorted (no fiducials) | ✅ Exists |
| `storage/app/tests/omr-appreciation/fixtures/filled-distorted-fiducial/` | 9 distorted (black squares) | ✅ Exists |
| `storage/app/tests/omr-appreciation/fixtures/filled-distorted-aruco/` | 9 distorted (ArUco) | ❌ **NEED TO CREATE** |
| `storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json` | Expected marks | ✅ Exists |

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

### To Generate ArUco Fixtures (RECOMMENDED NEXT STEP):

```bash
# 1. Generate ArUco-marked filled ballot base
python3 scripts/add_fiducial_markers.py \
  storage/app/tests/omr-appreciation/fixtures/filled-ballot-base.png \
  storage/app/tests/omr-appreciation/fixtures/filled-ballot-aruco.png \
  --mode aruco \
  --aruco-ids 101,102,103,104

# 2. Generate 9 distorted variants with ArUco markers
python3 scripts/synthesize_ballot_variants.py \
  --input storage/app/tests/omr-appreciation/fixtures/filled-ballot-aruco.png \
  --output storage/app/tests/omr-appreciation/fixtures/filled-distorted-aruco

# 3. Run tests (need to create scenario-8 or modify scenario-7)
# See "Implementation Tasks" below
```

### To Debug Fiducial Detection:

```bash
# Test black square detection on rotated ballot
python3 packages/omr-appreciation/omr-python/appreciate.py \
  storage/app/tests/omr-appreciation/fixtures/filled-distorted-fiducial/R1_rotation_+3deg.png \
  storage/app/tests/omr-appreciation/latest/template/coordinates.json \
  --threshold 0.3

# Expected: "Error: Could not detect 4 fiducial markers"
```

---

## 📋 Implementation Tasks

### Phase 1: ArUco Marker Testing (HIGH PRIORITY)

- [ ] Generate ArUco-marked filled ballot base
- [ ] Generate 9 distorted ArUco variants
- [ ] Add scenario-8 to `test-omr-appreciation.sh` for ArUco testing
- [ ] Update fixture generation script to include ArUco variants
- [ ] Run tests and validate ≥98% accuracy

### Phase 2: Investigation (IF ARUCO FAILS)

- [ ] Debug why U0 (upright) doesn't show 100% accuracy with fiducials
- [ ] Check if fiducial markers overlap with corner bubbles
- [ ] Investigate perspective distortion cases (P1-P3)
- [ ] Measure actual vs expected fiducial coordinates

### Phase 3: Algorithm Improvements (IF NEEDED)

- [ ] Implement rotation-invariant black square detection
- [ ] Add multi-angle detection fallback
- [ ] Optimize ArUco detection parameters
- [ ] Add hybrid detection mode

---

## 🔧 Code Changes Needed

### 1. Add ArUco Fixture Generation to `generate-omr-fixtures.sh`

```bash
# After line 154 (filled-distorted-fiducial section)

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 8. Generate ArUco-marked filled ballot (for scenario-8)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILLED_WITH_ARUCO="${FIXTURE_BASE}/filled-ballot-aruco.png"

if [ ! -f "${FILLED_WITH_ARUCO}" ]; then
    echo -e "${YELLOW}Adding ArUco markers to filled ballot...${NC}"
    
    if python3 scripts/add_fiducial_markers.py \
        "${FILLED_BALLOT_BASE}" \
        "${FILLED_WITH_ARUCO}" \
        --mode aruco \
        --aruco-ids 101,102,103,104 \
        --size 10 \
        --margin 5; then
        echo -e "${GREEN}✓ Filled ballot with ArUco created${NC}"
    else
        echo -e "${RED}✗ Failed to add ArUco markers${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Filled ballot with ArUco exists${NC}"
fi

# Generate distorted variants
FILLED_DISTORTED_ARUCO_DIR="${FIXTURE_BASE}/filled-distorted-aruco"

if [ ! -d "${FILLED_DISTORTED_ARUCO_DIR}" ] || [ -z "$(ls -A "${FILLED_DISTORTED_ARUCO_DIR}" 2>/dev/null)" ]; then
    echo -e "${YELLOW}Generating filled-distorted-aruco fixtures...${NC}"
    
    if python3 scripts/synthesize_ballot_variants.py \
        --input "${FILLED_WITH_ARUCO}" \
        --output "${FILLED_DISTORTED_ARUCO_DIR}" \
        --quiet; then
        echo -e "${GREEN}✓ Filled-distorted-aruco fixtures generated${NC}"
    else
        echo -e "${RED}✗ Failed to generate filled-distorted-aruco fixtures${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Filled-distorted-aruco fixtures exist${NC}"
fi
```

### 2. Add Scenario-8 to `test-omr-appreciation.sh`

Copy scenario-7 section and modify:
- Change `SCENARIO_7` to `SCENARIO_8`
- Change directory to `filled-distorted-aruco`
- Update metadata to indicate ArUco markers
- Set environment variable: `export OMR_FIDUCIAL_MODE=aruco`

### 3. Enable ArUco Detection

```bash
# In test-omr-appreciation.sh, before scenario-8
export OMR_FIDUCIAL_MODE=aruco
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
- Consistent fiducial detection across all distortion types
- Fast processing (<2 seconds per ballot)

---

## 🆘 How You Can Help

### Option 1: Test ArUco Markers (FASTEST)
1. Generate ArUco fixtures using commands above
2. Run tests and report results
3. If successful, we just need to integrate into main test flow

### Option 2: Debug Black Square Detection
1. Investigate why rotated squares aren't detected
2. Possibly implement rotation-invariant detection
3. Test on R1 fixture and measure improvement

### Option 3: Investigate Bubble Detection
1. Check why U0 with fiducials might not achieve 100%
2. Verify fiducial markers don't overlap corner bubbles
3. Validate ground truth matches actual filled marks

---

## 📞 Questions?

**Key Files to Review:**
- This document: You are here
- Test results: `storage/app/tests/omr-appreciation/latest/README.md`
- Fiducial code: `packages/omr-appreciation/omr-python/image_aligner.py`
- Test script: `scripts/test-omr-appreciation.sh`

**Quick Commands:**
```bash
# See latest test results
cat storage/app/tests/omr-appreciation/latest/README.md

# Check fixture status
ls -lh storage/app/tests/omr-appreciation/fixtures/

# View specific test output
cat storage/app/tests/omr-appreciation/latest/scenario-7-fiducial-alignment/summary.json
```

---

**Last Updated:** 2025-10-29 (Updated after extensive testing)  
**Status:** CRITICAL BUG FOUND - Perspective transform breaks bubble detection

---

## 🚨 CRITICAL DISCOVERY (Latest)

### ArUco Detection Works, But Alignment Breaks Detection

**Test Results with PHP-Generated Ballots:**

| Test | ArUco Mode | Alignment | Accuracy | Finding |
|------|-----------|-----------|----------|----------|
| Original PHP ballot | ✅ Yes | ❌ Disabled (`--no-align`) | 100% | ✅ **WORKS PERFECTLY** |
| Original PHP ballot | ✅ Yes | ✅ Enabled | 0% | ❌ **TOTAL FAILURE** |
| R1 (+3° rotation) | ✅ Yes | ✅ Enabled | 0% | ❌ **TOTAL FAILURE** |
| U0 (upright distorted) | ✅ Yes | ✅ Enabled | 0% | ❌ **TOTAL FAILURE** |

**Conclusion:**
- ✅ ArUco markers ARE present in PHP-generated ballots
- ✅ ArUco detection works (all 4 markers detected, even after rotation)
- ✅ Quality metrics computed correctly (θ angle, shear, ratio)
- ✅ Perspective transform matrix calculated
- ❌ **cv2.warpPerspective() breaks bubble detection completely**

### Root Cause

**The perspective transform is applied, but the warped image loses bubble marks or coordinates don't match the warped image.**

Possible issues:
1. **Transform applies to wrong dimensions** - warped image size doesn't match original
2. **Bubble coordinates not transformed** - coordinates reference original image, not warped
3. **Image quality degradation** - warping introduces artifacts that prevent detection
4. **Wrong transform direction** - should transform coordinates, not image

### Code Location

**File:** `packages/omr-appreciation/omr-python/image_aligner.py`  
**Function:** `align_image()` (lines 280-357)
**Problem Line:** `aligned = cv2.warpPerspective(image, matrix, (w, h))`

```python
# Current code (BROKEN):
def align_image(image, fiducials, template, verbose=False):
    # ... compute matrix ...
    h, w = image.shape[:2]
    aligned = cv2.warpPerspective(image, matrix, (w, h))  # ❌ This breaks detection
    return aligned, quality_metrics
```

**The issue:** After warping, bubble coordinates from template still reference the ORIGINAL image dimensions, but the bubbles have moved in the warped image.

### Solution Options

**Option A: Transform Coordinates Instead of Image (RECOMMENDED)**
```python
# Don't warp the image - transform the coordinates instead!
def align_image(image, fiducials, template, verbose=False):
    # ... compute matrix ...
    
    # Calculate INVERSE transform to map template coords to actual image
    inv_matrix = cv2.getPerspectiveTransform(dst_points, src_points)
    
    # Return original image + inverse matrix
    # Bubble detection will apply inv_matrix to each coordinate
    return image, quality_metrics, inv_matrix
```

**Option B: Warp to Template Space**
```python
# Warp image to match template dimensions exactly
def align_image(image, fiducials, template, verbose=False):
    # ... compute matrix ...
    
    # Get template dimensions from config
    template_w = int(template.get('width', 210) * 11.811)  # A4 width in px
    template_h = int(template.get('height', 297) * 11.811) # A4 height in px
    
    aligned = cv2.warpPerspective(image, matrix, (template_w, template_h))
    return aligned, quality_metrics
```

**Option C: Update Coordinates After Warping**
```python
# Apply inverse transform to all bubble coordinates
def transform_coordinates(coords, matrix):
    # Transform each (x,y) coordinate through the perspective matrix
    points = np.array([[x, y] for x, y in coords], dtype=np.float32)
    transformed = cv2.perspectiveTransform(points.reshape(-1, 1, 2), matrix)
    return transformed.reshape(-1, 2)
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
**Result:** ❌ 0% accuracy (0/5 marks detected, all false negatives)

### Test 3: Rotated Ballot (+3°) WITH ArUco Alignment
```bash
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py R1_rotation_+3deg.png coordinates.json --threshold 0.3
```
**Output:**
```
Quality: θ=+3.01° shear=3.01° ratio=1.000 reproj=1274.01px [RED]
```
**ArUco Detection:**
```python
Detected: 4 markers
IDs: [103 101 102 104]  # ✅ All corners detected despite rotation!
```
**Result:** ❌ 0% accuracy (0/5 marks detected, all false negatives)

### Test 4: Python-Generated ArUco Markers
```bash
python3 add_fiducial_markers.py --mode aruco input.png output.png
```
**ArUco Detection:**
```
Detected: 0 markers  # ❌ Python script generates undetectable markers
```
**Conclusion:** Python script's ArUco generation is broken - use PHP ballots only

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

### ❌ Fix 4: Perspective Transform (NEEDS FIXING)
**File:** `image_aligner.py` lines 352-355  
**Problem:** Warped image breaks bubble coordinate alignment  
**Solution:** See "Solution Options" above  
**Status:** ❌ **CRITICAL - NEEDS IMPLEMENTATION**

---

## 📋 Implementation Tasks (UPDATED)

### Phase 1: Fix Perspective Transform (HIGH PRIORITY - BLOCKING)

- [ ] **Option A: Implement coordinate transformation approach**
  - Modify `align_image()` to return inverse matrix
  - Update `detect_marks()` to transform coordinates before detection
  - Test on upright ballot first (should still get 100%)
  - Test on R1 rotated ballot (target: ≥98%)

- [ ] **Option B: Implement template-space warping**
  - Get template dimensions from config
  - Warp to exact template size
  - Verify coordinates now match warped image

- [ ] **Option C: Transform coordinates after warping**
  - Apply inverse perspective transform to bubble coordinates
  - Update coordinate array before detection

### Phase 2: Validation

- [ ] Test upright PHP ballot with alignment → expect 100%
- [ ] Test R1 (+3°) with alignment → expect ≥98%
- [ ] Test R2 (+10°) with alignment → expect ≥95%
- [ ] Test all 9 distorted variants
- [ ] Compare vs --no-align results

### Phase 3: Integration

- [ ] Update generate-omr-fixtures.sh to use PHP ballots
- [ ] Remove Python add_fiducial_markers.py approach
- [ ] Update test scenarios to use PHP-generated ballots
- [ ] Document why Python ArUco generation doesn't work

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

### Get PHP-Generated Ballots
```bash
# Run test to generate fresh PHP ballots with ArUco
bash scripts/test-omr-appreciation.sh

# Use the generated ballot
cp storage/app/tests/omr-appreciation/latest/scenario-1-normal/blank_filled.png test_ballot.png

# Create distorted versions
python3 scripts/synthesize_ballot_variants.py --input test_ballot.png --output distorted/
```

---

## 🆘 HELP NEEDED

### Critical Bug: Perspective Transform Breaks Detection

**Symptom:** Bubble detection works perfectly without alignment (100%), but fails completely with alignment (0%).

**Evidence:**
- ArUco markers detected ✅
- Quality metrics computed ✅  
- Perspective transform applied ✅
- Bubble detection fails ❌

**Root Cause:** The `cv2.warpPerspective()` call transforms the image but bubble coordinates still reference the original image positions.

**Where to Fix:**
- File: `packages/omr-appreciation/omr-python/image_aligner.py`
- Function: `align_image()` (lines 280-357)
- Also needs changes in: `appreciate.py` and possibly `mark_detector.py`

**Recommended Approach:** 
Transform bubble coordinates instead of the image (see "Solution Options" above).

**Test Cases:**
```bash
# Should work:
python3 appreciate.py ballot.png coords.json --no-align  # 100% ✅

# Currently broken:
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py ballot.png coords.json  # 0% ❌

# Goal after fix:
OMR_FIDUCIAL_MODE=aruco python3 appreciate.py rotated.png coords.json  # 98%+ ✅
```

**Files to Review:**
1. `packages/omr-appreciation/omr-python/image_aligner.py` - Where transform happens
2. `packages/omr-appreciation/omr-python/appreciate.py` - Calls align_image()
3. `packages/omr-appreciation/omr-python/mark_detector.py` - Uses coordinates for detection
4. This document - Complete problem analysis

---

**Last Updated:** 2025-10-29 23:46 UTC  
**Next Action:** Fix perspective transform to work with bubble coordinates  
**Priority:** CRITICAL - Blocking all distortion testing
