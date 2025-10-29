#!/bin/bash
# Generate all OMR test fixtures
# This script creates all required fixtures for OMR appreciation testing

set -e

# Colors for output
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${BLUE}  OMR Test Fixture Generator${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""

FIXTURE_BASE="storage/app/tests/omr-appreciation/fixtures"
mkdir -p "${FIXTURE_BASE}"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 1. Generate blank ballot base (for skew/rotation testing)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
BLANK_BALLOT_BASE="packages/omr-appreciation/examples/test_ballot.png"

if [ ! -f "${BLANK_BALLOT_BASE}" ]; then
    echo -e "${RED}✗ Blank ballot base not found: ${BLANK_BALLOT_BASE}${NC}"
    exit 1
fi

echo -e "${GREEN}✓ Blank ballot base exists: ${BLANK_BALLOT_BASE}${NC}"

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 2. Generate skew-rotation fixtures (for scenario-5)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
SKEW_ROTATION_DIR="${FIXTURE_BASE}/skew-rotation"

if [ ! -d "${SKEW_ROTATION_DIR}" ] || [ -z "$(ls -A "${SKEW_ROTATION_DIR}" 2>/dev/null | grep -v README.md)" ]; then
    echo -e "${YELLOW}Generating skew-rotation fixtures...${NC}"
    
    if python3 scripts/synthesize_ballot_variants.py \
        --input "${BLANK_BALLOT_BASE}" \
        --output "${SKEW_ROTATION_DIR}" \
        --quiet; then
        echo -e "${GREEN}✓ Skew-rotation fixtures generated ($(ls -1 "${SKEW_ROTATION_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' ') files)${NC}"
    else
        echo -e "${RED}✗ Failed to generate skew-rotation fixtures${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Skew-rotation fixtures exist ($(ls -1 "${SKEW_ROTATION_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' ') files)${NC}"
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 3. Generate filled ballot base (from PHP test run)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILLED_BALLOT_BASE="${FIXTURE_BASE}/filled-ballot-base.png"

if [ ! -f "${FILLED_BALLOT_BASE}" ]; then
    echo -e "${YELLOW}Generating filled ballot base...${NC}"
    echo -e "${BLUE}Running PHP test to generate filled ballot...${NC}"
    
    # Run just the normal ballot test to generate filled ballot
    TEMP_RUN_ID="fixture-generation-$(date '+%Y%m%d_%H%M%S')"
    export OMR_TEST_RUN_ID="${TEMP_RUN_ID}"
    
    if php artisan test tests/Feature/OMRAppreciationTest.php --filter="appreciates simulated Philippine ballot correctly" --group=appreciation --quiet; then
        # Extract the filled ballot from the test run
        TEMP_RUN_DIR="storage/app/tests/omr-appreciation/runs/${TEMP_RUN_ID}"
        TEMP_FILLED="${TEMP_RUN_DIR}/scenario-1-normal/blank_filled.png"
        
        if [ -f "${TEMP_FILLED}" ]; then
            cp "${TEMP_FILLED}" "${FILLED_BALLOT_BASE}"
            echo -e "${GREEN}✓ Filled ballot base generated${NC}"
            
            # Clean up temporary run directory
            rm -rf "${TEMP_RUN_DIR}"
        else
            echo -e "${RED}✗ Failed to generate filled ballot (test did not produce expected output)${NC}"
            exit 1
        fi
    else
        echo -e "${RED}✗ Failed to run PHP test to generate filled ballot${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Filled ballot base exists${NC}"
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 4. Generate filled-distorted fixtures (for scenario-6)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILLED_DISTORTED_DIR="${FIXTURE_BASE}/filled-distorted"

if [ ! -d "${FILLED_DISTORTED_DIR}" ] || [ -z "$(ls -A "${FILLED_DISTORTED_DIR}" 2>/dev/null)" ]; then
    echo -e "${YELLOW}Generating filled-distorted fixtures...${NC}"
    
    if python3 scripts/synthesize_ballot_variants.py \
        --input "${FILLED_BALLOT_BASE}" \
        --output "${FILLED_DISTORTED_DIR}" \
        --quiet; then
        echo -e "${GREEN}✓ Filled-distorted fixtures generated ($(ls -1 "${FILLED_DISTORTED_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' ') files)${NC}"
    else
        echo -e "${RED}✗ Failed to generate filled-distorted fixtures${NC}"
        exit 1
    fi
else
    echo -e "${GREEN}✓ Filled-distorted fixtures exist ($(ls -1 "${FILLED_DISTORTED_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' ') files)${NC}"
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# 5. Generate ground truth JSON (for scenario-6 validation)
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
GROUND_TRUTH="${FIXTURE_BASE}/filled-ballot-ground-truth.json"

if [ ! -f "${GROUND_TRUTH}" ]; then
    echo -e "${YELLOW}Creating ground truth JSON...${NC}"
    
    cat > "${GROUND_TRUTH}" <<'GROUNDTRUTH'
{
  "test_id": "filled_ballot_validation",
  "description": "Expected bubble states for scenario-1 normal ballot (5 marks)",
  "source": "OMRAppreciationTest.php - test_appreciates_simulated_Philippine_ballot_correctly",
  "expected_marks": {
    "PRESIDENT": ["LD_001"],
    "VICE-PRESIDENT": ["VD_002"],
    "SENATOR": ["JD_001", "ES_002", "MF_003"]
  },
  "bubble_states": {
    "PRESIDENT_LD_001": true,
    "VICE-PRESIDENT_VD_002": true,
    "SENATOR_JD_001": true,
    "SENATOR_ES_002": true,
    "SENATOR_MF_003": true
  },
  "total_expected_marks": 5,
  "validation_criteria": {
    "min_accuracy": 0.98,
    "max_false_positive_rate": 0.01,
    "max_false_negative_rate": 0.02
  }
}
GROUNDTRUTH
    
    echo -e "${GREEN}✓ Ground truth JSON created${NC}"
else
    echo -e "${GREEN}✓ Ground truth JSON exists${NC}"
fi

# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
# Summary
# ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
echo ""
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo -e "${GREEN}✓ All fixtures generated successfully${NC}"
echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
echo ""
echo -e "${BLUE}Fixture Summary:${NC}"
echo -e "  • Skew-rotation:    ${GREEN}$(ls -1 "${SKEW_ROTATION_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' ')${NC} files"
echo -e "  • Filled-distorted: ${GREEN}$(ls -1 "${FILLED_DISTORTED_DIR}"/*.png 2>/dev/null | wc -l | tr -d ' ')${NC} files"
echo -e "  • Ground truth:     ${GREEN}✓${NC}"
echo ""
