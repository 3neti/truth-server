# OMR Appreciation Test Script Refactoring Plan

## Overview

This document outlines the plan to refactor `scripts/test-omr-appreciation.sh` into a modular, config-independent test runner that reuses logic from `scripts/simulation/run-simulation.sh`.

## Goals

1. **Config-independent ballot generation**: Generate ballots from any config directory without database dependency
2. **Modular architecture**: Reuse simulation libraries instead of inline bash/Python code
3. **CLI flexibility**: Accept parameters for config path, output directory, scenarios, fiducial mode
4. **Consistent artifacts**: Generate same structure as `storage/app/tests/omr-appreciation/latest`
5. **Timestamped runs**: Create runs in `storage/app/tests/omr-appreciation/runs/YYYY-MM-DD_HHMMSS/`
6. **Symlink management**: Maintain `latest` symlink to most recent run

## Current Architecture

### Existing Structure

```
scripts/
├── test-omr-appreciation.sh         # Monolithic (1184 lines)
├── generate-omr-fixtures.sh         # Fixture generator
├── compare_appreciation_results.py  # Result validation
├── generate-overlay.php             # Visual overlay generator
└── simulation/
    ├── run-simulation.sh             # Modular simulation runner
    └── lib/                          # Reusable libraries
        ├── common.sh                 # Logging, validation, test tracking
        ├── template-generator.sh     # Generate coordinates from config
        ├── scenario-generator.sh     # Generate test scenarios (votes.json)
        ├── ballot-renderer.sh        # Render ballots from votes
        ├── aruco-generator.sh        # Fiducial marker generation
        ├── ballot-appreciator.sh     # Run OMR appreciation
        └── overlay-generator.sh      # Generate visual overlays
```

### Current Issues

1. **Monolithic design**: 1184 lines with embedded Python heredocs
2. **Hardcoded paths**: Config paths hardcoded, not parameterized
3. **No CLI arguments**: No flexibility for different config dirs or scenarios
4. **Duplicate logic**: Reimplements what simulation libraries already provide
5. **Maintenance burden**: Changes must be duplicated across scripts

## Target Architecture

### New Modular Structure

```
scripts/
├── test-omr-appreciation.sh         # Refactored modular runner
├── generate-omr-fixtures.sh         # Keep as-is
├── compare_appreciation_results.py  # Keep as-is
├── generate-overlay.php             # Keep as-is
└── simulation/
    ├── run-simulation.sh             # Keep as-is
    └── lib/                          # SHARED libraries
        ├── common.sh                 # Logging, colors, test tracking
        ├── template-generator.sh     # Generate coordinates from config
        ├── scenario-generator.sh     # Generate test scenarios
        ├── ballot-renderer.sh        # Render ballots
        ├── aruco-generator.sh        # Fiducial markers
        ├── ballot-appreciator.sh     # OMR appreciation
        ├── overlay-generator.sh      # Visual overlays
        └── rotation-tester.sh        # NEW: Rotation testing logic
```

### Design Principles

1. **Single Responsibility**: Each library handles one aspect
2. **Config Agnostic**: Works with any election config directory
3. **Database Independent**: No database queries, config files only
4. **Reusable Functions**: Import simulation libraries via `source`
5. **Consistent Output**: Same artifact structure across runs

## Implementation Phases

### Phase 1: Extract Reusable Logic ✅ DONE

**Status**: Complete (simulation libraries exist)

- [x] `common.sh`: Logging, validation, test result tracking
- [x] `scenario-generator.sh`: Generate votes.json from scenarios
- [x] `template-generator.sh`: Generate coordinates from election config
- [x] `ballot-renderer.sh`: Render ballot images
- [x] `aruco-generator.sh`: Generate fiducial markers

### Phase 2: Add CLI Argument Parsing ← **CURRENT PHASE**

**Objective**: Make script configurable via command-line arguments

