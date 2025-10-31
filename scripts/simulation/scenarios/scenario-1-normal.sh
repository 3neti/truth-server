#!/bin/bash
# Scenario 1: Normal Voting
# Tests: 1 Punong Barangay + 7 Sangguniang Barangay (valid vote)

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="$SCRIPT_DIR/../lib"

# Source libraries
source "$LIB_DIR/common.sh"
source "$LIB_DIR/ballot-renderer.sh"

# Main scenario function
# Args: run_dir, coords_file, config_dir, blank_ballot
run_scenario() {
    local run_dir=$1
    local coords_file=$2
    local config_dir=$3
    local blank_ballot=$4
    local appreciate_script="${5:-packages/omr-appreciation/omr-python/appreciate.py}"
    
    local scenario_dir="$run_dir/scenario-1-normal"
    mkdir -p "$scenario_dir"
    
    log_scenario "Scenario 1: Normal Voting"
    
    # Select bubbles: 1 Punong + 7 Sangguniang (valid)
    local selected="A1,B1,B5,C2,D3,E4,F5,G6"
    
    # Fill bubbles
    if ! fill_bubbles "$blank_ballot" "$selected" "$coords_file" "$scenario_dir/filled_ballot.png"; then
        log_error "Failed to fill bubbles"
        increment_failed
        return 1
    fi
    
    # Save selection metadata
    echo "{\"selected\": [\"A1\", \"B1\", \"B5\", \"C2\", \"D3\", \"E4\", \"F5\", \"G6\"], \"expected\": 8}" \
        > "$scenario_dir/selections.json"
    
    # Run appreciation
    echo -n "  Running appreciation..."
    if python3 "$appreciate_script" \
        "$scenario_dir/filled_ballot.png" \
        "$coords_file" \
        --threshold 0.25 \
        --no-align \
        --config-path "$config_dir" \
        > "$scenario_dir/appreciation.json" 2>&1; then
        
        # Validate results
        local filled_count=$(python3 -c "
import json
data = json.load(open('$scenario_dir/appreciation.json'))
results = data['results']
if isinstance(results, dict):
    filled = [r for r in results.values() if r['filled']]
else:
    filled = [r for r in results if r['filled']]
print(len(filled))
" 2>/dev/null || echo "0")
        
        echo " done"
        
        if [ "$filled_count" = "8" ]; then
            log_success "PASS - Detected 8/8 filled bubbles"
            increment_passed
            return 0
        else
            log_error "FAIL - Detected $filled_count/8 filled bubbles"
            increment_failed
            return 1
        fi
    else
        echo " failed"
        log_error "Appreciation error"
        increment_failed
        return 1
    fi
}

# If executed directly
if [ "${BASH_SOURCE[0]}" = "${0}" ]; then
    # Parse arguments
    RUN_DIR="${1:?Run directory required}"
    COORDS_FILE="${2:?Coordinates file required}"
    CONFIG_DIR="${3:?Config directory required}"
    BLANK_BALLOT="${4:?Blank ballot required}"
    
    run_scenario "$RUN_DIR" "$COORDS_FILE" "$CONFIG_DIR" "$BLANK_BALLOT"
fi
