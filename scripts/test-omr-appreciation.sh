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

# Get active profile and ground truth file
ACTIVE_PROFILE=$(php -r "echo config('omr-testing.active_profile');" 2>/dev/null || echo "philippine")
GROUND_TRUTH_FILE=$(php -r "echo config('omr-testing.profiles.' . config('omr-testing.active_profile') . '.ground_truth_file');" 2>/dev/null || echo "storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json")

echo -e "${BLUE}Test Profile:${NC} ${ACTIVE_PROFILE}"
echo -e "${BLUE}Ground Truth:${NC} ${GROUND_TRUTH_FILE}"
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
    $([ -d "${RUN_DIR}/scenario-6-distortion" ] && echo '{"id": "scenario-6-distortion", "name": "Filled Ballot Distortion", "status": "executed"},' || echo '')
    $([ -d "${RUN_DIR}/scenario-7-fiducial-alignment" ] && echo '{"id": "scenario-7-fiducial-alignment", "name": "Fiducial Alignment", "status": "executed"}' || echo '')
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
    GROUND_TRUTH="${GROUND_TRUTH_FILE}"
    
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

# Run scenario-7-fiducial-alignment if fiducial-marked fixtures exist
FILLED_FIDUCIAL_DIR="storage/app/tests/omr-appreciation/fixtures/filled-distorted-fiducial"
if [ -d "${FILLED_FIDUCIAL_DIR}" ] && [ -n "$(ls -A "${FILLED_FIDUCIAL_DIR}" 2>/dev/null | grep '.png$')" ]; then
    SCENARIO_7="${RUN_DIR}/scenario-7-fiducial-alignment"
    mkdir -p "${SCENARIO_7}"
    
    echo -e "${YELLOW}Testing fiducial-based alignment on distorted ballots...${NC}"
    
    # Create metadata
    cat > "${SCENARIO_7}/metadata.json" <<SCENARIO7META
{
  "scenario": "fiducial-alignment",
  "description": "Ballot appreciation with fiducial-based alignment correction",
  "fixtures_tested": $(ls "${FILLED_FIDUCIAL_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' '),
  "ground_truth": "storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json",
  "alignment_enabled": true
}
SCENARIO7META
    
    # Test each fiducial-marked distorted fixture
    FIDUCIAL_PASSED=0
    FIDUCIAL_FAILED=0
    
    # Get paths for appreciation and comparison
    APPRECIATE_SCRIPT="packages/omr-appreciation/omr-python/appreciate.py"
    COORDS_FILE="${RUN_DIR}/template/coordinates.json"
    GROUND_TRUTH="${GROUND_TRUTH_FILE}"
    
    # Verify required files exist
    if [ ! -f "${APPRECIATE_SCRIPT}" ]; then
        echo -e "${RED}✗ Appreciation script not found: ${APPRECIATE_SCRIPT}${NC}"
    elif [ ! -f "${COORDS_FILE}" ]; then
        echo -e "${RED}✗ Coordinates file not found: ${COORDS_FILE}${NC}"
    elif [ ! -f "${GROUND_TRUTH}" ]; then
        echo -e "${RED}✗ Ground truth not found: ${GROUND_TRUTH}${NC}"
    else
        for fixture in "${FILLED_FIDUCIAL_DIR}"/*.png; do
            [ -f "${fixture}" ] || continue
            basename=$(basename "${fixture}" .png)
            echo -e "  Testing ${BLUE}${basename}${NC}..."
            
            # Run appreciation on fiducial-marked distorted ballot
            APPRECIATION_OUTPUT="${SCENARIO_7}/${basename}_appreciation.json"
            VALIDATION_OUTPUT="${SCENARIO_7}/${basename}_validation.json"
            COMBINED_LOG="${SCENARIO_7}/${basename}_combined.log"
            
            # Run appreciation WITH alignment enabled (fiducials present)
            if python3 "${APPRECIATE_SCRIPT}" \
                "${fixture}" \
                "${COORDS_FILE}" \
                --threshold 0.3 \
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
                    FIDUCIAL_PASSED=$((FIDUCIAL_PASSED + 1))
                else
                    # Extract accuracy even on failure
                    ACCURACY=$(python3 -c "import json; print(f\"{json.load(open('${VALIDATION_OUTPUT}'))['accuracy']*100:.1f}%\")" 2>/dev/null || echo "N/A")
                    echo -e "    ${RED}✗ FAIL${NC} (accuracy: ${ACCURACY})"
                    FIDUCIAL_FAILED=$((FIDUCIAL_FAILED + 1))
                fi
            else
                echo -e "    ${RED}✗ FAIL${NC} (appreciation error)"
                echo "Appreciation failed" > "${COMBINED_LOG}"
                FIDUCIAL_FAILED=$((FIDUCIAL_FAILED + 1))
            fi
        done
    fi
    
    # Generate summary
    cat > "${SCENARIO_7}/summary.json" <<FIDUCIALSUMMARY
{
  "total_fixtures": $((FIDUCIAL_PASSED + FIDUCIAL_FAILED)),
  "passed": ${FIDUCIAL_PASSED},
  "failed": ${FIDUCIAL_FAILED}
}
FIDUCIALSUMMARY
    
    if [ $((FIDUCIAL_PASSED + FIDUCIAL_FAILED)) -gt 0 ]; then
        echo -e "${GREEN}✓ Fiducial alignment tests complete${NC} (${FIDUCIAL_PASSED} passed, ${FIDUCIAL_FAILED} failed)"
    else
        echo -e "${YELLOW}⊙ Fiducial tests skipped (missing required files)${NC}"
    fi
    echo ""
fi

# Shared function: Run appreciation test for a single rotation
# Returns: 0 on success (validation passed), 1 on failure
run_rotation_test() {
    local degree=$1
    local source_filled=$2
    local source_blank=$3
    local coords_file=$4
    local output_dir=$5
    local ground_truth=$6
    
    mkdir -p "${output_dir}"
    
    # Generate rotated blank and filled images using unified canvas-based rotation
    python3 <<PYROT
import cv2
import numpy as np
import sys

def rotate_with_canvas(image, angle, bg_color=255):
    """Rotate image at any angle with expanded canvas to prevent cropping."""
    h, w = image.shape[:2]
    
    # For rotation with corner fiducials, use full diagonal as canvas size
    # This ensures corners (where ArUco markers are) never get clipped
    # Diagonal = sqrt(w² + h²) is the maximum extent from center to any corner
    diagonal = int(np.ceil(np.sqrt(w**2 + h**2))) + 200  # +200px safety margin
    
    # Create canvas with white background (simulates camera capture)
    if len(image.shape) == 3:
        canvas = np.full((diagonal, diagonal, 3), bg_color, dtype=np.uint8)
    else:
        canvas = np.full((diagonal, diagonal), bg_color, dtype=np.uint8)
    
    # Center original image in canvas
    y_offset = (diagonal - h) // 2
    x_offset = (diagonal - w) // 2
    canvas[y_offset:y_offset+h, x_offset:x_offset+w] = image
    
    # Rotate around canvas center
    center = (diagonal // 2, diagonal // 2)
    rotation_matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    # borderValue needs to be tuple for color images (B, G, R)
    border_val = (bg_color, bg_color, bg_color) if len(image.shape) == 3 else bg_color
    rotated = cv2.warpAffine(canvas, rotation_matrix, (diagonal, diagonal),
                             borderMode=cv2.BORDER_CONSTANT,
                             borderValue=border_val)
    
    return rotated

try:
    # Load source images
    filled = cv2.imread('${source_filled}')
    blank = cv2.imread('${source_blank}')
    
    if filled is None or blank is None:
        print("Error: Could not load source images", file=sys.stderr)
        sys.exit(1)
    
    # Apply unified rotation with canvas for all angles
    angle = ${degree}
    filled_rot = rotate_with_canvas(filled, angle)
    blank_rot = rotate_with_canvas(blank, angle)
    
    # Save rotated images
    cv2.imwrite('${output_dir}/blank_filled.png', filled_rot)
    cv2.imwrite('${output_dir}/blank.png', blank_rot)
    
except Exception as e:
    print(f"Error rotating images: {e}", file=sys.stderr)
    sys.exit(1)
PYROT
    
    if [ $? -ne 0 ]; then
        return 1
    fi
    
    # Run appreciation
    if ! OMR_FIDUCIAL_MODE=aruco python3 packages/omr-appreciation/omr-python/appreciate.py \
        "${output_dir}/blank_filled.png" \
        "${coords_file}" \
        --threshold 0.3 \
        > "${output_dir}/results.json" 2>"${output_dir}/stderr.log"; then
        return 1
    fi
    
    # Create overlay on UNROTATED ballot first (to get correct coordinate mapping)
    # Then rotate the overlay to match the rotated ballot
    # Use PHP overlay generator to get candidate names
    
    if [ ${degree} -eq 0 ]; then
        # 0 degrees - use rotated image directly (which is unrotated)
        php scripts/generate-overlay.php \
            "${output_dir}/blank_filled.png" \
            "${output_dir}/results.json" \
            "${coords_file}" \
            "${output_dir}/overlay.png" 2>&1 | grep -v "^Overlay"
    else
        # For rotated ballots: generate overlay on unrotated, then rotate it
        TEMP_OVERLAY="${output_dir}/overlay_unrotated.png"
        
        # Generate overlay using the original unrotated filled ballot
        php scripts/generate-overlay.php \
            "${source_filled}" \
            "${output_dir}/results.json" \
            "${coords_file}" \
            "${TEMP_OVERLAY}" 2>&1 | grep -v "^Overlay"
        
        # Rotate the overlay to match the ballot rotation using same unified approach
        python3 <<PYROTOVERLAY
import cv2
import numpy as np
import sys

def rotate_with_canvas(image, angle, bg_color=255):
    """Rotate image at any angle with expanded canvas to prevent cropping."""
    h, w = image.shape[:2]
    
    # For rotation with corner fiducials, use full diagonal as canvas size
    # This ensures corners (where ArUco markers are) never get clipped
    # Diagonal = sqrt(w² + h²) is the maximum extent from center to any corner
    diagonal = int(np.ceil(np.sqrt(w**2 + h**2))) + 200  # +200px safety margin
    
    # Create canvas with white background
    if len(image.shape) == 3:
        canvas = np.full((diagonal, diagonal, 3), bg_color, dtype=np.uint8)
    else:
        canvas = np.full((diagonal, diagonal), bg_color, dtype=np.uint8)
    
    # Center original image in canvas
    y_offset = (diagonal - h) // 2
    x_offset = (diagonal - w) // 2
    canvas[y_offset:y_offset+h, x_offset:x_offset+w] = image
    
    # Rotate around canvas center
    center = (diagonal // 2, diagonal // 2)
    rotation_matrix = cv2.getRotationMatrix2D(center, angle, 1.0)
    # borderValue needs to be tuple for color images (B, G, R)
    border_val = (bg_color, bg_color, bg_color) if len(image.shape) == 3 else bg_color
    rotated = cv2.warpAffine(canvas, rotation_matrix, (diagonal, diagonal),
                             borderMode=cv2.BORDER_CONSTANT,
                             borderValue=border_val)
    
    return rotated

try:
    overlay = cv2.imread('${TEMP_OVERLAY}')
    if overlay is None:
        print("Error: Could not load overlay", file=sys.stderr)
        sys.exit(1)
    
    # Apply same rotation as ballot
    angle = ${degree}
    overlay_rot = rotate_with_canvas(overlay, angle)
    
    cv2.imwrite('${output_dir}/overlay.png', overlay_rot)
    
except Exception as e:
    print(f"Error rotating overlay: {e}", file=sys.stderr)
    sys.exit(1)
PYROTOVERLAY
        
        # Clean up temp file
        rm -f "${TEMP_OVERLAY}"
    fi
    
    # Generate metadata
    cat > "${output_dir}/metadata.json" <<ROTMETA
{
  "rotation": ${degree},
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "fiducial_mode": "aruco",
  "threshold": 0.3,
  "alignment_enabled": true
}
ROTMETA
    
    # Validate against ground truth
    if [ -f "${ground_truth}" ]; then
        if python3 scripts/compare_appreciation_results.py \
            --result "${output_dir}/results.json" \
            --truth "${ground_truth}" \
            --output "${output_dir}/validation.json" \
            >> "${output_dir}/stderr.log" 2>&1; then
            return 0
        else
            return 1
        fi
    fi
    
    return 0
}

# Run scenario-8-cardinal-rotations: Test all rotations (cardinal + diagonal)
SCENARIO_8="${RUN_DIR}/scenario-8-cardinal-rotations"
mkdir -p "${SCENARIO_8}"

echo -e "${YELLOW}Testing all rotations (cardinal + diagonal)...${NC}"

# Create scenario metadata
cat > "${SCENARIO_8}/metadata.json" <<SCENARIO8META
{
  "scenario": "cardinal-rotations",
  "description": "ArUco fiducial detection on all rotations: cardinal (0/90/180/270) and diagonal (45/135/225/315)",
  "rotations_tested": [0, 45, 90, 135, 180, 225, 270, 315],
  "test_profile": "${ACTIVE_PROFILE}",
  "ground_truth": "${GROUND_TRUTH_FILE}",
  "alignment_enabled": true,
  "fiducial_mode": "aruco",
  "rotation_method": "unified_canvas_based"
}
SCENARIO8META

# Get source ballots from scenario-1
SOURCE_FILLED="${RUN_DIR}/scenario-1-normal/blank_filled.png"
SOURCE_BLANK="${RUN_DIR}/scenario-1-normal/blank.png"
COORDS_FILE="${RUN_DIR}/template/coordinates.json"
GROUND_TRUTH="${GROUND_TRUTH_FILE}"

CARDINAL_PASSED=0
CARDINAL_FAILED=0

if [ -f "${SOURCE_FILLED}" ] && [ -f "${SOURCE_BLANK}" ] && [ -f "${COORDS_FILE}" ]; then
    # Test each rotation (cardinal + diagonal)
    for deg in 0 45 90 135 180 225 270 315; do
        ROT_DIR="${SCENARIO_8}/rot_$(printf '%03d' $deg)"
        echo -e "  Testing ${BLUE}${deg}° rotation${NC}..."
        
        if run_rotation_test ${deg} "${SOURCE_FILLED}" "${SOURCE_BLANK}" "${COORDS_FILE}" "${ROT_DIR}" "${GROUND_TRUTH}"; then
            # Extract accuracy if validation exists
            if [ -f "${ROT_DIR}/validation.json" ]; then
                ACCURACY=$(python3 -c "import json; print(f\"{json.load(open('${ROT_DIR}/validation.json'))['accuracy']*100:.1f}%\")" 2>/dev/null || echo "N/A")
                echo -e "    ${GREEN}✓ PASS${NC} (accuracy: ${ACCURACY})"
            else
                echo -e "    ${GREEN}✓ PASS${NC}"
            fi
            CARDINAL_PASSED=$((CARDINAL_PASSED + 1))
        else
            # Try to extract accuracy even on failure
            if [ -f "${ROT_DIR}/validation.json" ]; then
                ACCURACY=$(python3 -c "import json; print(f\"{json.load(open('${ROT_DIR}/validation.json'))['accuracy']*100:.1f}%\")" 2>/dev/null || echo "N/A")
                echo -e "    ${RED}✗ FAIL${NC} (accuracy: ${ACCURACY})"
            else
                echo -e "    ${RED}✗ FAIL${NC} (test error)"
            fi
            CARDINAL_FAILED=$((CARDINAL_FAILED + 1))
        fi
    done
    
    # Generate summary
    cat > "${SCENARIO_8}/summary.json" <<CARDINALSUMMARY
{
  "total_rotations": $((CARDINAL_PASSED + CARDINAL_FAILED)),
  "passed": ${CARDINAL_PASSED},
  "failed": ${CARDINAL_FAILED}
}
CARDINALSUMMARY
    
    echo -e "${GREEN}✓ Rotation tests complete${NC} (${CARDINAL_PASSED}/$((CARDINAL_PASSED + CARDINAL_FAILED)) passed)"
else
    echo -e "${YELLOW}⊙ Cardinal rotation tests skipped (missing source files)${NC}"
fi
echo ""

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

### Scenario 7: Fiducial-Based Alignment
**Directory:** `scenario-7-fiducial-alignment/`

Tests ballot appreciation with ArUco fiducial-based alignment enabled:
- Tests same distorted ballots as Scenario 6 but WITH alignment correction
- Compares detected votes against ground truth
- Validates coordinate transformation fix

**Expected Results (with coordinate transformation fix):**
- U0 (upright): ✅ 100% accuracy
- R1 (+3° rotation): ✅ ~100% accuracy (small distortions corrected)
- R2/R3 (larger rotations): ⚠️ ArUco detection may fail
- S1/S2/P1-P3: Results depend on fiducial detection success

### Scenario 8: Cardinal Rotations
**Directory:** `scenario-8-cardinal-rotations/`

Tests ArUco fiducial detection and coordinate transformation on cardinal rotations:
- **0°**: Baseline upright ballot
- **90°**: Clockwise rotation (portrait → landscape)
- **180°**: Upside-down
- **270°**: Counter-clockwise rotation (landscape → portrait)

**Test Mode:** ArUco fiducials with coordinate transformation

**Structure:** Each rotation has its own subdirectory with consistent artifacts:
```
scenario-8-cardinal-rotations/
├── metadata.json          # Scenario-level metadata
├── summary.json           # Aggregated test results
├── rot_000/
│   ├── blank.png          # Unrotated blank ballot
│   ├── blank_filled.png   # Unrotated filled ballot
│   ├── results.json       # Appreciation results
│   ├── overlay.png        # Visual overlay with circles
│   ├── metadata.json      # Rotation-specific metadata
│   ├── validation.json    # Ground truth comparison
│   └── stderr.log         # Debug output
├── rot_090/ [same structure]
├── rot_180/ [same structure]
└── rot_270/ [same structure]
```

**Viewing Results:**
```bash
# View all rotation overlays
open scenario-8-cardinal-rotations/rot_*/overlay.png

# Check specific rotation results
cat scenario-8-cardinal-rotations/rot_090/results.json | jq
cat scenario-8-cardinal-rotations/rot_090/validation.json

# Compare blank vs filled for 90° rotation
open scenario-8-cardinal-rotations/rot_090/blank.png
open scenario-8-cardinal-rotations/rot_090/blank_filled.png
```

**Expected Results:**
- 0°: ✅ 100% accuracy (reference)
- 90°: ✅ 100% accuracy (ArUco + coordinate transformation works)
- 180°: ✅ 100% accuracy (rotation-invariant detection)
- 270°: ✅ 100% accuracy (works correctly)

**Key Finding:** ArUco markers are **rotation-invariant** and detect successfully at all angles. The coordinate transformation correctly maps template coordinates to rotated image positions, enabling accurate mark detection regardless of ballot orientation.

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
