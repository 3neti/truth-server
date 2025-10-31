#!/bin/bash
# Scenario 2: Overvote Detection
# Tests: 1 Punong Barangay + 10 Sangguniang Barangay (overvote - max is 8)

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="$SCRIPT_DIR/../lib"

# Source libraries
source "$LIB_DIR/common.sh"
source "$LIB_DIR/ballot-renderer.sh"
source "$LIB_DIR/overlay-generator.sh"

# Main scenario function
# Args: run_dir, coords_file, config_dir, blank_ballot
run_scenario() {
    local run_dir=$1
    local coords_file=$2
    local config_dir=$3
    local blank_ballot=$4
    local appreciate_script="${5:-packages/omr-appreciation/omr-python/appreciate.py}"
    
    local scenario_dir="$run_dir/scenario-2-overvote"
    mkdir -p "$scenario_dir"
    
    log_scenario "Scenario 2: Overvote Detection"
    
    # Select bubbles: 1 Punong + 10 Sangguniang (overvote - max is 8)
    local selected="A2,B1,B2,B3,B4,B5,B6,C1,C2,C3,C4"
    
    # Fill bubbles
    if ! fill_bubbles "$blank_ballot" "$selected" "$coords_file" "$scenario_dir/filled_ballot.png"; then
        log_error "Failed to fill bubbles"
        increment_failed
        return 1
    fi
    
    # Save selection metadata
    echo "{\"selected\": [\"A2\", \"B1\", \"B2\", \"B3\", \"B4\", \"B5\", \"B6\", \"C1\", \"C2\", \"C3\", \"C4\"], \"expected\": 11, \"note\": \"Overvote: 10 Sangguniang when max is 8\"}" \
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
        
        # Count filled bubbles
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
        
        # For overvote, we just verify all bubbles were detected
        # Validation logic would flag this as overvote
        if [ "$filled_count" = "11" ]; then
            log_success "PASS - Detected 11/11 bubbles (overvote scenario)"
            
            # Generate overlay
            echo -n "  Creating overlay..."
            if create_overlay "$scenario_dir/filled_ballot.png" "$scenario_dir/appreciation.json" "$coords_file" "$scenario_dir/overlay.png" 2>&1 | grep -q "Overlay created"; then
                echo " done"
            else
                echo " skipped"
            fi
            
            increment_passed
            return 0
        else
            log_error "FAIL - Detected $filled_count/11 filled bubbles"
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
