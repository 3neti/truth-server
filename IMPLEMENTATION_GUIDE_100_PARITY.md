# Implementation Guide: Achieving 100% Parity with Deprecated Script

## Current Status: ~50% Parity

### Gold Standard (Reference)
```
storage/app/tests/omr-appreciation/runs/2025-11-01_131952/
├── config/
│   ├── election.json ✅
│   ├── precinct.yaml ✅
│   ├── mapping.yaml ✅
│   └── summary.txt ✅
├── template/
│   ├── ballot.pdf ❌ MISSING
│   ├── questionnaire.pdf ❌ MISSING
│   └── coordinates.json ✅
├── scenario-1-normal/
│   ├── blank.png ❌ MISSING
│   ├── blank_filled.png ❌ MISSING
│   ├── overlay.png ❌ MISSING
│   ├── results.json ❌ MISSING
│   └── metadata.json ❌ MISSING
├── scenario-2-overvote/ (same structure)
├── scenario-3-faint/ (same structure)
├── scenario-4-fiducials/ ✅ Created but incomplete
├── scenario-5-quality-gates/ ✅ Created but incomplete
├── scenario-6-distortion/ ✅ Created but incomplete
├── scenario-7-fiducial-alignment/ ✅ Created but incomplete
├── scenario-8-cardinal-rotations/ ✅ Created but incomplete
├── environment.json ✅
├── README.md ✅
├── test-results.json ✅
└── test-output.txt ❌ MISSING
```

## Critical Issues

### Issue 1: Wrong Output Directory
**Current:** `storage/app/tests/omr-appreciation/runs/`
**Should be:** `storage/app/private/simulation/runs/`

**Fix:**
```bash
# In scripts/simulation/run-test-suite.sh, change line ~17:
DEFAULT_TEST_ROOT="storage/app/private/simulation"
```

### Issue 2: Basic Scenarios Not Generated
**Problem:** Coordinate validation fails with default config
**Solution:** Always use simulation config or fix validation

**Fix:**
```bash
# In scripts/simulation/run-test-suite.sh, update default config:
DEFAULT_CONFIG_DIR="resources/docs/simulation/config"
```

### Issue 3: Missing PDF Artifacts
**Problem:** Template generation doesn't create ballot.pdf/questionnaire.pdf
**Solution:** Use Laravel helper instead of simulation script

**Implementation:**
```bash
# In run-test-suite.sh, replace Step 4 with:
log_section "Generating Template with Laravel"

TEMPLATE_DIR="${RUN_DIR}/template"
mkdir -p "$TEMPLATE_DIR"

# Use Laravel helper for template generation
if php scripts/laravel-simulation-helper.php generate-template \
    "$CONFIG_DIR" "$TEMPLATE_DIR" \
    > "${RUN_DIR}/template-generation.log" 2>&1; then
    log_success "Template generated with PDFs"
else
    log_error "Template generation failed"
    cat "${RUN_DIR}/template-generation.log"
    exit 1
fi
```

### Issue 4: Missing overlay.png
**Problem:** Overlay generation fails or is skipped
**Solution:** Generate overlay for each scenario after appreciation

**Implementation:**
```bash
# After each scenario's appreciation step, add:
if [[ -f "${scenario_dir}/results.json" ]]; then
    php scripts/laravel-simulation-helper.php generate-overlay \
        "${scenario_dir}/blank_filled.png" \
        "${scenario_dir}/results.json" \
        "${TEMPLATE_DIR}/coordinates.json" \
        "${scenario_dir}/overlay.png" \
        "$CONFIG_DIR" \
        >> "${scenario_dir}/overlay.log" 2>&1
fi
```

## Step-by-Step Implementation

### Step 1: Fix Laravel Helper Path Handling

**File:** `scripts/laravel-simulation-helper.php`

```php
// Line 72, update to use absolute path:
$configPath = realpath($configDir);
if (!$configPath || !is_dir($configPath)) {
    throw new Exception("Config directory not found: $configDir");
}

$questionnaire = $loader->load($configPath, null);
```

### Step 2: Update Output Directory

**File:** `scripts/simulation/run-test-suite.sh`

