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
DEFAULT_CONFIG_DIR="resources/docs/simulation/config"
DEFAULT_TEST_ROOT="storage/app/private/simulation"
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

AVAILABLE SCENARIOS:
    Basic:
      normal          - Clean ballot with clear marks
      overvote        - Ballot with overvoted positions
      faint           - Ballot with faint/light marks
    
    Advanced:
      fiducials       - Fiducial marker detection tests
      quality-gates   - Geometric distortion quality metrics
      distortion      - Distortion without alignment correction
      alignment       - Distortion with fiducial alignment
      rotations       - Full 360° rotation tests (0-315°)

EXAMPLES:
    # Run default test suite (basic scenarios only)
    $0

    # Run with custom config (Barangay election)
    $0 --config resources/docs/simulation/config

    # Run all scenarios (basic + advanced)
    $0 --scenarios normal,overvote,faint,fiducials,quality-gates,distortion,alignment,rotations
    
    # Run only rotation tests
    $0 --scenarios normal,rotations

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

# Generate config summary
log_info "Generating config summary..."
python3 << PYSUMMARY
import json
import os
from datetime import datetime

summary_lines = []
summary_lines.append("ELECTION CONFIGURATION SUMMARY")
summary_lines.append("="*30)
summary_lines.append("")
summary_lines.append(f"Generated: {datetime.now().strftime('%Y-%m-%d %H:%M:%S')}")
summary_lines.append("")

# Parse election.json if available
if os.path.exists('${CONFIG_ARTIFACT_DIR}/election.json'):
    with open('${CONFIG_ARTIFACT_DIR}/election.json') as f:
        election = json.load(f)
    
    # Positions
    if 'positions' in election:
        summary_lines.append("POSITIONS:")
        summary_lines.append("-" * 10)
        for pos in election['positions']:
            summary_lines.append(f"  • {pos['name']} ({pos['code']})")
            summary_lines.append(f"    Max selections: {pos.get('count', 1)}")
        summary_lines.append("")
    
    # Candidates
    if 'candidates' in election:
        summary_lines.append("CANDIDATES:")
        summary_lines.append("-" * 11)
        for pos_code, candidates in election['candidates'].items():
            summary_lines.append(f"  {pos_code}: {len(candidates)} candidates")
        summary_lines.append("")

# Note about YAML files
try:
    import yaml
    yaml_available = True
except ImportError:
    yaml_available = False

if not yaml_available:
    summary_lines.append("PRECINCT:")
    summary_lines.append("-" * 9)
    summary_lines.append("  (PyYAML not available - showing raw file)")
    if os.path.exists('${CONFIG_ARTIFACT_DIR}/precinct.yaml'):
        with open('${CONFIG_ARTIFACT_DIR}/precinct.yaml') as f:
            for i, line in enumerate(f):
                if i < 10:  # First 10 lines only
                    summary_lines.append(line.rstrip())
    summary_lines.append("")
    
    summary_lines.append("BALLOT MAPPING:")
    summary_lines.append("-" * 15)
    summary_lines.append("  (PyYAML not available - showing raw file snippet)")
    if os.path.exists('${CONFIG_ARTIFACT_DIR}/mapping.yaml'):
        with open('${CONFIG_ARTIFACT_DIR}/mapping.yaml') as f:
            for i, line in enumerate(f):
                if i < 5:  # First 5 lines only
                    summary_lines.append(line.rstrip())
    summary_lines.append("  ...")
    summary_lines.append("")

# List files
summary_lines.append("FILES:")
summary_lines.append("-" * 6)
import subprocess
try:
    result = subprocess.run(['ls', '-lh', '${CONFIG_ARTIFACT_DIR}'], 
                          capture_output=True, text=True, check=True)
    for line in result.stdout.strip().split('\n')[1:]:  # Skip 'total' line
        summary_lines.append(line)
except:
    summary_lines.append("  (Could not list files)")

# Write summary
with open('${CONFIG_ARTIFACT_DIR}/summary.txt', 'w') as f:
    f.write('\n'.join(summary_lines))
    f.write('\n')

print(f"Config summary generated")
PYSUMMARY

log_success "summary.txt"
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

# Step 3: Create ground truth file if not exists
GROUND_TRUTH="storage/app/tests/omr-appreciation/fixtures/filled-ballot-ground-truth.json"
if [[ ! -f "$GROUND_TRUTH" ]] && [[ " ${SCENARIOS[@]} " =~ " normal " ]]; then
    log_info "Creating ground truth file from scenario-1..."
    mkdir -p "$(dirname "$GROUND_TRUTH")"
fi

