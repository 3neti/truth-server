#!/usr/bin/env bash
# OMR Appreciation Test Suite Orchestration
# Creates timestamped test runs with comprehensive artifacts matching deprecated script structure
# This wraps the modular run-simulation.sh with reporting and organization

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
LIB_DIR="${SCRIPT_DIR}/lib"

# Source common functions
source "${LIB_DIR}/common.sh"

# Default configuration
DEFAULT_CONFIG_DIR="config"
DEFAULT_TEST_ROOT="storage/app/tests/omr-appreciation"
DEFAULT_SCENARIOS=(normal overvote faint)

# Usage information
usage() {
    cat << EOF
Usage: $0 [OPTIONS]

Run comprehensive OMR appreciation test suite with timestamped artifacts.

OPTIONS:
    -c, --config DIR        Config directory (default: config)
    -s, --scenarios LIST    Comma-separated scenarios (default: normal,overvote,faint)
    -f, --fresh             Remove previous test runs
    -v, --verbose           Enable verbose logging
    -h, --help              Show this help message

EXAMPLES:
    # Run default test suite
    $0

    # Run with custom config (Barangay election)
    $0 --config resources/docs/simulation/config

    # Fresh run with specific scenarios
    $0 --fresh --scenarios normal,overvote,faint

OUTPUT STRUCTURE:
    storage/app/tests/omr-appreciation/
    ├── runs/
    │   └── YYYY-MM-DD_HHMMSS/
    │       ├── config/              # Election config snapshots
    │       ├── template/            # Generated ballot template
    │       ├── scenario-1-normal/   # Test scenario artifacts
    │       ├── test-results.json    # Summary JSON
    │       ├── environment.json     # Environment metadata
    │       └── README.md            # Comprehensive documentation
    └── latest -> runs/YYYY-MM-DD_HHMMSS  # Symlink to latest run

EOF
}

# Parse command line arguments
CONFIG_DIR="$DEFAULT_CONFIG_DIR"
SCENARIOS=("${DEFAULT_SCENARIOS[@]}")
FRESH_RUN=false
VERBOSE=false

while [[ $# -gt 0 ]]; do
    case $1 in
        -c|--config)
            CONFIG_DIR="$2"
            shift 2
            ;;
        -s|--scenarios)
            IFS=',' read -ra SCENARIOS <<< "$2"
            shift 2
            ;;
        -f|--fresh)
            FRESH_RUN=true
            shift
            ;;
        -v|--verbose)
            VERBOSE=true
            export LOG_LEVEL=DEBUG
            shift
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            log_error "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Create timestamped run directory
RUN_TIMESTAMP=$(date '+%Y-%m-%d_%H%M%S')
RUN_DIR="${PROJECT_ROOT}/${DEFAULT_TEST_ROOT}/runs/${RUN_TIMESTAMP}"

# Ensure test root exists
mkdir -p "${PROJECT_ROOT}/${DEFAULT_TEST_ROOT}/runs"

# Clean up if fresh run requested
if [[ "$FRESH_RUN" == true ]]; then
    log_info "Removing previous test runs..."
    rm -rf "${PROJECT_ROOT}/${DEFAULT_TEST_ROOT}/runs/"*
fi

# Banner
log_section "OMR Appreciation Test Suite"
log_info "Run ID: ${RUN_TIMESTAMP}"
log_info "Output: ${RUN_DIR}"
echo ""

# Step 1: Copy election configuration to artifacts
log_info "Copying election configuration to artifacts..."
CONFIG_ARTIFACT_DIR="${RUN_DIR}/config"
mkdir -p "$CONFIG_ARTIFACT_DIR"

if [[ -f "${CONFIG_DIR}/election.json" ]]; then
    cp "${CONFIG_DIR}/election.json" "${CONFIG_ARTIFACT_DIR}/election.json"
    log_success "election.json"
fi

if [[ -f "${CONFIG_DIR}/precinct.yaml" ]]; then
    cp "${CONFIG_DIR}/precinct.yaml" "${CONFIG_ARTIFACT_DIR}/precinct.yaml"
    log_success "precinct.yaml"
fi

if [[ -f "${CONFIG_DIR}/mapping.yaml" ]]; then
    cp "${CONFIG_DIR}/mapping.yaml" "${CONFIG_ARTIFACT_DIR}/mapping.yaml"
    log_success "mapping.yaml"
fi
echo ""

# Step 2: Capture environment information
log_info "Capturing environment info..."

# Detect fiducial capabilities
ARUCO_AVAILABLE="false"
APRILTAG_AVAILABLE="false"
if python3 -c "import cv2; cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)" 2>/dev/null; then
    ARUCO_AVAILABLE="true"
fi
if python3 -c "import apriltag" 2>/dev/null || python3 -c "from pupil_apriltags import Detector" 2>/dev/null; then
    APRILTAG_AVAILABLE="true"