**New Arguments**:
```bash
scripts/test-omr-appreciation.sh [OPTIONS]

OPTIONS:
  -c, --config-dir DIR        Election config directory 
                              (default: config or resources/docs/simulation/config)
  -o, --output-dir DIR        Output directory for test runs
                              (default: storage/app/tests/omr-appreciation)
  -s, --scenarios LIST        Comma-separated scenario list
                              (default: all 8 scenarios)
  -f, --fiducial-mode MODE    Fiducial detection mode
                              (black_square|aruco|apriltag, default: black_square)
  --fresh                     Remove existing runs before starting
  --list-scenarios            List available test scenarios and exit
  -v, --verbose               Enable verbose debug logging
  -h, --help                  Show help message
```

**Tasks**:
- [ ] Add argument parsing loop (similar to run-simulation.sh)
- [ ] Set default values for all parameters
- [ ] Add validation for config directory existence
- [ ] Add `usage()` help function
- [ ] Add `--list-scenarios` handler

### Phase 3: Source Simulation Libraries

**Objective**: Replace inline code with library function calls

**Tasks**:
- [ ] Add library sourcing at script start:
  ```bash
  SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
  LIB_DIR="${SCRIPT_DIR}/simulation/lib"
  
  source "${LIB_DIR}/common.sh"
  source "${LIB_DIR}/template-generator.sh"
  source "${LIB_DIR}/scenario-generator.sh"
  source "${LIB_DIR}/ballot-renderer.sh"
  source "${LIB_DIR}/aruco-generator.sh"
  source "${LIB_DIR}/ballot-appreciator.sh"
  source "${LIB_DIR}/overlay-generator.sh"
  ```
- [ ] Replace inline logging with `log_info`, `log_success`, `log_error`
- [ ] Replace test tracking with `record_success`, `record_failure`

### Phase 4: Extract Rotation Testing Logic

**Objective**: Move rotation test functions to shared library

**New Library**: `scripts/simulation/lib/rotation-tester.sh`

**Functions to Extract**:
- `rotate_with_canvas()`: Canvas-based rotation (Python)
- `run_rotation_test()`: Single rotation test executor
- `run_cardinal_rotation_suite()`: Test all 8 rotations (0°/45°/90°/135°/180°/225°/270°/315°)

**Tasks**:
- [ ] Create `rotation-tester.sh` library
- [ ] Extract Python rotation code to separate script
- [ ] Move `run_rotation_test()` function
- [ ] Move cardinal rotation testing logic
- [ ] Export functions for reuse

### Phase 5: Implement Config-Independent Ballot Generation

**Objective**: Generate ballots from config files without database

**Workflow**:
```
Config Files → Template Generation → Scenario Generation → Ballot Rendering → Appreciation
```

**Tasks**:
- [ ] Use `generate_template()` from template-generator.sh:
  ```bash
  generate_template "$CONFIG_DIR" "$coordinates_file" "${template_dir}/generate.log"
  ```
- [ ] Use `generate_scenario_votes()` for each scenario:
  ```bash
  generate_scenario_votes "normal" "$coordinates_file" "${scenario_dir}/votes.json"
  ```
- [ ] Use `render_ballot()` to create ballot images:
  ```bash
  render_ballot "${scenario_dir}/votes.json" "$coordinates_file" "${scenario_dir}/ballot.png"
  ```
- [ ] Remove hardcoded fixture dependencies
- [ ] Validate config directory structure

### Phase 6: Refactor Scenario Execution

**Objective**: Replace monolithic scenario code with modular function calls

**Scenarios to Refactor**:

1. **Scenario 1 (Normal)**:
   ```bash
   create_scenario "scenario-1-normal" "normal" "${RUN_DIR}/scenario-1-normal" "$COORDS_FILE"
   render_ballot "${scenario_dir}/votes.json" "$COORDS_FILE" "${scenario_dir}/ballot.png"
   appreciate_ballot "${scenario_dir}/ballot.png" "$COORDS_FILE" "${scenario_dir}/results.json"
   generate_overlay "${scenario_dir}/ballot.png" "${scenario_dir}/results.json" "${scenario_dir}/overlay.png"
   ```

