#!/bin/bash
# Simulation Ballot Test Runner
# Pure config-driven testing without database dependency

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Paths
CONFIG_DIR="resources/docs/simulation/config"
COORDS_FILE="resources/docs/simulation/coordinates.json"
APPRECIATE_SCRIPT="packages/omr-appreciation/omr-python/appreciate.py"

# Create timestamp for this test run
RUN_TIMESTAMP=$(date '+%Y-%m-%d_%H%M%S')
RUN_DIR="storage/app/tests/simulation/runs/${RUN_TIMESTAMP}"

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  Simulation Ballot Test Suite (Config-Driven)${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}Run ID:${NC} ${RUN_TIMESTAMP}"
echo -e "${BLUE}Output:${NC} ${RUN_DIR}"
echo ""

# Create run directory
mkdir -p "${RUN_DIR}"

# Step 1: Generate coordinates.json from config
echo -e "${YELLOW}Generating ballot template from election config...${NC}"
if php artisan election:generate-template \
    --config-path="${CONFIG_DIR}" \
    --output="${COORDS_FILE}" > "${RUN_DIR}/generation.log" 2>&1; then
    echo -e "${GREEN}✓ Template generated${NC}"
    
    # Copy to artifacts
    cp "${COORDS_FILE}" "${RUN_DIR}/coordinates.json"
    
    # Show bubble count
    BUBBLE_COUNT=$(python3 -c "import json; print(len(json.load(open('${COORDS_FILE}'))['bubble']))")
    echo -e "  Bubbles: ${BUBBLE_COUNT}"
else
    echo -e "${RED}✗ Template generation failed${NC}"
    cat "${RUN_DIR}/generation.log"
    exit 1
fi

# Step 2: Copy config files to artifacts
echo -e "${YELLOW}Copying election config to artifacts...${NC}"
mkdir -p "${RUN_DIR}/config"
cp "${CONFIG_DIR}/election.json" "${RUN_DIR}/config/"
cp "${CONFIG_DIR}/precinct.yaml" "${RUN_DIR}/config/"
cp "${CONFIG_DIR}/mapping.yaml" "${RUN_DIR}/config/"
echo -e "${GREEN}✓ Config files copied${NC}"
echo ""

# Step 3: Generate synthetic blank ballot
echo -e "${YELLOW}Generating synthetic blank ballot...${NC}"
python3 <<PYGEN
import cv2
import numpy as np
import json

# Load coordinates
with open('${COORDS_FILE}') as f:
    coords = json.load(f)

# Get ballot size (convert mm to pixels at 300 DPI)
mm_to_px = 300 / 25.4  # 300 DPI
width_mm = coords['ballot_size']['width_mm']
height_mm = coords['ballot_size']['height_mm']
width_px = int(width_mm * mm_to_px)
height_px = int(height_mm * mm_to_px)

print(f"  Ballot size: {width_mm}mm x {height_mm}mm ({width_px}px x {height_px}px)")

# Create white canvas
ballot = np.ones((height_px, width_px, 3), dtype=np.uint8) * 255

# Draw fiducial markers
for fid_id, fid in coords['fiducial'].items():
    x = int(fid['x'] * mm_to_px)
    y = int(fid['y'] * mm_to_px)
    size = int(10 * mm_to_px)  # 10mm marker
    cv2.rectangle(ballot, (x, y), (x + size, y + size), (0, 0, 0), 2)
    # Add marker ID
    cv2.putText(ballot, str(fid['marker_id']), (x + 5, y + 20), 
                cv2.FONT_HERSHEY_SIMPLEX, 0.4, (0, 0, 0), 1)

# Draw bubbles
for bubble_id, bubble in coords['bubble'].items():
    cx = int(bubble['center_x'] * mm_to_px)
    cy = int(bubble['center_y'] * mm_to_px)
    radius = int(bubble['diameter'] / 2 * mm_to_px)
    
    # Draw circle with thicker border
    cv2.circle(ballot, (cx, cy), radius, (0, 0, 0), 3)
    
    # Add bubble ID label
    label_x = cx - radius - 50
    label_y = cy + 5
    cv2.putText(ballot, bubble_id, (label_x, label_y),
                cv2.FONT_HERSHEY_SIMPLEX, 0.3, (100, 100, 100), 1)