fi

# Determine config type
CONFIG_TYPE="default"
if [[ "$CONFIG_DIR" == *"simulation"* ]]; then
    CONFIG_TYPE="simulation"
fi

cat > "${RUN_DIR}/environment.json" <<EOF
{
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "hostname": "$(hostname)",
  "user": "$(whoami)",
  "php_version": "$(php -r 'echo PHP_VERSION;')",
  "python_version": "$(python3 --version 2>&1 | cut -d' ' -f2)",
  "imagick_version": "$(php -r 'extension_loaded("imagick") ? print("available") : print("not available");' 2>/dev/null || echo 'not available')",
  "opencv_version": "$(python3 -c 'import cv2; print(cv2.__version__)' 2>/dev/null || echo 'not available')",
  "fiducial_support": {
    "black_square": true,
    "aruco": ${ARUCO_AVAILABLE},
    "apriltag": ${APRILTAG_AVAILABLE}
  },
  "omr_fiducial_mode": "${OMR_FIDUCIAL_MODE:-black_square}",
  "election_config": {
    "available": true,
    "path": "${CONFIG_DIR}",
    "type": "${CONFIG_TYPE}"
  },
  "scenarios": $(printf '%s\n' "${SCENARIOS[@]}" | jq -R . | jq -s .)
}
EOF

log_success "environment.json created"
echo ""

# Step 3: Run simulation with modular script
log_section "Running Simulation Tests"

# Export config for child processes
export CONFIG_DIR
export ELECTION_CONFIG_PATH="${CONFIG_DIR}"

# Build scenario arguments
SCENARIO_ARGS=$(IFS=,; echo "${SCENARIOS[*]}")

# Run simulation and capture exit code
SIMULATION_OUTPUT="${RUN_DIR}/simulation-output.txt"
if "${SCRIPT_DIR}/run-simulation.sh" \
    --config "$CONFIG_DIR" \
    --scenarios "$SCENARIO_ARGS" \
    --output "${RUN_DIR}/simulation-temp" \
    ${VERBOSE:+--verbose} \
    > "$SIMULATION_OUTPUT" 2>&1; then
    SIMULATION_STATUS="PASSED"
else
    SIMULATION_STATUS="FAILED"
fi

# Step 4: Reorganize simulation output to match deprecated structure
log_info "Reorganizing artifacts..."

# Move template to run directory
if [[ -d "${RUN_DIR}/simulation-temp/template" ]]; then
    mv "${RUN_DIR}/simulation-temp/template" "${RUN_DIR}/template"
fi

# Move scenarios and rename them
if [[ -d "${RUN_DIR}/simulation-temp/scenarios" ]]; then
    for scenario_dir in "${RUN_DIR}/simulation-temp/scenarios"/scenario-*; do
        if [[ -d "$scenario_dir" ]]; then
            scenario_name=$(basename "$scenario_dir")
            mv "$scenario_dir" "${RUN_DIR}/${scenario_name}"
            
            # Rename files to match deprecated script naming
            if [[ -f "${RUN_DIR}/${scenario_name}/ballot.png" ]]; then
                mv "${RUN_DIR}/${scenario_name}/ballot.png" "${RUN_DIR}/${scenario_name}/blank_filled.png"
            fi
            if [[ -f "${RUN_DIR}/${scenario_name}/appreciation_results.json" ]]; then
                mv "${RUN_DIR}/${scenario_name}/appreciation_results.json" "${RUN_DIR}/${scenario_name}/results.json"
            fi
            
            # Generate metadata.json from scenario.json, votes.json, and results.json
            if [[ -f "${RUN_DIR}/${scenario_name}/scenario.json" ]] && 
               [[ -f "${RUN_DIR}/${scenario_name}/votes.json" ]] && 
               [[ -f "${RUN_DIR}/${scenario_name}/results.json" ]]; then
                
                python3 << PYMETA
import json
import sys
from datetime import datetime

try:
    # Load files
    with open('${RUN_DIR}/${scenario_name}/scenario.json') as f:
        scenario = json.load(f)
    with open('${RUN_DIR}/${scenario_name}/votes.json') as f:
        votes = json.load(f)
    with open('${RUN_DIR}/${scenario_name}/results.json') as f:
        results_data = json.load(f)
    
    # Get bubbles filled from votes
    bubbles_filled = [bid for bid, vote in votes.items() if vote.get('filled', False)]
    
    # Get bubbles detected from results
    results = results_data.get('results', {})
    if isinstance(results, list):
        bubbles_detected = [r['id'] for r in results if r.get('filled', False)]
    else:
        bubbles_detected = [bid for bid, r in results.items() if r.get('filled', False)]
    
    # Create metadata matching deprecated script format
    metadata = {
        "scenario": scenario.get('scenario_type', 'unknown'),
        "description": scenario.get('description', ''),
        "bubbles_filled": sorted(bubbles_filled),
        "bubbles_detected": sorted(bubbles_detected),
        "timestamp": datetime.utcnow().isoformat() + '+00:00'
    }
    
    # Write metadata.json
    with open('${RUN_DIR}/${scenario_name}/metadata.json', 'w') as f:
        json.dump(metadata, f, indent=4)
    
    print(f"Metadata generated: {len(bubbles_filled)} filled, {len(bubbles_detected)} detected")
