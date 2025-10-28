#!/bin/bash
# Fiducial Detection Test Script
# Tests all three fiducial modes (black_square, aruco, apriltag) with a ballot image

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Default values
BALLOT_IMAGE=""
COORDS_JSON=""
OUTPUT_DIR="storage/app/tests/fiducial-detection"
SKIP_ARUCO=false
SKIP_APRILTAG=false
SHOW_VISUAL=false

# Parse arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --image)
            BALLOT_IMAGE="$2"
            shift 2
            ;;
        --coords)
            COORDS_JSON="$2"
            shift 2
            ;;
        --output)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        --skip-aruco)
            SKIP_ARUCO=true
            shift
            ;;
        --skip-apriltag)
            SKIP_APRILTAG=true
            shift
            ;;
        --show)
            SHOW_VISUAL=true
            shift
            ;;
        --help)
            echo "Usage: $0 --image <path> --coords <path> [options]"
            echo ""
            echo "Options:"
            echo "  --image PATH       Path to ballot image (PNG/JPG)"
            echo "  --coords PATH      Path to coordinates JSON"
            echo "  --output DIR       Output directory (default: storage/app/tests/fiducial-detection)"
            echo "  --skip-aruco       Skip ArUco marker test"
            echo "  --skip-apriltag    Skip AprilTag marker test"
            echo "  --show             Display debug images after generation"
            echo "  --help             Show this help message"
            echo ""
            echo "Example:"
            echo "  $0 --image scanned_ballot.png --coords coords.json --show"
            exit 0
            ;;
        *)
            echo "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# Validate required arguments
if [ -z "$BALLOT_IMAGE" ] || [ -z "$COORDS_JSON" ]; then
    echo -e "${RED}Error: --image and --coords are required${NC}"
    echo "Use --help for usage information"
    exit 1
fi

if [ ! -f "$BALLOT_IMAGE" ]; then
    echo -e "${RED}Error: Image file not found: $BALLOT_IMAGE${NC}"
    exit 1
fi

if [ ! -f "$COORDS_JSON" ]; then
    echo -e "${RED}Error: Coordinates file not found: $COORDS_JSON${NC}"
    exit 1
fi

