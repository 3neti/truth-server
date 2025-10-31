#!/bin/bash
# Scenario 3: Faint Marks Detection
# Tests: Bubbles filled with lower intensity to simulate faint marks

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LIB_DIR="$SCRIPT_DIR/../lib"

# Source libraries
source "$LIB_DIR/common.sh"
source "$LIB_DIR/ballot-renderer.sh"
source "$LIB_DIR/overlay-generator.sh"

# Fill bubbles with faint intensity
# Args: blank_ballot, bubble_ids (comma-separated), coords_file, output_file, intensity (0-255)
fill_faint_bubbles() {
    local blank_ballot=$1
    local bubble_ids=$2
    local coords_file=$3
    local output_file=$4
    local intensity="${5:-180}"  # Default: light gray (faint)
    
    python3 <<PYFAINT
import cv2
import json
import sys

try:
    ballot = cv2.imread('$blank_ballot')
    if ballot is None:
        raise Exception("Could not load ballot image")
    
    with open('$coords_file') as f:
        coords = json.load(f)
    
    mm_to_px = 300 / 25.4
    bubble_list = '$bubble_ids'.split(',')
    filled_count = 0
    
    for bubble_id in bubble_list:
        bubble_id = bubble_id.strip()
        if bubble_id in coords['bubble']:
            bubble = coords['bubble'][bubble_id]
            cx = int(bubble['center_x'] * mm_to_px)
            cy = int(bubble['center_y'] * mm_to_px)
            radius = int(bubble['diameter'] / 2 * mm_to_px)
            
            # Fill with faint color (gray instead of black)
            gray_value = int($intensity)
            cv2.circle(ballot, (cx, cy), radius - 1, (gray_value, gray_value, gray_value), -1)
            filled_count += 1
    
    cv2.imwrite('$output_file', ballot)
    print(f"Filled {filled_count} bubbles with faint marks (intensity: $intensity)")
    
except Exception as e:
    print(f"Error: {e}", file=sys.stderr)
    sys.exit(1)
PYFAINT

    return $?
}

# Main scenario function
run_scenario() {
    local run_dir=$1
    local coords_file=$2
    local config_dir=$3
    local blank_ballot=$4
    local appreciate_script="${5:-packages/omr-appreciation/omr-python/appreciate.py}"
    
    local scenario_dir="$run_dir/scenario-3-faint"
    mkdir -p "$scenario_dir"
    
    log_scenario "Scenario 3: Faint Marks Detection"
    
    # Select bubbles: 1 Punong + 5 Sangguniang with faint marks
    local selected="A3,B2,C3,D4,E5,F6"
    
    # Fill with faint intensity (180 = light gray)
    echo -n "  Filling bubbles with faint marks..."
    if fill_faint_bubbles "$blank_ballot" "$selected" "$coords_file" "$scenario_dir/filled_ballot.png" 180; then
        echo " done"
        log_success "Bubbles filled with faint intensity"
    else
        echo " failed"
        log_error "Failed to fill faint bubbles"
        increment_failed
        return 1
    fi
    
    # Save selection metadata
    echo "{\"selected\": [\"A3\", \"B2\", \"C3\", \"D4\", \"E5\", \"F6\"], \"expected\": 6, \"note\": \"Faint marks with intensity 180 (light gray)\"}" \
        > "$scenario_dir/selections.json"
    
    # Run appreciation with lower threshold for faint marks
    echo -n "  Running appreciation..."
    if python3 "$appreciate_script" \
        "$scenario_dir/filled_ballot.png" \
        "$coords_file" \
        --threshold 0.15 \
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
        
        # Accept 4+ detections as pass (faint marks are harder to detect)
        if [ "$filled_count" -ge 4 ]; then
            log_success "PASS - Detected $filled_count/6 faint marks (threshold: 4+)"
            
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
            log_error "FAIL - Detected only $filled_count/6 faint marks (expected 4+)"
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
    RUN_DIR="${1:?Run directory required}"
    COORDS_FILE="${2:?Coordinates file required}"
    CONFIG_DIR="${3:?Config directory required}"
    BLANK_BALLOT="${4:?Blank ballot required}"
    
    run_scenario "$RUN_DIR" "$COORDS_FILE" "$CONFIG_DIR" "$BLANK_BALLOT"
fi