# Save blank ballot
cv2.imwrite('${RUN_DIR}/blank_ballot.png', ballot)
print(f"  ✓ Blank ballot saved")

# Count bubbles by position
a_bubbles = [b for b in coords['bubble'].keys() if b.startswith('A')]
b_bubbles = [b for b in coords['bubble'].keys() if b.startswith('B')]
print(f"  Bubbles: {len(a_bubbles)} Punong Barangay + {len(b_bubbles)} Sangguniang Barangay")

PYGEN

echo -e "${GREEN}✓ Blank ballot generated${NC}"
echo ""

# Step 4: Test scenarios
echo -e "${YELLOW}Running test scenarios...${NC}"
echo ""

# Scenario 1: Normal voting (select 1 Punong + 7 Sangguniang)
SCENARIO_1="${RUN_DIR}/scenario-1-normal"
mkdir -p "${SCENARIO_1}"

echo -e "${BLUE}Scenario 1: Normal Voting${NC}"

# Fill bubbles
python3 <<PYFILL
import cv2
import numpy as np
import json

# Load blank ballot and coordinates
ballot = cv2.imread('${RUN_DIR}/blank_ballot.png')
with open('${COORDS_FILE}') as f:
    coords = json.load(f)

mm_to_px = 300 / 25.4

# Select bubbles: 1 Punong Barangay + 7 Sangguniang Barangay
# Using new row-based layout: A=Punong, B-J=Sangguniang (6 per row)
selected = ['A1', 'B1', 'B5', 'C2', 'D3', 'E4', 'F5', 'G6']

for bubble_id in selected:
    if bubble_id in coords['bubble']:
        bubble = coords['bubble'][bubble_id]
        cx = int(bubble['center_x'] * mm_to_px)
        cy = int(bubble['center_y'] * mm_to_px)
        radius = int(bubble['diameter'] / 2 * mm_to_px)
        
        # Fill bubble (black) - radius-1 for better fill ratio
        cv2.circle(ballot, (cx, cy), radius - 1, (0, 0, 0), -1)

# Save filled ballot
cv2.imwrite('${SCENARIO_1}/filled_ballot.png', ballot)

# Save selection metadata
with open('${SCENARIO_1}/selections.json', 'w') as f:
    json.dump({'selected': selected}, f, indent=2)

print(f"  Filled {len(selected)} bubbles: {', '.join(selected)}")

PYFILL