2. **Scenario 2 (Overvote)**:
   ```bash
   create_scenario "scenario-2-overvote" "overvote" "${RUN_DIR}/scenario-2-overvote" "$COORDS_FILE"
   ```

3. **Scenario 3 (Faint)**:
   ```bash
   create_scenario "scenario-3-faint" "faint" "${RUN_DIR}/scenario-3-faint" "$COORDS_FILE"
   ```

4. **Scenario 4 (Fiducials)**: Keep custom logic for fiducial testing

5. **Scenario 5 (Quality Gates)**: Extract to library function

6. **Scenario 6 (Distortion)**: Use rotation-tester.sh functions

7. **Scenario 7 (Fiducial Alignment)**: Use rotation-tester.sh functions

8. **Scenario 8 (Cardinal Rotations)**: Use `run_cardinal_rotation_suite()`

**Tasks**:
- [ ] Replace scenario 1-3 with `create_scenario()` calls
- [ ] Keep scenario 4 custom (fiducial-specific)
- [ ] Extract scenario 5 quality gates to library
- [ ] Refactor scenario 6-7 using rotation-tester.sh
- [ ] Refactor scenario 8 using `run_cardinal_rotation_suite()`

### Phase 7: Standardize Artifact Generation

**Objective**: Ensure consistent artifact structure across all runs

**Target Structure**:
```
storage/app/tests/omr-appreciation/
├── runs/
│   ├── 2025-11-01_075838/
│   │   ├── config/                    # Copied election configs
│   │   │   ├── election.json
│   │   │   ├── precinct.yaml
│   │   │   ├── mapping.yaml
│   │   │   └── summary.txt            # Human-readable summary
│   │   ├── environment.json           # Environment metadata
│   │   ├── template/                  # Generated template
│   │   │   ├── ballot.pdf
│   │   │   └── coordinates.json
│   │   ├── scenario-1-normal/
│   │   │   ├── votes.json            # Ground truth votes
│   │   │   ├── ballot.png            # Rendered ballot
│   │   │   ├── results.json          # Appreciation results
│   │   │   ├── overlay.png           # Visual overlay
│   │   │   ├── metadata.json         # Scenario metadata
│   │   │   └── validation.json       # Comparison with ground truth
│   │   ├── scenario-2-overvote/
│   │   ├── scenario-3-faint/
│   │   ├── scenario-4-fiducials/
│   │   ├── scenario-5-quality-gates/
│   │   ├── scenario-6-distortion/
│   │   ├── scenario-7-fiducial-alignment/
│   │   ├── scenario-8-cardinal-rotations/
│   │   │   ├── metadata.json
│   │   │   ├── summary.json
│   │   │   ├── rot_000/
│   │   │   ├── rot_045/
│   │   │   ├── rot_090/
│   │   │   ├── rot_135/
│   │   │   ├── rot_180/
│   │   │   ├── rot_225/
│   │   │   ├── rot_270/
│   │   │   └── rot_315/
│   │   ├── phase4-unit/              # Unit test results
│   │   ├── test-output.txt           # Raw test output
│   │   ├── test-results.json         # Structured results
│   │   └── README.md                 # Run documentation
│   └── 2025-11-01_080512/
└── latest -> runs/2025-11-01_080512   # Symlink to latest run
```

**Tasks**:
- [ ] Create timestamped run directory: `runs/$(date '+%Y-%m-%d_%H%M%S')`
- [ ] Copy config files to `${RUN_DIR}/config/`
- [ ] Generate config summary: `${RUN_DIR}/config/summary.txt`
- [ ] Generate environment metadata: `${RUN_DIR}/environment.json`
- [ ] Generate README.md with run documentation
- [ ] Create/update `latest` symlink
- [ ] Generate test-results.json summary

### Phase 8: Add Result Validation

**Objective**: Compare appreciation results with ground truth

**Tasks**:
- [ ] Use `scripts/compare_appreciation_results.py` for validation
- [ ] Generate validation.json per scenario
- [ ] Calculate accuracy metrics
- [ ] Report pass/fail status
- [ ] Aggregate results in test-results.json

