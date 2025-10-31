# Plan: Integrate Config Files into Test Artifacts

## Overview

Update `scripts/test-omr-appreciation.sh` to:
1. Detect and use election config files (election.json, precinct.yaml, mapping.yaml)
2. Pass `--config-path` to Python scripts when configs are available
3. Copy config files into test run artifacts for documentation
4. Generate a config summary in the artifacts

## Current State

**Test artifacts location:** `storage/app/tests/omr-appreciation/runs/${TIMESTAMP}/`

**Current artifacts include:**
- `environment.json` - Environment info (PHP, Python, OpenCV versions)
- `test-output.txt` - Test output from PHPUnit
- `test-results.json` - Test summary
- `template/coordinates.json` - Copy of the template being tested
- Various scenario directories with test results

**Currently missing:**
- Election configuration context
- Mapping of bubble IDs to candidates
- Precinct information

## Proposed Changes

### Change 1: Config Detection

Add early in the script (around line 100):

```bash
# Detect election configuration
CONFIG_PATH=""
ELECTION_CONFIG=""
PRECINCT_CONFIG=""
MAPPING_CONFIG=""

# Try simulation config first
if [ -f "resources/docs/simulation/config/election.json" ]; then
    CONFIG_PATH="resources/docs/simulation/config"
    ELECTION_CONFIG="${CONFIG_PATH}/election.json"
    PRECINCT_CONFIG="${CONFIG_PATH}/precinct.yaml"
    MAPPING_CONFIG="${CONFIG_PATH}/mapping.yaml"
    echo -e "${BLUE}Election Config:${NC} simulation (Barangay)"
# Fall back to default config
elif [ -f "config/election.json" ]; then
    CONFIG_PATH="config"
    ELECTION_CONFIG="${CONFIG_PATH}/election.json"
    PRECINCT_CONFIG="${CONFIG_PATH}/precinct.yaml"
    MAPPING_CONFIG="${CONFIG_PATH}/mapping.yaml"
    echo -e "${BLUE}Election Config:${NC} default (Philippine)"
else
    echo -e "${YELLOW}⚠ No election config found${NC}"
fi
```

### Change 2: Copy Config Files to Artifacts

Add after creating `${RUN_DIR}` (around line 26):

```bash
# Copy election configs to artifacts (if available)
if [ -n "${CONFIG_PATH}" ]; then
    CONFIG_ARTIFACT_DIR="${RUN_DIR}/config"
    mkdir -p "${CONFIG_ARTIFACT_DIR}"
    
    echo -e "${YELLOW}Copying election configuration to artifacts...${NC}"
    
    if [ -f "${ELECTION_CONFIG}" ]; then
        cp "${ELECTION_CONFIG}" "${CONFIG_ARTIFACT_DIR}/election.json"
        echo -e "  ${GREEN}✓${NC} election.json"
    fi
    
    if [ -f "${PRECINCT_CONFIG}" ]; then
        cp "${PRECINCT_CONFIG}" "${CONFIG_ARTIFACT_DIR}/precinct.yaml"
        echo -e "  ${GREEN}✓${NC} precinct.yaml"
    fi
    
    if [ -f "${MAPPING_CONFIG}" ]; then
        cp "${MAPPING_CONFIG}" "${CONFIG_ARTIFACT_DIR}/mapping.yaml"
        echo -e "  ${GREEN}✓${NC} mapping.yaml"
    fi
    
    # Generate config summary
    generate_config_summary "${CONFIG_ARTIFACT_DIR}"
    
    echo ""
fi
```

### Change 3: Generate Config Summary

Add helper function (around line 90):