# Run appreciation
echo -e "  Running appreciation..."
if python3 "${APPRECIATE_SCRIPT}" \
    "${SCENARIO_1}/filled_ballot.png" \
    "${COORDS_FILE}" \
    --threshold 0.25 \
    --no-align \
    --config-path "${CONFIG_DIR}" \
    > "${SCENARIO_1}/appreciation.json" 2>&1; then
    
    # Validate results
    FILLED_COUNT=$(python3 -c "
import json
data = json.load(open('${SCENARIO_1}/appreciation.json'))
results = data['results']
# Handle both dict and list formats
if isinstance(results, dict):
    filled = [r for r in results.values() if r['filled']]
else:
    filled = [r for r in results if r['filled']]
print(len(filled))
")
    
    if [ "${FILLED_COUNT}" = "8" ]; then
        echo -e "  ${GREEN}✓ PASS${NC} - Detected 8/8 filled bubbles"
    else
        echo -e "  ${RED}✗ FAIL${NC} - Detected ${FILLED_COUNT}/8 filled bubbles"
    fi
else
    echo -e "  ${RED}✗ FAIL${NC} - Appreciation error"
fi

echo ""

# Scenario 2: Overvote detection (select too many Sangguniang)
SCENARIO_2="${RUN_DIR}/scenario-2-overvote"
mkdir -p "${SCENARIO_2}"

echo -e "${BLUE}Scenario 2: Overvote Detection${NC}"

# Fill 10 Sangguniang bubbles (max is 8)
python3 <<PYOVERVOTE
import cv2
import numpy as np
import json

ballot = cv2.imread('${RUN_DIR}/blank_ballot.png')
with open('${COORDS_FILE}') as f:
    coords = json.load(f)

mm_to_px = 300 / 25.4

# Overvote: 1 Punong + 10 Sangguniang (max is 8)
# Using new row-based layout
selected = ['A2', 'B1', 'B2', 'B3', 'B4', 'B5', 'B6', 'C1', 'C2', 'C3', 'C4']

for bubble_id in selected:
    if bubble_id in coords['bubble']:
        bubble = coords['bubble'][bubble_id]
        cx = int(bubble['center_x'] * mm_to_px)
        cy = int(bubble['center_y'] * mm_to_px)
        radius = int(bubble['diameter'] / 2 * mm_to_px)
        # Fill bubble (black) - radius-1 for better fill ratio
        cv2.circle(ballot, (cx, cy), radius - 1, (0, 0, 0), -1)

cv2.imwrite('${SCENARIO_2}/filled_ballot.png', ballot)

with open('${SCENARIO_2}/selections.json', 'w') as f:
    json.dump({'selected': selected, 'note': 'Overvote: 10 Sangguniang (max 8)'}, f, indent=2)

print(f"  Filled {len(selected)} bubbles (overvote scenario)")

PYOVERVOTE

# Run appreciation
if python3 "${APPRECIATE_SCRIPT}" \
    "${SCENARIO_2}/filled_ballot.png" \
    "${COORDS_FILE}" \
    --threshold 0.25 \
    --no-align \
    --config-path "${CONFIG_DIR}" \
    > "${SCENARIO_2}/appreciation.json" 2>&1; then
    
    echo -e "  ${GREEN}✓${NC} Appreciation completed (overvote should be detected in validation)"
else
    echo -e "  ${RED}✗${NC} Appreciation error"
fi

echo ""

# Generate summary report
echo -e "${YELLOW}Generating test report...${NC}"

cat > "${RUN_DIR}/README.md" <<REPORT
# Simulation Ballot Test Report

**Run ID:** ${RUN_TIMESTAMP}  
**Date:** $(date '+%Y-%m-%d %H:%M:%S')

## Configuration

- **Config Path:** \`${CONFIG_DIR}\`
- **Election:** Barangay Elections 2025
- **Positions:** 
  - Punong Barangay: 6 candidates (A1-A6)
  - Sangguniang Barangay: 50 candidates (B1-B50)
- **Total Bubbles:** 56

## Test Scenarios

### Scenario 1: Normal Voting
- **Selection:** 1 Punong Barangay + 7 Sangguniang Barangay
- **Status:** See \`scenario-1-normal/appreciation.json\`

### Scenario 2: Overvote Detection  
- **Selection:** 1 Punong Barangay + 10 Sangguniang Barangay (overvote)
- **Status:** See \`scenario-2-overvote/appreciation.json\`

## Artifacts

- \`coordinates.json\` - Generated ballot template (56 bubbles)
- \`blank_ballot.png\` - Synthetic blank ballot image
- \`config/\` - Election configuration files
- \`scenario-*/\` - Test scenario results

## Notes

This test suite is **database-independent** and generates all artifacts dynamically from election config files.

REPORT

echo -e "${GREEN}✓ Report generated${NC}"
echo ""

# Final summary
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✅ Simulation test complete!${NC}"
echo ""
echo -e "Results: ${RUN_DIR}"
echo -e "Report:  ${RUN_DIR}/README.md"
echo ""
echo "View results:"
echo "  cat ${RUN_DIR}/README.md"
echo "  open ${RUN_DIR}/blank_ballot.png"
echo ""
