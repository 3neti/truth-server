#!/bin/bash
# OMR Appreciation Test Runner
# Creates timestamped directory and runs all OMR appreciation tests with comprehensive reporting

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Create timestamp for this test run
RUN_TIMESTAMP=$(date '+%Y-%m-%d_%H%M%S')
RUN_DIR="storage/app/tests/omr-appreciation/runs/${RUN_TIMESTAMP}"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  OMR Appreciation Test Suite${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Run ID:${NC} ${RUN_TIMESTAMP}"
echo -e "${BLUE}Output:${NC} ${RUN_DIR}"
echo ""

# Create run directory
mkdir -p "${RUN_DIR}"

# Capture environment info
echo -e "${YELLOW}Capturing environment info...${NC}"

# Detect fiducial capabilities
ARUCO_AVAILABLE="false"
APRILTAG_AVAILABLE="false"
if python3 -c "import cv2; cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)" 2>/dev/null; then
    ARUCO_AVAILABLE="true"
fi
if python3 -c "import apriltag" 2>/dev/null || python3 -c "from pupil_apriltags import Detector" 2>/dev/null; then
    APRILTAG_AVAILABLE="true"
fi

# Check if ArUco markers exist, generate if missing and ArUco is available
if [ "${ARUCO_AVAILABLE}" = "true" ]; then
    ARUCO_MARKER_DIR="storage/app/fiducial-markers/aruco"
    if [ ! -d "${ARUCO_MARKER_DIR}" ] || [ -z "$(ls -A "${ARUCO_MARKER_DIR}" 2>/dev/null)" ]; then
        echo -e "${YELLOW}⚠ ArUco markers not found, generating...${NC}"
        if python3 scripts/generate_aruco_markers.py --size 200; then
            echo -e "${GREEN}✓ ArUco markers generated${NC}"
        else
            echo -e "${RED}✗ Failed to generate ArUco markers${NC}"
        fi
    fi
fi

cat > "${RUN_DIR}/environment.json" <<EOF
{
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "hostname": "$(hostname)",
  "user": "$(whoami)",
  "php_version": "$(php -r 'echo PHP_VERSION;')",
  "python_version": "$(python3 --version 2>&1 | cut -d' ' -f2)",
  "imagick_version": "$(php -r 'echo (new Imagick())->getVersion()[\"versionString\"];' 2>/dev/null || echo 'not available')",
  "opencv_version": "$(python3 -c 'import cv2; print(cv2.__version__)' 2>/dev/null || echo 'not available')",
  "fiducial_support": {
    "black_square": true,
    "aruco": ${ARUCO_AVAILABLE},
    "apriltag": ${APRILTAG_AVAILABLE}
  },
  "omr_fiducial_mode": "${OMR_FIDUCIAL_MODE:-black_square}"
}
EOF

# Display fiducial mode
if [ -z "${OMR_FIDUCIAL_MODE}" ]; then
    echo -e "${BLUE}Fiducial Mode:${NC} black_square (default)"
else
    echo -e "${BLUE}Fiducial Mode:${NC} ${OMR_FIDUCIAL_MODE}"
fi

if [ "${ARUCO_AVAILABLE}" = "true" ]; then
    echo -e "${GREEN}✓ ArUco detection available${NC}"
else
    echo -e "${YELLOW}⚠ ArUco detection unavailable${NC}"
fi

if [ "${APRILTAG_AVAILABLE}" = "true" ]; then
    echo -e "${GREEN}✓ AprilTag detection available${NC}"
else
    echo -e "${YELLOW}⚠ AprilTag detection unavailable${NC}"
fi
echo ""

# Generate all required fixtures
echo -e "${YELLOW}Ensuring all test fixtures are available...${NC}"
if bash scripts/generate-omr-fixtures.sh; then
    echo -e "${GREEN}✓ All fixtures ready${NC}"
else
    echo -e "${RED}✗ Fixture generation failed${NC}"
    exit 1
fi
echo ""

# Run tests with timestamped directory
echo -e "${YELLOW}Running OMR appreciation tests...${NC}"
export OMR_TEST_RUN_ID="${RUN_TIMESTAMP}"

# Capture test output
TEST_OUTPUT="${RUN_DIR}/test-output.txt"
if php artisan test tests/Feature/OMRAppreciationTest.php --group=appreciation > "${TEST_OUTPUT}" 2>&1; then
    TEST_STATUS="PASSED"
    STATUS_COLOR="${GREEN}"
else
    TEST_STATUS="FAILED"
    STATUS_COLOR="${RED}"
fi

# Parse test results
TESTS_PASSED=$(grep -o "[0-9]\+ passed" "${TEST_OUTPUT}" | awk '{print $1}' || echo "0")
TESTS_FAILED=$(grep -o "[0-9]\+ failed" "${TEST_OUTPUT}" | awk '{print $1}' || echo "0")
TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))