# Create timestamp for this test run
RUN_TIMESTAMP=$(date '+%Y-%m-%d_%H%M%S')
RUN_DIR="${OUTPUT_DIR}/${RUN_TIMESTAMP}"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Fiducial Detection Test Suite${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Image:${NC} ${BALLOT_IMAGE}"
echo -e "${BLUE}Coords:${NC} ${COORDS_JSON}"
echo -e "${BLUE}Output:${NC} ${RUN_DIR}"
echo ""

# Create run directory
mkdir -p "${RUN_DIR}"

# Detect available fiducial libraries
echo -e "${YELLOW}Detecting fiducial capabilities...${NC}"

ARUCO_AVAILABLE=false
APRILTAG_AVAILABLE=false

if python3 -c "import cv2; cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)" 2>/dev/null; then
    ARUCO_AVAILABLE=true
    echo -e "${GREEN}✓ ArUco detection available${NC}"
else
    echo -e "${YELLOW}⚠ ArUco detection unavailable${NC}"
fi

if python3 -c "import apriltag" 2>/dev/null || python3 -c "from pupil_apriltags import Detector" 2>/dev/null; then
    APRILTAG_AVAILABLE=true
    echo -e "${GREEN}✓ AprilTag detection available${NC}"
else
    echo -e "${YELLOW}⚠ AprilTag detection unavailable${NC}"
fi

echo -e "${GREEN}✓ Black square detection available (always)${NC}"
echo ""

# Test modes
MODES=("black_square")
[ "$ARUCO_AVAILABLE" = true ] && [ "$SKIP_ARUCO" = false ] && MODES+=("aruco")
[ "$APRILTAG_AVAILABLE" = true ] && [ "$SKIP_APRILTAG" = false ] && MODES+=("apriltag")

# Results summary
RESULTS=()

# Run tests for each mode
for MODE in "${MODES[@]}"; do
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}  Testing: ${MODE}${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    
    MODE_DIR="${RUN_DIR}/${MODE}"
    mkdir -p "${MODE_DIR}"
    
    # Set environment variable
    export OMR_FIDUCIAL_MODE="${MODE}"
    
    # Run debug script
    DEBUG_SCRIPT="scripts/debug_fiducial_detection.py"
    
    if [ ! -f "$DEBUG_SCRIPT" ]; then
        echo -e "${RED}Error: Debug script not found: $DEBUG_SCRIPT${NC}"
        exit 1
    fi
    
    echo -e "${YELLOW}Running fiducial detection...${NC}"
    
    START_TIME=$(date +%s)
    
    if python3 "$DEBUG_SCRIPT" \
        "$BALLOT_IMAGE" \
        --mode "$MODE" \
        --template "$COORDS_JSON" \
        --output "$MODE_DIR" \
        --grid > "${MODE_DIR}/debug.log" 2>&1; then
        
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        
        echo -e "${GREEN}✓ Detection successful${NC}"
        echo -e "${BLUE}Duration:${NC} ${DURATION}s"
        
        RESULTS+=("${MODE}:SUCCESS:${DURATION}s")
        
        # Check if output files were created
        if [ -f "${MODE_DIR}/ballot_fiducials.png" ]; then
            echo -e "${GREEN}✓ Overlay image saved${NC}"
        fi
        
        if [ -f "${MODE_DIR}/ballot_aligned.png" ]; then
            echo -e "${GREEN}✓ Aligned image saved${NC}"
        fi
    else
        END_TIME=$(date +%s)
        DURATION=$((END_TIME - START_TIME))
        
        echo -e "${RED}✗ Detection failed${NC}"
        echo -e "${YELLOW}See log: ${MODE_DIR}/debug.log${NC}"
        
        RESULTS+=("${MODE}:FAILED:${DURATION}s")
    fi
    
    echo ""
done

# Generate summary report
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Test Summary${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

PASSED=0
FAILED=0

for RESULT in "${RESULTS[@]}"; do
    IFS=':' read -r MODE STATUS DURATION <<< "$RESULT"
    
    if [ "$STATUS" = "SUCCESS" ]; then
        echo -e "${GREEN}✓ ${MODE}${NC} - ${DURATION}"
        PASSED=$((PASSED + 1))
    else
        echo -e "${RED}✗ ${MODE}${NC} - ${DURATION}"
        FAILED=$((FAILED + 1))
    fi
done

echo ""
echo -e "${BLUE}Total:${NC} $((PASSED + FAILED)) tests"
echo -e "${GREEN}Passed:${NC} ${PASSED}"
echo -e "${RED}Failed:${NC} ${FAILED}"
echo ""

# Generate JSON summary
cat > "${RUN_DIR}/summary.json" <<EOF
{
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "image": "${BALLOT_IMAGE}",
  "coordinates": "${COORDS_JSON}",
  "modes_tested": [$(printf '"%s",' "${MODES[@]}" | sed 's/,$//')],
  "results": {
    "total": $((PASSED + FAILED)),
    "passed": ${PASSED},
    "failed": ${FAILED}
  },
  "capabilities": {
    "black_square": true,
    "aruco": ${ARUCO_AVAILABLE},
    "apriltag": ${APRILTAG_AVAILABLE}
  }
}
EOF

# Generate README
cat > "${RUN_DIR}/README.md" <<EOFREADME
# Fiducial Detection Test Results

**Run ID:** \`${RUN_TIMESTAMP}\`  
**Date:** $(date '+%Y-%m-%d %H:%M:%S %Z')  
**Image:** \`${BALLOT_IMAGE}\`

## Summary

- **Total Tests:** $((PASSED + FAILED))
- **Passed:** ✅ ${PASSED}
- **Failed:** ❌ ${FAILED}

## Test Modes

$(for RESULT in "${RESULTS[@]}"; do
    IFS=':' read -r MODE STATUS DURATION <<< "$RESULT"
    if [ "$STATUS" = "SUCCESS" ]; then
        echo "### ${MODE} - ✅ SUCCESS (${DURATION})"
        echo ""
        echo "**Artifacts:**"
        echo "- \`${MODE}/ballot_fiducials.png\` - Detection overlay"
        echo "- \`${MODE}/ballot_aligned.png\` - Aligned image"
        echo "- \`${MODE}/debug.log\` - Debug output"
        echo ""
    else
        echo "### ${MODE} - ❌ FAILED (${DURATION})"
        echo ""
        echo "Detection failed. Check \`${MODE}/debug.log\` for details."
        echo ""
    fi
done)

## Viewing Results

\`\`\`bash
# View detection overlays
$(for MODE in "${MODES[@]}"; do
    echo "open ${MODE}/ballot_fiducials.png"
done)

# View aligned images
$(for MODE in "${MODES[@]}"; do
    echo "open ${MODE}/ballot_aligned.png"
done)
\`\`\`

## Capabilities

- **Black Square:** ✅ Always available
- **ArUco:** $([ "$ARUCO_AVAILABLE" = true ] && echo "✅ Available" || echo "❌ Not available")
- **AprilTag:** $([ "$APRILTAG_AVAILABLE" = true ] && echo "✅ Available" || echo "❌ Not available")

---

*Generated by test-fiducial-detection.sh on $(date)*
EOFREADME

echo -e "${BLUE}Artifacts saved to:${NC}"
echo -e "  ${RUN_DIR}"
echo ""

# Show visual results if requested
if [ "$SHOW_VISUAL" = true ]; then
    echo -e "${YELLOW}Opening visual results...${NC}"
    for MODE in "${MODES[@]}"; do
        if [ -f "${RUN_DIR}/${MODE}/ballot_fiducials.png" ]; then
            open "${RUN_DIR}/${MODE}/ballot_fiducials.png" 2>/dev/null || \
            xdg-open "${RUN_DIR}/${MODE}/ballot_fiducials.png" 2>/dev/null || \
            echo "  (Cannot display ${MODE}/ballot_fiducials.png automatically)"
        fi
    done
fi

echo -e "${BLUE}Quick commands:${NC}"
echo -e "  ${YELLOW}cd ${RUN_DIR}${NC}"
echo -e "  ${YELLOW}cat README.md${NC}"
echo -e "  ${YELLOW}cat summary.json | jq${NC}"
echo ""

# Exit with appropriate code
if [ "${FAILED}" -gt 0 ]; then
    exit 1
else
    exit 0
fi