```bash
# Change line ~17:
DEFAULT_TEST_ROOT="storage/app/private/simulation"

# Also update line ~99:
RUN_DIR="${PROJECT_ROOT}/${DEFAULT_TEST_ROOT}/runs/${RUN_TIMESTAMP}"
```

### Step 3: Fix Default Config

**File:** `scripts/simulation/run-test-suite.sh`

```bash
# Change line ~16:
DEFAULT_CONFIG_DIR="resources/docs/simulation/config"
```

### Step 4: Replace Template Generation

**File:** `scripts/simulation/run-test-suite.sh`

Replace the entire "Step 4: Run simulation" section with Laravel helper:

```bash
# Step 4: Generate Template with Laravel Infrastructure
log_section "Generating Ballot Template"

TEMPLATE_DIR="${RUN_DIR}/template"
mkdir -p "$TEMPLATE_DIR"

log_info "Using Laravel test infrastructure for template generation..."

if php scripts/laravel-simulation-helper.php generate-template \
    "$CONFIG_DIR" "$TEMPLATE_DIR" \
    > "${RUN_DIR}/template-generation.log" 2>&1; then
    
    log_success "Template generated:"
    log_success "  - ballot.pdf"
    log_success "  - questionnaire.pdf"
    log_success "  - coordinates.json"
    log_success "  - ballot.png"
    
    COORDS_FILE="${TEMPLATE_DIR}/coordinates.json"
    export COORDINATES_FILE="$COORDS_FILE"
    export TEMPLATE_DIR="$TEMPLATE_DIR"
else
    log_error "Template generation failed"
    cat "${RUN_DIR}/template-generation.log"
    exit 1
fi
```

### Step 5: Generate Basic Scenarios with Laravel

Instead of using the simulation script for basic scenarios, generate them directly:

```bash
# Step 5: Generate Basic Scenarios with Laravel
log_section "Generating Basic Scenarios"

# Use the template/ballot.png as base
BLANK_BALLOT="${TEMPLATE_DIR}/ballot.png"

for scenario_num in 1 2 3; do
    case $scenario_num in
        1) scenario_name="normal" ;;
        2) scenario_name="overvote" ;;
        3) scenario_name="faint" ;;
    esac
    
    SCENARIO_DIR="${RUN_DIR}/scenario-${scenario_num}-${scenario_name}"
    mkdir -p "$SCENARIO_DIR"
    
    log_info "Creating scenario-${scenario_num}-${scenario_name}..."
    
    # Copy blank ballot
    cp "$BLANK_BALLOT" "${SCENARIO_DIR}/blank.png"
    
    # Generate votes.json using scenario generator
    python3 << PYGEN
import json, random
# Generate votes based on scenario type
votes = {}
# ... (use logic from scenario-generator.sh)
with open('${SCENARIO_DIR}/votes.json', 'w') as f:
    json.dump(votes, f, indent=2)
PYGEN
    
    # Fill bubbles using Laravel helper
    php scripts/laravel-simulation-helper.php fill-bubbles \
        "${SCENARIO_DIR}/blank.png" \
        "${SCENARIO_DIR}/votes.json" \
        "$COORDS_FILE" \
        "${SCENARIO_DIR}/blank_filled.png"
    
    # Run appreciation
    python3 packages/omr-appreciation/omr-python/appreciate.py \
        "${SCENARIO_DIR}/blank_filled.png" \
        "$COORDS_FILE" \
        --threshold 0.3 \
        --no-align \
        --config-path "$CONFIG_DIR" \
        > "${SCENARIO_DIR}/results.json" 2>"${SCENARIO_DIR}/stderr.log"
    
    # Generate overlay
    php scripts/laravel-simulation-helper.php generate-overlay \
        "${SCENARIO_DIR}/blank_filled.png" \
        "${SCENARIO_DIR}/results.json" \
        "$COORDS_FILE" \
        "${SCENARIO_DIR}/overlay.png" \
        "$CONFIG_DIR"
    
    # Generate metadata
    python3 << PYMETA
import json
from datetime import datetime

with open('${SCENARIO_DIR}/votes.json') as f:
    votes = json.load(f)
with open('${SCENARIO_DIR}/results.json') as f:
    results = json.load(f)

bubbles_filled = [k for k, v in votes.items() if v.get('filled')]
bubbles = results.get('bubbles', results.get('results', {}))
if isinstance(bubbles, dict):
    bubbles_detected = [k for k, v in bubbles.items() if v.get('filled')]
else:
    bubbles_detected = [b['id'] for b in bubbles if b.get('filled')]

metadata = {
    "scenario": "$scenario_name",
    "description": "Test scenario: $scenario_name",
    "bubbles_filled": sorted(bubbles_filled),
    "bubbles_detected": sorted(bubbles_detected),
    "timestamp": datetime.utcnow().isoformat() + '+00:00'
}

with open('${SCENARIO_DIR}/metadata.json', 'w') as f:
    json.dump(metadata, f, indent=4)
PYMETA
    
    log_success "scenario-${scenario_num}-${scenario_name} complete"
done
```

