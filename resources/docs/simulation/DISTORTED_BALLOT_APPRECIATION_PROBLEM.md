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

**Last Updated:** 2025-10-29  
**Next Action:** Generate and test ArUco-marked fixtures