```bash
# Generate human-readable config summary
generate_config_summary() {
    local config_dir=$1
    local summary_file="${config_dir}/summary.txt"
    
    {
        echo "ELECTION CONFIGURATION SUMMARY"
        echo "=============================="
        echo ""
        echo "Generated: $(date '+%Y-%m-%d %H:%M:%S')"
        echo ""
        
        # Parse election.json if available
        if [ -f "${config_dir}/election.json" ]; then
            echo "POSITIONS:"
            echo "----------"
            python3 -c "
import json
with open('${config_dir}/election.json') as f:
    data = json.load(f)
    for pos in data.get('positions', []):
        print(f\"  • {pos['name']} ({pos['code']})\")
        print(f\"    Max selections: {pos.get('count', 1)}\")
"
            echo ""
            
            echo "CANDIDATES:"
            echo "-----------"
            python3 -c "
import json
with open('${config_dir}/election.json') as f:
    data = json.load(f)
    for pos_code, candidates in data.get('candidates', {}).items():
        print(f\"  {pos_code}: {len(candidates)} candidates\")
"
            echo ""
        fi
        
        # Parse precinct.yaml if available
        if [ -f "${config_dir}/precinct.yaml" ]; then
            echo "PRECINCT:"
            echo "---------"
            python3 -c "
import yaml
with open('${config_dir}/precinct.yaml') as f:
    data = yaml.safe_load(f)
    print(f\"  Code: {data.get('code', 'N/A')}\")
    print(f\"  Name: {data.get('name', 'N/A')}\")
    print(f\"  Location: {data.get('location_name', 'N/A')}\")
"
            echo ""
        fi
        
        # Parse mapping.yaml if available
        if [ -f "${config_dir}/mapping.yaml" ]; then
            echo "BALLOT MAPPING:"
            echo "---------------"
            python3 -c "
import yaml
with open('${config_dir}/mapping.yaml') as f:
    data = yaml.safe_load(f)
    marks = data.get('marks', [])
    print(f\"  Total marks: {len(marks)}\")
    if marks:
        print(f\"  First mark: {marks[0]['key']} → {marks[0]['value']}\")
        print(f\"  Last mark: {marks[-1]['key']} → {marks[-1]['value']}\")
"
            echo ""
        fi
        
        echo "FILES:"
        echo "------"
        ls -lh "${config_dir}" | tail -n +2
        
    } > "${summary_file}"
    
    echo -e "  ${GREEN}✓${NC} summary.txt"
}
```

### Change 4: Pass --config-path to appreciate.py

Update all `appreciate.py` calls to include `--config-path`:

**Location 1: Line 251 (scenario-6-distortion)**

```bash
# OLD:
if python3 "${APPRECIATE_SCRIPT}" \
    "${fixture}" \
    "${COORDS_FILE}" \
    --threshold 0.3 \
    --no-align \
    > "${APPRECIATION_OUTPUT}" 2>&1; then

# NEW:
APPRECIATE_ARGS=(
    "${fixture}"
    "${COORDS_FILE}"
    --threshold 0.3
    --no-align
)

# Add config path if available
if [ -n "${CONFIG_PATH}" ]; then
    APPRECIATE_ARGS+=(--config-path "${CONFIG_PATH}")
fi

if python3 "${APPRECIATE_SCRIPT}" "${APPRECIATE_ARGS[@]}" \
    > "${APPRECIATION_OUTPUT}" 2>&1; then
```

**Location 2: Line 347 (scenario-7-fiducial-alignment)**

```bash
# Same pattern as above
APPRECIATE_ARGS=(
    "${fixture}"
    "${COORDS_FILE}"
    --threshold 0.3
)

if [ -n "${CONFIG_PATH}" ]; then
    APPRECIATE_ARGS+=(--config-path "${CONFIG_PATH}")
fi

if python3 "${APPRECIATE_SCRIPT}" "${APPRECIATE_ARGS[@]}" \
    > "${APPRECIATION_OUTPUT}" 2>&1; then
```

**Location 3: Line 472 (rotation tests)**

```bash
# OLD:
if ! OMR_FIDUCIAL_MODE=aruco python3 packages/omr-appreciation/omr-python/appreciate.py \
    "${output_dir}/blank_filled.png" \
    "${coords_file}" \
    --threshold 0.3 \
    > "${output_dir}/results.json" 2>"${output_dir}/stderr.log"; then

# NEW:
APPRECIATE_ARGS=(
    "${output_dir}/blank_filled.png"
    "${coords_file}"
    --threshold 0.3
)

if [ -n "${CONFIG_PATH}" ]; then
    APPRECIATE_ARGS+=(--config-path "${CONFIG_PATH}")
fi

if ! OMR_FIDUCIAL_MODE=aruco python3 packages/omr-appreciation/omr-python/appreciate.py \
    "${APPRECIATE_ARGS[@]}" \
    > "${output_dir}/results.json" 2>"${output_dir}/stderr.log"; then
```

### Change 5: Update environment.json

Add config information to environment.json (around line 54):