except Exception as e:
    print(f"Error generating metadata: {e}", file=sys.stderr)
    sys.exit(1)
PYMETA
            fi
        fi
    done
fi

# Clean up temp directory
rm -rf "${RUN_DIR}/simulation-temp"

log_success "Artifacts organized"
echo ""

# Step 5: Generate test-results.json
log_info "Generating test results summary..."

TESTS_PASSED=0
TESTS_FAILED=0

# Count passing/failing scenarios by checking for results.json
for scenario_dir in "${RUN_DIR}"/scenario-*; do
    if [[ -d "$scenario_dir" ]] && [[ -f "${scenario_dir}/results.json" ]]; then
        TESTS_PASSED=$((TESTS_PASSED + 1))
    elif [[ -d "$scenario_dir" ]]; then
        TESTS_FAILED=$((TESTS_FAILED + 1))
    fi
done

TOTAL_TESTS=$((TESTS_PASSED + TESTS_FAILED))

# Build scenarios array for JSON
SCENARIOS_JSON="["
first=true
for scenario_dir in "${RUN_DIR}"/scenario-*; do
    if [[ -d "$scenario_dir" ]]; then
        scenario_name=$(basename "$scenario_dir")
        if [[ "$first" == true ]]; then
            first=false
        else
            SCENARIOS_JSON+=","
        fi
        SCENARIOS_JSON+="{\"id\": \"${scenario_name}\", \"name\": \"${scenario_name}\", \"status\": \"executed\"}"
    fi
done
SCENARIOS_JSON+="]"

cat > "${RUN_DIR}/test-results.json" <<EOF
{
  "run_id": "${RUN_TIMESTAMP}",
  "status": "${SIMULATION_STATUS}",
  "summary": {
    "total": ${TOTAL_TESTS},
    "passed": ${TESTS_PASSED},
    "failed": ${TESTS_FAILED}
  },
  "scenarios": ${SCENARIOS_JSON}
}
EOF

log_success "test-results.json created"
echo ""

# Step 6: Generate comprehensive README.md
log_info "Generating README documentation..."

cat > "${RUN_DIR}/README.md" <<'EOFREADME'
# OMR Appreciation Test Run

## Test Information

**Run ID:** `RUN_TIMESTAMP_PLACEHOLDER`  
**Date:** DATE_PLACEHOLDER  
**Status:** **STATUS_PLACEHOLDER**

## Summary

- **Total Tests:** TOTAL_PLACEHOLDER
- **Passed:** ✅ PASSED_PLACEHOLDER
- **Failed:** ❌ FAILED_PLACEHOLDER

## Test Scenarios

SCENARIOS_PLACEHOLDER

## Template Files

- `template/coordinates.json` - Bubble coordinate mappings
- Generated ballot template and coordinates for this test run

## Configuration

See `config/` directory for election configuration snapshots used in this test run.

## Environment

See `environment.json` for complete environment details.

**Key Dependencies:**
- PHP: PHP_VERSION_PLACEHOLDER
- Python: PYTHON_VERSION_PLACEHOLDER
- ImageMagick/Imagick: IMAGICK_VERSION_PLACEHOLDER
- OpenCV: OPENCV_VERSION_PLACEHOLDER

**Fiducial Detection Support:**
- Black Squares: ✅ Always available
- ArUco: ARUCO_STATUS_PLACEHOLDER
- AprilTag: APRILTAG_STATUS_PLACEHOLDER
- Current Mode: `FIDUCIAL_MODE_PLACEHOLDER`

## Viewing Results

### Visual Inspection
```bash
# View overlays for each scenario
open scenario-*/overlay.png
```

### Detailed Results
```bash
# View JSON results
cat scenario-1-normal/results.json | jq
```

## Next Steps

1. Review overlay images for visual verification
2. Check results.json for detailed metrics
3. Compare fill_ratio values across scenarios
4. Adjust thresholds if needed for your scanner characteristics

---

*Generated by run-test-suite.sh on GENERATION_DATE_PLACEHOLDER*
EOFREADME

