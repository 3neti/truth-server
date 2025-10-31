#!/bin/bash
# Simulation Ballot Test Runner (Modular)
# Pure config-driven testing without database dependency

set -e

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="$SCRIPT_DIR/simulation/lib"
SCENARIOS_DIR="$SCRIPT_DIR/simulation/scenarios"

# Source common libraries
source "$LIB_DIR/common.sh"
source "$LIB_DIR/template-generator.sh"
source "$LIB_DIR/ballot-renderer.sh"

# Configuration
PROJECT_ROOT=$(get_project_root)
CONFIG_DIR="resources/docs/simulation/config"
COORDS_FILE="resources/docs/simulation/coordinates.json"
APPRECIATE_SCRIPT="packages/omr-appreciation/omr-python/appreciate.py"

# Create timestamp for this test run
RUN_TIMESTAMP=$(date '+%Y-%m-%d_%H%M%S')
RUN_DIR="$PROJECT_ROOT/storage/app/tests/simulation/runs/$RUN_TIMESTAMP"

# Main execution
main() {
    log_header "Simulation Ballot Test Suite (Modular)"
    echo ""
    log_info "Run ID: $RUN_TIMESTAMP"
    log_info "Output: $RUN_DIR"
    echo ""
    
    # Check prerequisites
    check_python_modules || exit 1
    check_command_exists php || exit 1
    
    # Create run directory
    mkdir -p "$RUN_DIR"
    
    # Step 1: Generate template from config
    if ! generate_template "$CONFIG_DIR" "$COORDS_FILE" "$RUN_DIR/generation.log"; then
        log_error "Template generation failed"
        exit 1
    fi
    
    # Copy coordinates to artifacts
    cp "$COORDS_FILE" "$RUN_DIR/coordinates.json"
    
    # Validate coordinates
    validate_coordinates "$COORDS_FILE" || exit 1
    
    echo ""
    
    # Step 2: Copy config files to artifacts
    log_info "Copying election config to artifacts..."
    mkdir -p "$RUN_DIR/config"
    cp "$CONFIG_DIR/election.json" "$RUN_DIR/config/"
    cp "$CONFIG_DIR/precinct.yaml" "$RUN_DIR/config/"
    cp "$CONFIG_DIR/mapping.yaml" "$RUN_DIR/config/"
    log_success "Config files copied"
    echo ""
    
    # Step 3: Render blank ballot
    log_info "Rendering blank ballot..."
    if ! render_blank_ballot "$COORDS_FILE" "$RUN_DIR/blank_ballot.png" "SIMULATION-001"; then
        log_error "Failed to render blank ballot"
        exit 1
    fi
    echo ""
    
    # Step 4: Run test scenarios
    log_header "Running Test Scenarios"
    echo ""
    
    # Run each scenario
    for scenario_script in "$SCENARIOS_DIR"/*.sh; do
        if [ -f "$scenario_script" ]; then
            bash "$scenario_script" \
                "$RUN_DIR" \
                "$COORDS_FILE" \
                "$CONFIG_DIR" \
                "$RUN_DIR/blank_ballot.png" \
                "$APPRECIATE_SCRIPT"
            echo ""
        fi
    done
    
    # Step 5: Generate summary report
    log_info "Generating test report..."
    generate_report
    log_success "Report generated"
    echo ""
    
    # Print summary
    print_summary
    
    # Display results location
    echo ""
    log_info "Results: $RUN_DIR"
    log_info "Report:  $RUN_DIR/README.md"
    echo ""
    echo "View results:"
    echo "  cat $RUN_DIR/README.md"
    echo "  open $RUN_DIR/blank_ballot.png"
    echo ""
}

# Generate summary report
generate_report() {
    cat > "$RUN_DIR/README.md" <<REPORT
# Simulation Ballot Test Report (Modular)

**Run ID:** $RUN_TIMESTAMP  
**Date:** $(date '+%Y-%m-%d %H:%M:%S')

## Configuration

- **Config Path:** \`$CONFIG_DIR\`
- **Election:** Barangay Elections 2025
- **Positions:**
  - Row A: Punong Barangay (6 candidates)
  - Rows B-J: Sangguniang Barangay (50 candidates, 6 per row)
- **Total Bubbles:** 56

## Test Results

- **Total Scenarios:** $SCENARIO_TOTAL
- **Passed:** $SCENARIO_PASSED
- **Failed:** $SCENARIO_FAILED

## Test Scenarios

$(for scenario_dir in "$RUN_DIR"/scenario-*; do
    if [ -d "$scenario_dir" ]; then
        scenario_name=$(basename "$scenario_dir")
        echo "### $scenario_name"
        if [ -f "$scenario_dir/selections.json" ]; then
            echo "- Selection data: \`$scenario_name/selections.json\`"
        fi
        if [ -f "$scenario_dir/appreciation.json" ]; then
            echo "- Appreciation results: \`$scenario_name/appreciation.json\`"
        fi
        echo ""
    fi
done)

## Artifacts

- \`coordinates.json\` - Generated ballot template (56 bubbles)
- \`blank_ballot.png\` - Synthetic blank ballot image
- \`config/\` - Election configuration files
- \`scenario-*/\` - Test scenario results

## Notes

This test suite is **database-independent** and generates all artifacts dynamically from election config files using a modular architecture.

REPORT
}

# Run main
main "$@"