# Generate test results JSON (handle empty values)
[ -z "${TESTS_PASSED}" ] && TESTS_PASSED=0
[ -z "${TESTS_FAILED}" ] && TESTS_FAILED=0
[ -z "${TOTAL_TESTS}" ] && TOTAL_TESTS=0

cat > "${RUN_DIR}/test-results.json" <<EOF
{
  "run_id": "${RUN_TIMESTAMP}",
  "status": "${TEST_STATUS}",
  "summary": {
    "total": ${TOTAL_TESTS},
    "passed": ${TESTS_PASSED},
    "failed": ${TESTS_FAILED}
  },
  "scenarios": [
    $([ -d "${RUN_DIR}/scenario-1-normal" ] && echo '{"id": "scenario-1-normal", "name": "Normal Ballot", "status": "executed"},' || echo '')
    $([ -d "${RUN_DIR}/scenario-2-overvote" ] && echo '{"id": "scenario-2-overvote", "name": "Overvote Detection", "status": "executed"},' || echo '')
    $([ -d "${RUN_DIR}/scenario-3-faint" ] && echo '{"id": "scenario-3-faint", "name": "Faint Marks", "status": "executed"},' || echo '')
    $([ -d "${RUN_DIR}/scenario-4-fiducials" ] && echo '{"id": "scenario-4-fiducials", "name": "Fiducial Marker Detection", "status": "executed"},' || echo '')
    $([ -d "${RUN_DIR}/scenario-5-quality-gates" ] && echo '{"id": "scenario-5-quality-gates", "name": "Quality Gates (Skew/Rotation)", "status": "executed"},' || echo '')
    $([ -d "${RUN_DIR}/scenario-6-distortion" ] && echo '{"id": "scenario-6-distortion", "name": "Filled Ballot Distortion", "status": "executed"}' || echo '')
  ]
}
EOF