# Step 4: Run simulation with modular script
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
            # blank.png stays as-is (unfilled template)
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
from datetime import datetime, timezone

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
    
    # Get bubbles detected from results (handle both 'results' and 'bubbles' formats)
    results = results_data.get('results') or results_data.get('bubbles', [])
    if isinstance(results, list):
        bubbles_detected = [r.get('id') or r.get('bubble_id') for r in results if r.get('filled', False)]
    else:
        bubbles_detected = [bid for bid, r in results.items() if r.get('filled', False)]
    
    # Create metadata matching deprecated script format
    metadata = {
        "scenario": scenario.get('scenario_type', 'unknown'),
        "description": scenario.get('description', ''),
        "bubbles_filled": sorted(bubbles_filled),
        "bubbles_detected": sorted(bubbles_detected),
        "timestamp": datetime.now(timezone.utc).isoformat()
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

# Step 4.5: Generate ground truth from scenario-1 if needed
if [[ ! -f "$GROUND_TRUTH" ]] && [[ -f "${RUN_DIR}/scenario-1-normal/votes.json" ]]; then
    log_info "Generating ground truth from scenario-1-normal..."
    cp "${RUN_DIR}/scenario-1-normal/votes.json" "$GROUND_TRUTH"
    log_success "Ground truth created: $GROUND_TRUTH"
fi

# Step 4.6: Run advanced scenarios if requested
if [[ " ${SCENARIOS[@]} " =~ " fiducials " ]] || 
   [[ " ${SCENARIOS[@]} " =~ " quality-gates " ]] || 
   [[ " ${SCENARIOS[@]} " =~ " distortion " ]] ||
   [[ " ${SCENARIOS[@]} " =~ " alignment " ]] ||
   [[ " ${SCENARIOS[@]} " =~ " rotations " ]]; then
    
    log_section "Advanced Scenarios"
    
    # Source advanced scenarios library
    source "${SCRIPT_DIR}/lib/advanced-scenarios.sh"
    
    # Get source files from scenario-1
    SOURCE_FILLED="${RUN_DIR}/scenario-1-normal/blank_filled.png"
    SOURCE_BLANK="${RUN_DIR}/scenario-1-normal/blank.png"
    COORDS_FILE="${RUN_DIR}/template/coordinates.json"
    
    # Generate each advanced scenario
    if [[ " ${SCENARIOS[@]} " =~ " fiducials " ]]; then
        generate_scenario_fiducials "$RUN_DIR" "$COORDS_FILE"
    fi
    
    if [[ " ${SCENARIOS[@]} " =~ " quality-gates " ]]; then
        generate_scenario_quality_gates "$RUN_DIR"
    fi
    
    if [[ " ${SCENARIOS[@]} " =~ " distortion " ]]; then
        generate_scenario_distortion "$RUN_DIR" "$COORDS_FILE" "$GROUND_TRUTH"
    fi
    
    if [[ " ${SCENARIOS[@]} " =~ " alignment " ]]; then
        generate_scenario_fiducial_alignment "$RUN_DIR" "$COORDS_FILE" "$GROUND_TRUTH"
    fi
    
    if [[ " ${SCENARIOS[@]} " =~ " rotations " ]]; then
        if [[ -f "$SOURCE_FILLED" ]] && [[ -f "$SOURCE_BLANK" ]]; then
            generate_scenario_cardinal_rotations "$RUN_DIR" "$SOURCE_FILLED" "$SOURCE_BLANK" "$COORDS_FILE" "$GROUND_TRUTH"
        else
            log_warning "Rotation tests skipped: source files missing"
        fi
    fi
    
    log_success "Advanced scenarios complete"
    echo ""
fi

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

# Generate scenario descriptions dynamically and insert into README using Python
python3 << PYREADME
import os
import glob

run_dir = '${RUN_DIR}'
scenarios_text = []

# Generate scenario descriptions
for scenario_dir in sorted(glob.glob(f"{run_dir}/scenario-*")):
    if os.path.isdir(scenario_dir):
        scenario_name = os.path.basename(scenario_dir)
        scenarios_text.append(f"""### {scenario_name}
**Directory:** \`{scenario_name}/\`

**Artifacts:**
- \`blank.png\` - Unfilled ballot template
- \`blank_filled.png\` - Filled ballot image
- \`results.json\` - Appreciation results
- \`overlay.png\` - Visual overlay
- \`metadata.json\` - Scenario metadata
""")

# Read README and replace placeholder
readme_path = f"{run_dir}/README.md"
with open(readme_path, 'r') as f:
    content = f.read()

# Replace placeholder with scenarios text
scenarios_str = '\n'.join(scenarios_text)
content = content.replace('SCENARIOS_PLACEHOLDER', scenarios_str)

# Write updated README
with open(readme_path, 'w') as f:
    f.write(content)

print("README scenarios inserted")
PYREADME

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
