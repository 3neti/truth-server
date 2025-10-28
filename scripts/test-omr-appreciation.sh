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
cat > "${RUN_DIR}/environment.json" <<EOF
{
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "hostname": "$(hostname)",
  "user": "$(whoami)",
  "php_version": "$(php -r 'echo PHP_VERSION;')",
  "python_version": "$(python3 --version 2>&1 | cut -d' ' -f2)",
  "imagick_version": "$(php -r 'echo (new Imagick())->getVersion()[\"versionString\"];' 2>/dev/null || echo 'not available')",
  "opencv_version": "$(python3 -c 'import cv2; print(cv2.__version__)' 2>/dev/null || echo 'not available')"
}
EOF

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
    $([ -d "${RUN_DIR}/scenario-3-faint" ] && echo '{"id": "scenario-3-faint", "name": "Faint Marks", "status": "executed"}' || echo '')
  ]
}
EOF

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

## Template Files

- `template/ballot.pdf` - Source ballot PDF
- `template/coordinates.json` - Bubble coordinate mappings

## Environment

See `environment.json` for complete environment details.

**Key Dependencies:**
- PHP: $(php -r 'echo PHP_VERSION;')
- Python: $(python3 --version 2>&1 | cut -d' ' -f2)
- ImageMagick/Imagick: Available
- OpenCV: Available

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