# Run scenario-5-quality-gates if fixtures exist
if [ -d "${FIXTURE_DIR}" ] && [ -n "$(ls -A "${FIXTURE_DIR}" 2>/dev/null | grep '.png$')" ]; then
    SCENARIO_5="${RUN_DIR}/scenario-5-quality-gates"
    mkdir -p "${SCENARIO_5}"
    
    echo -e "${YELLOW}Testing quality gates (skew/rotation)...${NC}"
    
    # Create metadata
    cat > "${SCENARIO_5}/metadata.json" <<SCENARIO5META
{
  "scenario": "quality-gates",
  "description": "Ballot alignment quality validation with synthetic distortions",
  "fixtures_tested": $(ls "${FIXTURE_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' '),
  "test_matrix": "SKEW_ROTATION_TEST_SCENARIO.md"
}
SCENARIO5META
    
    # Test each fixture
    QUALITY_PASSED=0
    QUALITY_FAILED=0
    
    for fixture in "${FIXTURE_DIR}"/*.png; do
        [ -f "${fixture}" ] || continue
        basename=$(basename "${fixture}" .png)
        echo -e "  Testing ${BLUE}${basename}${NC}..."
        
        if python3 packages/omr-appreciation/omr-python/test_quality_on_fixture.py \
            "${fixture}" \
            > "${SCENARIO_5}/${basename}_metrics.log" 2>&1; then
            echo -e "    ${GREEN}✓ PASS${NC}"
            QUALITY_PASSED=$((QUALITY_PASSED + 1))
        else
            echo -e "    ${RED}✗ FAIL${NC}"
            QUALITY_FAILED=$((QUALITY_FAILED + 1))
        fi
    done
    
    # Generate summary
    cat > "${SCENARIO_5}/summary.json" <<QUALITYSUMMARY
{
  "total_fixtures": $((QUALITY_PASSED + QUALITY_FAILED)),
  "passed": ${QUALITY_PASSED},
  "failed": ${QUALITY_FAILED}
}
QUALITYSUMMARY
    
    echo -e "${GREEN}✓ Quality gate tests complete${NC} (${QUALITY_PASSED} passed, ${QUALITY_FAILED} failed)"
    echo ""
fi

# Run scenario-6-distortion if filled ballot fixtures exist
FILLED_FIXTURE_DIR="storage/app/tests/omr-appreciation/fixtures/filled-distorted"
if [ -d "${FILLED_FIXTURE_DIR}" ] && [ -n "$(ls -A "${FILLED_FIXTURE_DIR}" 2>/dev/null | grep '.png$')" ]; then
    SCENARIO_6="${RUN_DIR}/scenario-6-distortion"
    mkdir -p "${SCENARIO_6}"
    
    echo -e "${YELLOW}Testing filled ballot distortion tolerance...${NC}"
    
    # Create metadata
    cat > "${SCENARIO_6}/metadata.json" <<SCENARIO6META
{
  "scenario": "filled-ballot-distortion",
  "description": "Real-world ballot appreciation under geometric distortion",
  "fixtures_tested": $(ls "${FILLED_FIXTURE_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' '),
  "ground_truth": "storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json"
}
SCENARIO6META
    
    # Test each filled distorted fixture
    DISTORTION_PASSED=0
    DISTORTION_FAILED=0
    
    # Get paths for appreciation and comparison
    APPRECIATE_SCRIPT="packages/omr-appreciation/omr-python/appreciate.py"
    COORDS_FILE="${RUN_DIR}/template/coordinates.json"
    GROUND_TRUTH="storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json"
    
    # Verify required files exist
    if [ ! -f "${APPRECIATE_SCRIPT}" ]; then
        echo -e "${RED}✗ Appreciation script not found: ${APPRECIATE_SCRIPT}${NC}"
    elif [ ! -f "${COORDS_FILE}" ]; then
        echo -e "${RED}✗ Coordinates file not found: ${COORDS_FILE}${NC}"
    elif [ ! -f "${GROUND_TRUTH}" ]; then
        echo -e "${RED}✗ Ground truth not found: ${GROUND_TRUTH}${NC}"
    else
        for fixture in "${FILLED_FIXTURE_DIR}"/*.png; do
            [ -f "${fixture}" ] || continue
            basename=$(basename "${fixture}" .png)
            echo -e "  Testing ${BLUE}${basename}${NC}..."
            
            # Run appreciation on distorted filled ballot
            APPRECIATION_OUTPUT="${SCENARIO_6}/${basename}_appreciation.json"
            VALIDATION_OUTPUT="${SCENARIO_6}/${basename}_validation.json"
            COMBINED_LOG="${SCENARIO_6}/${basename}_combined.log"
            
            # Run appreciation (without alignment - synthetic distortions don't have real fiducials)
            if python3 "${APPRECIATE_SCRIPT}" \
                "${fixture}" \
                "${COORDS_FILE}" \
                --threshold 0.3 \
                --no-align \
                > "${APPRECIATION_OUTPUT}" 2>&1; then
                
                # Validate results against ground truth
                if python3 scripts/compare_appreciation_results.py \
                    --result "${APPRECIATION_OUTPUT}" \
                    --truth "${GROUND_TRUTH}" \
                    --output "${VALIDATION_OUTPUT}" \
                    > "${COMBINED_LOG}" 2>&1; then
                    
                    # Extract accuracy from validation output
                    ACCURACY=$(python3 -c "import json; print(f\"{json.load(open('${VALIDATION_OUTPUT}'))['accuracy']*100:.1f}%\")" 2>/dev/null || echo "N/A")
                    echo -e "    ${GREEN}✓ PASS${NC} (accuracy: ${ACCURACY})"
                    DISTORTION_PASSED=$((DISTORTION_PASSED + 1))
                else
                    # Extract accuracy even on failure
                    ACCURACY=$(python3 -c "import json; print(f\"{json.load(open('${VALIDATION_OUTPUT}'))['accuracy']*100:.1f}%\")" 2>/dev/null || echo "N/A")
                    echo -e "    ${RED}✗ FAIL${NC} (accuracy: ${ACCURACY})"
                    DISTORTION_FAILED=$((DISTORTION_FAILED + 1))
                fi
            else
                echo -e "    ${RED}✗ FAIL${NC} (appreciation error)"
                echo "Appreciation failed" > "${COMBINED_LOG}"
                DISTORTION_FAILED=$((DISTORTION_FAILED + 1))
            fi
        done
    fi
    
    # Generate summary
    cat > "${SCENARIO_6}/summary.json" <<DISTORTIONSUMMARY
{
  "total_fixtures": $((DISTORTION_PASSED + DISTORTION_FAILED)),
  "passed": ${DISTORTION_PASSED},
  "failed": ${DISTORTION_FAILED}
}
DISTORTIONSUMMARY
    
    if [ $((DISTORTION_PASSED + DISTORTION_FAILED)) -gt 0 ]; then
        echo -e "${GREEN}✓ Filled ballot distortion tests complete${NC} (${DISTORTION_PASSED} passed, ${DISTORTION_FAILED} failed)"
    else
        echo -e "${YELLOW}⊙ Distortion tests skipped (missing required files)${NC}"
    fi
    echo ""
fi

# Generate README documentation
echo -e "${YELLOW}Generating documentation...${NC}"
cat > "${RUN_DIR}/README.md" <<'EOFREADME'
# OMR Appreciation Test Run

## Test Information

**Run ID:** `${RUN_TIMESTAMP}`  
**Date:** $(date '+%Y-%m-%d %H:%M:%S %Z')  
**Status:** **${TEST_STATUS}**

## Summary

- **Total Tests:** ${TOTAL_TESTS}
- **Passed:** ✅ ${TESTS_PASSED}
- **Failed:** ❌ ${TESTS_FAILED}

## Test Scenarios

### Scenario 1: Normal Ballot
**Directory:** `scenario-1-normal/`

Tests standard ballot appreciation with 5 deliberately filled bubbles:
- PRESIDENT_LD_001
- VICE-PRESIDENT_VD_002
- SENATOR_JD_001, SENATOR_ES_002, SENATOR_MF_003

**Artifacts:**
- `blank.png` - Original ballot template
- `filled.png` - Simulated filled ballot
- `overlay.png` - Visual overlay showing detected marks
- `results.json` - Detailed appreciation results
- `metadata.json` - Test configuration and parameters

### Scenario 2: Overvote Detection
**Directory:** `scenario-2-overvote/`

Tests detection of overvote condition (multiple marks for single-choice position):
- Two bubbles filled for President position
- Verifies that both marks are detected

### Scenario 3: Faint Marks
**Directory:** `scenario-3-faint/`

Tests sensitivity to faint marks:
- 70% fill intensity (vs 100% for normal marks)
- Lower detection threshold (0.25 vs 0.30)
- Demonstrates threshold tuning challenges

### Scenario 4: Fiducial Marker Detection
**Directory:** `scenario-4-fiducials/`

Tests fiducial marker detection and alignment:
- Generates ballots with ArUco markers (if available)
- Tests fiducial detection WITHOUT `--no-align` flag
- Validates perspective correction and corner detection
- Compares detection across available modes

### Scenario 5: Quality Gates (Skew/Rotation)
**Directory:** `scenario-5-quality-gates/`

Tests ballot alignment quality with synthetic geometric distortions:
- Tests rotation detection (θ angle measurement)
- Tests shear detection (horizontal/vertical skew)
- Tests perspective distortion (aspect ratio validation)
- Validates quality thresholds per SKEW_ROTATION_TEST_SCENARIO.md

**Test Matrix:**
- **U0**: Reference upright (baseline - no distortion)
- **R1-R3**: Rotation tests (+3°, +10°, -20°)
- **S1-S2**: Shear tests (2°, 6°)
- **P1-P3**: Perspective tests (ratio 0.98, 0.95, 0.90)

**Artifacts:**
- `*_metrics.log` - Quality metrics report for each fixture
- `metadata.json` - Test configuration
- `summary.json` - Pass/fail summary

**Quality Thresholds:**
- Rotation θ: Green ≤3°, Amber 3-10°, Red >10°
- Shear: Green ≤2°, Amber 2-6°, Red >6°
- Aspect ratio: Green ≥0.95, Amber 0.90-0.95, Red <0.90

### Scenario 6: Filled Ballot Distortion
**Directory:** `scenario-6-distortion/`

Tests ballot appreciation on filled ballots with geometric distortions:
- Runs full OMR appreciation on distorted filled ballots  
- Compares detected votes against ground truth (5 expected marks)
- **Uses `--no-align` flag** (no fiducial correction)
- **Demonstrates that geometric distortions break fixed-coordinate appreciation**

**Test Matrix:** Same as Scenario 5 (U0, R1-R3, S1-S2, P1-P3)

**Artifacts:**
- `*_appreciation.json` - Raw appreciation results for each fixture
- `*_validation.json` - Validation report with accuracy metrics
- `*_combined.log` - Combined test log
- `metadata.json` - Test configuration
- `summary.json` - Pass/fail summary with accuracy statistics

**Validation Criteria:**
- Minimum accuracy: ≥98%
- Maximum false positive rate: ≤1%
- Maximum false negative rate: ≤2%

**Expected Results (without alignment):**
- U0 (upright): ✅ 100% accuracy (no distortion)
- R1-R3 (rotation): ❌ 0% accuracy (coordinates don't match rotated positions)
- S1-S2 (shear): ❌ 0% accuracy (coordinates don't match skewed positions)  
- P1-P3 (perspective): ❌ 0% accuracy (coordinates don't match perspective-warped positions)

**Key Finding:** This validates that **fiducial marker alignment is essential** for handling real-world ballot distortions. Without alignment correction, even minor geometric distortions (3° rotation) result in complete detection failure.

## Template Files

- `template/ballot.pdf` - Source ballot PDF
- `template/coordinates.json` - Bubble coordinate mappings

## Fiducial Markers

Scenario 4 generates ballots with the current fiducial mode and tests detection:

```bash
# View blank ballot with fiducial markers (PNG and PDF)
open scenario-4-fiducials/blank_with_fiducials.png
open scenario-4-fiducials/ballot_with_fiducials.pdf

# View filled ballot (with simulated marks)
open scenario-4-fiducials/blank_with_fiducials_filled.png

# Check fiducial detection results
cat scenario-4-fiducials/fiducial_debug.log
cat scenario-4-fiducials/metadata.json

# Check appreciation output (if detection ran)
cat scenario-4-fiducials/appreciation_output.txt
```

**Artifacts:**
- `blank_with_fiducials.png` - Blank ballot showing fiducial markers in corners
- `ballot_with_fiducials.pdf` - High-resolution PDF with embedded markers
- `blank_with_fiducials_filled.png` - Ballot with simulated filled bubbles
- `fiducial_debug.log` - Fiducial detection debug output
- `appreciation_output.txt` - Full appreciation script output
- `metadata.json` - Test metadata including fiducial mode

The markers appear in the **corners** of the ballot:
- **Black squares**: Traditional 10mm×10mm black rectangles
- **ArUco markers**: QR-code-like patterns with unique IDs (101-104)
- **AprilTag markers**: Similar to ArUco but different encoding (IDs 0-3)

## Environment

See `environment.json` for complete environment details.

**Key Dependencies:**
- PHP: $(php -r 'echo PHP_VERSION;')
- Python: $(python3 --version 2>&1 | cut -d' ' -f2)
- ImageMagick/Imagick: Available
- OpenCV: Available

**Fiducial Detection Support:**
- Black Squares: ✅ Always available
- ArUco: $([ "${ARUCO_AVAILABLE}" = "true" ] && echo "✅ Available" || echo "❌ Not available")
- AprilTag: $([ "${APRILTAG_AVAILABLE}" = "true" ] && echo "✅ Available" || echo "❌ Not available")
- Current Mode: \`${OMR_FIDUCIAL_MODE:-black_square}\`

## Viewing Results

### Visual Inspection
```bash
# View overlays for each scenario
open scenario-1-normal/overlay.png
open scenario-2-overvote/overlay.png
open scenario-3-faint/overlay.png
```

### Detailed Results
```bash
# View JSON results
cat scenario-1-normal/results.json | jq
cat scenario-2-overvote/results.json | jq
cat scenario-3-faint/results.json | jq
```

### Test Output
```bash
cat test-output.txt
```

## Notes

- All coordinates are in millimeters and converted to pixels at 300 DPI
- The `--no-align` flag is used to skip fiducial alignment for perfect synthetic images
- Template artifacts may cause false positives; filtered using fill_ratio >= 0.95 threshold
- Faint marks demonstrate the sensitivity/specificity tradeoff in mark detection

### Fiducial Alignment

The `--no-align` flag skips perspective correction because test images are perfect synthetic ballots. For real scanned ballots:

```bash
# Enable fiducial detection (removes --no-align)
python3 appreciate.py <image> <coords> --threshold 0.3

# Use specific fiducial mode
export OMR_FIDUCIAL_MODE=aruco
python3 appreciate.py <image> <coords> --threshold 0.3
```

**Fiducial Modes:**
- \`black_square\`: Traditional corner squares (default)
- \`aruco\`: ArUco markers with unique IDs (recommended for production)
- \`apriltag\`: AprilTag markers (maximum robustness)

To test with fiducial alignment, print the ballot PDF and scan it, then run appreciation without \`--no-align\`.

## Next Steps

1. Review overlay images for visual verification
2. Check results.json for detailed metrics
3. Compare fill_ratio values across scenarios
4. Adjust thresholds if needed for your scanner characteristics

---

*Generated by test-omr-appreciation.sh on $(date)*
EOFREADME

# Replace template variables in README
sed -i '' "s/\${RUN_TIMESTAMP}/${RUN_TIMESTAMP}/g" "${RUN_DIR}/README.md" 2>/dev/null || sed -i "s/\${RUN_TIMESTAMP}/${RUN_TIMESTAMP}/g" "${RUN_DIR}/README.md"
sed -i '' "s/\${TEST_STATUS}/${TEST_STATUS}/g" "${RUN_DIR}/README.md" 2>/dev/null || sed -i "s/\${TEST_STATUS}/${TEST_STATUS}/g" "${RUN_DIR}/README.md"
sed -i '' "s/\${TOTAL_TESTS}/${TOTAL_TESTS}/g" "${RUN_DIR}/README.md" 2>/dev/null || sed -i "s/\${TOTAL_TESTS}/${TOTAL_TESTS}/g" "${RUN_DIR}/README.md"
sed -i '' "s/\${TESTS_PASSED}/${TESTS_PASSED}/g" "${RUN_DIR}/README.md" 2>/dev/null || sed -i "s/\${TESTS_PASSED}/${TESTS_PASSED}/g" "${RUN_DIR}/README.md"
sed -i '' "s/\${TESTS_FAILED}/${TESTS_FAILED}/g" "${RUN_DIR}/README.md" 2>/dev/null || sed -i "s/\${TESTS_FAILED}/${TESTS_FAILED}/g" "${RUN_DIR}/README.md"

# Display results
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Test Results${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "Status: ${STATUS_COLOR}${TEST_STATUS}${NC}"
echo -e "Total:  ${TOTAL_TESTS}"
echo -e "Passed: ${GREEN}${TESTS_PASSED}${NC}"
echo -e "Failed: ${RED}${TESTS_FAILED}${NC}"
echo ""
echo -e "${BLUE}Artifacts saved to:${NC}"
echo -e "  ${RUN_DIR}"
echo ""
echo -e "${BLUE}Quick commands:${NC}"
echo -e "  ${YELLOW}cd ${RUN_DIR}${NC}"
echo -e "  ${YELLOW}cat README.md${NC}"
echo -e "  ${YELLOW}open scenario-1-normal/overlay.png${NC}"
if [ -d "${RUN_DIR}/scenario-5-quality-gates" ]; then
    echo -e "  ${YELLOW}cat scenario-5-quality-gates/summary.json${NC}"
fi
echo ""

# Create symlink to latest run
LATEST_LINK="storage/app/tests/omr-appreciation/latest"
rm -f "${LATEST_LINK}"
ln -s "runs/${RUN_TIMESTAMP}" "${LATEST_LINK}"
echo -e "${GREEN}✓ Symlink created: ${LATEST_LINK}${NC}"
echo ""

# Exit with appropriate code
if [ "${TEST_STATUS}" = "PASSED" ]; then
    exit 0
else
    exit 1
fi