# Replace placeholders in README
sed -i.bak "s/RUN_TIMESTAMP_PLACEHOLDER/${RUN_TIMESTAMP}/g" "${RUN_DIR}/README.md"
sed -i.bak "s/DATE_PLACEHOLDER/$(date '+%Y-%m-%d %H:%M:%S %Z')/g" "${RUN_DIR}/README.md"
sed -i.bak "s/STATUS_PLACEHOLDER/${SIMULATION_STATUS}/g" "${RUN_DIR}/README.md"
sed -i.bak "s/TOTAL_PLACEHOLDER/${TOTAL_TESTS}/g" "${RUN_DIR}/README.md"
sed -i.bak "s/PASSED_PLACEHOLDER/${TESTS_PASSED}/g" "${RUN_DIR}/README.md"
sed -i.bak "s/FAILED_PLACEHOLDER/${TESTS_FAILED}/g" "${RUN_DIR}/README.md"
sed -i.bak "s/PHP_VERSION_PLACEHOLDER/$(php -r 'echo PHP_VERSION;')/g" "${RUN_DIR}/README.md"
sed -i.bak "s/PYTHON_VERSION_PLACEHOLDER/$(python3 --version 2>&1 | cut -d' ' -f2)/g" "${RUN_DIR}/README.md"
sed -i.bak "s/IMAGICK_VERSION_PLACEHOLDER/$(php -r 'extension_loaded("imagick") ? print("available") : print("not available");' 2>/dev/null || echo 'not available')/g" "${RUN_DIR}/README.md"
sed -i.bak "s/OPENCV_VERSION_PLACEHOLDER/$(python3 -c 'import cv2; print(cv2.__version__)' 2>/dev/null || echo 'not available')/g" "${RUN_DIR}/README.md"
sed -i.bak "s/ARUCO_STATUS_PLACEHOLDER/$([ "$ARUCO_AVAILABLE" = "true" ] && echo "✅ Available" || echo "❌ Not available")/g" "${RUN_DIR}/README.md"
sed -i.bak "s/APRILTAG_STATUS_PLACEHOLDER/$([ "$APRILTAG_AVAILABLE" = "true" ] && echo "✅ Available" || echo "❌ Not available")/g" "${RUN_DIR}/README.md"
sed -i.bak "s/FIDUCIAL_MODE_PLACEHOLDER/${OMR_FIDUCIAL_MODE:-black_square}/g" "${RUN_DIR}/README.md"
sed -i.bak "s/GENERATION_DATE_PLACEHOLDER/$(date)/g" "${RUN_DIR}/README.md"

# Generate scenario descriptions dynamically
SCENARIOS_TEXT=""
for scenario_dir in "${RUN_DIR}"/scenario-*; do
    if [[ -d "$scenario_dir" ]]; then
        scenario_name=$(basename "$scenario_dir")
        SCENARIOS_TEXT+="### ${scenario_name}\n"
        SCENARIOS_TEXT+="**Directory:** \`${scenario_name}/\`\n\n"
        SCENARIOS_TEXT+="**Artifacts:**\n"
        SCENARIOS_TEXT+="- \`blank_filled.png\` - Filled ballot image\n"
        SCENARIOS_TEXT+="- \`results.json\` - Appreciation results\n"
        SCENARIOS_TEXT+="- \`overlay.png\` - Visual overlay\n"
        SCENARIOS_TEXT+="- \`metadata.json\` - Scenario metadata\n\n"
    fi
done

# Insert scenarios into README (using perl for multiline replacement)
perl -i -pe "s/SCENARIOS_PLACEHOLDER/${SCENARIOS_TEXT}/g" "${RUN_DIR}/README.md"

# Clean up backup file
rm -f "${RUN_DIR}/README.md.bak"

log_success "README.md created"
echo ""

# Step 7: Create symlink to latest run
LATEST_LINK="${PROJECT_ROOT}/${DEFAULT_TEST_ROOT}/latest"
rm -f "$LATEST_LINK"
ln -s "runs/${RUN_TIMESTAMP}" "$LATEST_LINK"
log_success "Symlink created: ${DEFAULT_TEST_ROOT}/latest"
echo ""

# Display final results
log_section "Test Results"

if [[ "$SIMULATION_STATUS" == "PASSED" ]]; then
    log_success "Status: PASSED"
else
    log_error "Status: FAILED"
fi

echo "Total:  ${TOTAL_TESTS}"
echo -e "Passed: ${GREEN}${TESTS_PASSED}${NC}"
echo -e "Failed: ${RED}${TESTS_FAILED}${NC}"
echo ""

log_info "Artifacts saved to:"
echo "  ${RUN_DIR}"
echo ""

log_info "Quick commands:"
echo -e "  ${YELLOW}cd ${RUN_DIR}${NC}"
echo -e "  ${YELLOW}cat README.md${NC}"
echo -e "  ${YELLOW}open scenario-1-normal/overlay.png${NC}"
echo ""

# Exit with appropriate code
if [[ "$SIMULATION_STATUS" == "PASSED" ]]; then
    exit 0
else
    exit 1
fi