```bash
cat > "${RUN_DIR}/environment.json" <<EOF
{
  "timestamp": "$(date -u '+%Y-%m-%dT%H:%M:%SZ')",
  "hostname": "$(hostname)",
  "user": "$(whoami)",
  "php_version": "$(php -r 'echo PHP_VERSION;')",
  "python_version": "$(python3 --version 2>&1 | cut -d' ' -f2)",
  "imagick_version": "$(php -r 'extension_loaded(\"imagick\") ? print(\"available\") : print(\"not available\");' 2>/dev/null || echo 'not available')",
  "opencv_version": "$(python3 -c 'import cv2; print(cv2.__version__)' 2>/dev/null || echo 'not available')",
  "fiducial_support": {
    "black_square": true,
    "aruco": ${ARUCO_AVAILABLE},
    "apriltag": ${APRILTAG_AVAILABLE}
  },
  "omr_fiducial_mode": "${OMR_FIDUCIAL_MODE:-black_square}",
  "election_config": {
    "available": $([ -n "${CONFIG_PATH}" ] && echo "true" || echo "false"),
    "path": "${CONFIG_PATH:-null}",
    "type": "$([ "${CONFIG_PATH}" = "resources/docs/simulation/config" ] && echo "simulation" || echo "default")"
  }
}
EOF
```

## Expected Artifact Structure

After implementation, test run artifacts will look like:

```
storage/app/tests/omr-appreciation/runs/2025-10-31_112800/
├── config/                          # NEW
│   ├── election.json                # Election configuration
│   ├── precinct.yaml                # Precinct details  
│   ├── mapping.yaml                 # Bubble ID mappings
│   └── summary.txt                  # Human-readable summary
├── environment.json                 # Updated with config info
├── test-output.txt
├── test-results.json
├── template/
│   └── coordinates.json
├── scenario-6-distortion/
│   ├── metadata.json
│   ├── *_appreciation.json          # Now with resolved candidate names
│   └── ...
└── scenario-7-fiducial-alignment/
    └── ...
```

## Benefits

### 1. Self-Documenting Artifacts
Every test run contains complete election context:
- Which positions were tested
- Which candidates were available
- How bubble IDs map to candidates

### 2. Reproducibility
Future developers can:
- See exact election config used in test
- Reproduce test with same config
- Compare configs across test runs

### 3. Debugging
When tests fail:
- Check if config was used correctly
- Verify bubble ID mappings
- Cross-reference results with election data

### 4. Audit Trail
- Complete record of election setup
- Version control for election configs
- Historical comparison of configs

### 5. Better Test Output
With `--config-path`, appreciate.py will output:
```json
{
  "results": {
    "A1": {
      "filled": true,
      "contest": "PUNONG_BARANGAY-1402702011",
      "code": "LD_001"
    }
  }
}
```

Instead of:
```json
{
  "results": {
    "A1": {
      "filled": true,
      "contest": "",
      "code": "A1"
    }
  }
}
```

## Testing the Changes

After implementation, test with:

```bash
# Run with simulation config
bash scripts/test-omr-appreciation.sh

# Verify artifacts
ls -la storage/app/tests/omr-appreciation/runs/*/config/

# Check summary
cat storage/app/tests/omr-appreciation/runs/*/config/summary.txt

# Verify config was used in appreciate.py
grep "contest" storage/app/tests/omr-appreciation/runs/*/scenario-*/results.json
```

## Implementation Order

1. ✅ Add config detection (Change 1)
2. ✅ Add generate_config_summary function (Change 3)
3. ✅ Copy config files to artifacts (Change 2)
4. ✅ Update environment.json (Change 5)
5. ✅ Update appreciate.py calls (Change 4)
6. ✅ Test with simulation config
7. ✅ Test with default config (backward compatibility)
8. ✅ Verify artifacts structure

## Backward Compatibility

- ✅ Script works if no config files found
- ✅ Script works without `--config-path` (falls back to parsing)
- ✅ Existing test expectations unchanged
- ✅ Only adds new artifacts, doesn't remove old ones

## Risk Assessment

**Risk Level: LOW** ✅

- All changes are additive
- Backward compatible with existing setup
- Config files are copied, not moved
- Script still works without configs
- Fallback to old behavior if config missing

---

## Ready to Implement?

This plan:
- ✅ Copies config files into artifacts
- ✅ Generates human-readable summary
- ✅ Uses `--config-path` when available
- ✅ Maintains backward compatibility
- ✅ Self-documenting test runs
- ✅ Better debugging capability