### Step 6: Update Advanced Scenarios

Add overlay generation to all advanced scenarios in `scripts/simulation/lib/advanced-scenarios.sh`:

```bash
# After each scenario's appreciation, add:
if [[ -f "${output_dir}/results.json" ]]; then
    php scripts/laravel-simulation-helper.php generate-overlay \
        "${output_dir}/blank_filled.png" \
        "${output_dir}/results.json" \
        "$coords_file" \
        "${output_dir}/overlay.png" \
        "${CONFIG_DIR:-}" \
        >> "${output_dir}/overlay.log" 2>&1
fi
```

## Testing Against Gold Standard

### Run Comparison Test

```bash
# Run deprecated script
bash scripts/test-omr-appreciation.sh

# Capture its output location
GOLD_RUN=$(readlink storage/app/tests/omr-appreciation/latest)

# Run new script
bash scripts/simulation/run-test-suite.sh \
    --config resources/docs/simulation/config \
    --scenarios normal,overvote,faint,fiducials,quality-gates,distortion,alignment,rotations \
    --fresh

# Capture its output
NEW_RUN=$(readlink storage/app/private/simulation/latest)

# Compare structures
diff -r "$GOLD_RUN" "$NEW_RUN" | grep -E "^Only in|^Files .* differ"
```

### Validation Checklist

For 100% parity, verify:

- [ ] Output directory is `storage/app/private/simulation/runs/`
- [ ] `template/ballot.pdf` exists and matches deprecated script
- [ ] `template/questionnaire.pdf` exists
- [ ] `template/coordinates.json` exists
- [ ] All 8 scenarios exist
- [ ] Each scenario has `blank.png`
- [ ] Each scenario has `blank_filled.png`
- [ ] Each scenario has `overlay.png` (with colored circles and labels)
- [ ] Each scenario has `results.json`
- [ ] Each scenario has `metadata.json` with proper format
- [ ] `config/summary.txt` exists
- [ ] `README.md` includes all scenarios
- [ ] `test-results.json` shows correct counts

## Estimated Effort

- Step 1 (Fix Laravel helper): 15 min
- Step 2-3 (Update paths/config): 10 min
- Step 4 (Replace template gen): 30 min
- Step 5 (Basic scenarios): 1 hour
- Step 6 (Advanced overlays): 30 min
- Testing & validation: 30 min

**Total: ~2.5-3 hours**

## Quick Reference Commands

```bash
# Test Laravel helper
php scripts/laravel-simulation-helper.php generate-template \
    resources/docs/simulation/config \
    /tmp/test-template

# Test overlay generation
php scripts/laravel-simulation-helper.php generate-overlay \
    ballot.png results.json coords.json overlay.png config

# Run full test suite
bash scripts/simulation/run-test-suite.sh \
    --config resources/docs/simulation/config \
    --scenarios normal,overvote,faint,rotations \
    --fresh

# Compare with gold standard
diff -qr storage/app/tests/omr-appreciation/runs/2025-11-01_131952 \
        storage/app/private/simulation/latest
```

## Success Criteria

✅ All artifacts match deprecated script structure
✅ All scenarios have complete files (no missing overlay.png, etc.)
✅ PDFs generated in template/
✅ Output in correct directory (storage/app/private/simulation)
✅ Side-by-side comparison shows identical structure
✅ Tests pass with same results as deprecated script

---

**Start Point for Next Session:**
1. Fix Laravel helper path handling
2. Update output directory and default config
3. Replace template generation with Laravel helper
4. Test against gold standard

Good luck! 🚀