### Phase 9: Testing & Validation

**Objective**: Verify refactored script produces same artifacts

**Test Cases**:
1. **Default run** (no args):
   ```bash
   scripts/test-omr-appreciation.sh
   ```
   - Should use default config
   - Should run all 8 scenarios
   - Should create timestamped run + symlink

2. **Custom config directory**:
   ```bash
   scripts/test-omr-appreciation.sh --config-dir resources/docs/simulation/config
   ```
   - Should generate Barangay ballot
   - Should work without database

3. **Specific scenarios**:
   ```bash
   scripts/test-omr-appreciation.sh --scenarios normal,overvote,faint
   ```
   - Should only run 3 scenarios
   - Should skip others

4. **Fresh run**:
   ```bash
   scripts/test-omr-appreciation.sh --fresh
   ```
   - Should remove existing runs
   - Should start clean

**Validation Criteria**:
- [ ] All 8 scenarios execute successfully
- [ ] Artifact structure matches expected format
- [ ] Symlink created/updated correctly
- [ ] Config summary generated correctly
- [ ] README.md contains accurate documentation
- [ ] Results validate against ground truth
- [ ] Works with both Philippine and Barangay configs

### Phase 10: Documentation & Cleanup

**Objective**: Update documentation and remove dead code

**Tasks**:
- [ ] Update `README.md` in project root
- [ ] Update WARP.md with new commands
- [ ] Add inline comments to refactored code
- [ ] Remove unused inline Python heredocs
- [ ] Remove hardcoded paths
- [ ] Add examples to help message
- [ ] Document library function APIs

## Benefits of Refactoring

### Before (Monolithic)
- 1184 lines in single file
- Embedded Python code
- Hardcoded paths
- No CLI flexibility
- Difficult to test individual scenarios
- Duplicate logic across scripts

### After (Modular)
- ~300-400 lines in main script
- Reusable library functions
- Config-agnostic design
- Full CLI control
- Easy to add new scenarios
- Shared logic with simulation runner
- Better maintainability
- Database-independent

## Success Metrics

1. **Lines of Code**: Reduce main script from 1184 → ~400 lines
2. **Config Flexibility**: Works with any config directory
3. **Artifact Consistency**: 100% match with current structure
4. **Test Coverage**: All 8 scenarios execute successfully
5. **Maintainability**: No duplicate code between scripts
6. **Documentation**: Complete help and examples

## Risk Mitigation

### Potential Issues

1. **Breaking existing workflows**: Current script may be used in automation
   - **Mitigation**: Keep old script as `test-omr-appreciation.sh.old` temporarily
   
2. **Library function compatibility**: Simulation libraries may need adjustments
   - **Mitigation**: Test each library function independently
   
3. **Python script extraction**: Inline Python may be complex to extract
   - **Mitigation**: Create separate `.py` files in `packages/omr-appreciation/omr-python/`

4. **Config structure differences**: Philippine vs Barangay configs differ
   - **Mitigation**: Add config validation and adapter functions

## Timeline

- **Phase 1**: ✅ Complete (libraries exist)
- **Phase 2**: 30 minutes (CLI args)
- **Phase 3**: 15 minutes (source libraries)
- **Phase 4**: 45 minutes (rotation library)
- **Phase 5**: 1 hour (config-independent generation)
- **Phase 6**: 2 hours (refactor scenarios)
- **Phase 7**: 45 minutes (artifact generation)
- **Phase 8**: 30 minutes (validation)
- **Phase 9**: 1 hour (testing)
- **Phase 10**: 30 minutes (documentation)

**Total Estimated Time**: ~7 hours

## Current Status

**Phase**: 2 (Add CLI Argument Parsing)  
**Last Updated**: 2025-11-01  
**Status**: Beginning implementation

## References

- `scripts/simulation/run-simulation.sh`: Reference implementation
- `scripts/simulation/lib/`: Shared library functions
- `storage/app/tests/omr-appreciation/latest/`: Target artifact structure
- `scripts/test-omr-appreciation.sh`: Current monolithic implementation
