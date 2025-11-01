#!/bin/bash
# Overlay generation for appreciation results

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Create overlay with colored circles on appreciation results
# Args: ballot_image, appreciation_json, coords_file, output_file
create_overlay() {
    local ballot_image=$1
    local appreciation_json=$2
    local coords_file=$3
    local output_file=$4
    
    if [ ! -f "$ballot_image" ]; then
        log_error "Ballot image not found: $ballot_image"
        return 1
    fi
    
    if [ ! -f "$appreciation_json" ]; then
        log_error "Appreciation JSON not found: $appreciation_json"
        return 1
    fi
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file"
        return 1
    fi
    
    python3 <<PYOVERLAY
import cv2
import numpy as np
import json
import sys

try:
    # Load ballot
    ballot = cv2.imread('$ballot_image')
    if ballot is None:
        raise Exception("Could not load ballot image")
    
    # Load coordinates
    with open('$coords_file') as f:
        coords = json.load(f)
    
    # Load appreciation results
    with open('$appreciation_json') as f:
        appreciation = json.load(f)
    
    mm_to_px = 300 / 25.4
    
    # Parse results (handle both dict and list formats)
    results = appreciation['results']
    if isinstance(results, list):
        results_dict = {r['id']: r for r in results}
    else:
        results_dict = results
    
    # Draw colored circles on bubbles
    filled_count = 0
    unfilled_count = 0
    
    for bubble_id, result in results_dict.items():
        if bubble_id not in coords['bubble']:
            continue
        
        bubble = coords['bubble'][bubble_id]
        cx = int(bubble['center_x'] * mm_to_px)
        cy = int(bubble['center_y'] * mm_to_px)
        radius = int(bubble['diameter'] / 2 * mm_to_px)
        
        is_filled = result.get('filled', False)
        fill_ratio = result.get('fill_ratio', 0.0)
        
        if is_filled:
            # Green circle for filled/detected
            color = (0, 255, 0)  # Green (BGR)
            filled_count += 1
        else:
            # Red circle for unfilled/not detected
            color = (0, 0, 255)  # Red (BGR)
            unfilled_count += 1
        
        # Draw thick colored circle
        cv2.circle(ballot, (cx, cy), radius + 5, color, 4)
        
        # Add confidence text
        confidence = result.get('confidence', 0.0)
        text = f"{fill_ratio:.2f}"
        cv2.putText(ballot, text, (cx - 30, cy - radius - 10),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.4, color, 1)
    
    # Add legend
    legend_x = 50
    legend_y = 50
    
    # Background for legend
    cv2.rectangle(ballot, (legend_x - 10, legend_y - 35),
                  (legend_x + 200, legend_y + 60), (255, 255, 255), -1)
    cv2.rectangle(ballot, (legend_x - 10, legend_y - 35),
                  (legend_x + 200, legend_y + 60), (0, 0, 0), 2)
    
    # Legend text
    cv2.putText(ballot, "Results:", (legend_x, legend_y),
                cv2.FONT_HERSHEY_SIMPLEX, 0.6, (0, 0, 0), 2)
    cv2.circle(ballot, (legend_x + 10, legend_y + 20), 8, (0, 255, 0), -1)
    cv2.putText(ballot, f"Filled: {filled_count}", (legend_x + 25, legend_y + 25),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 0), 1)
    cv2.circle(ballot, (legend_x + 10, legend_y + 45), 8, (0, 0, 255), -1)
    cv2.putText(ballot, f"Unfilled: {unfilled_count}", (legend_x + 25, legend_y + 50),
                cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 0), 1)
    
    # Save overlay
    cv2.imwrite('$output_file', ballot)
    print(f"Overlay created: {filled_count} filled, {unfilled_count} unfilled")
    
except Exception as e:
    print(f"Error creating overlay: {e}", file=sys.stderr)
    sys.exit(1)
PYOVERLAY

    if [ $? -eq 0 ]; then
        log_success "Overlay created: $output_file"
        return 0
    else
        log_error "Failed to create overlay"
        return 1
    fi
}

# Generate overlay from ballot and appreciation results
# Args: ballot_image, appreciation_json, output_file, [log_file]
generate_overlay() {
    local ballot_image="$1"
    local appreciation_json="$2"
    local output_file="$3"
    local log_file="${4:-/dev/null}"
    
    log_debug "Generating overlay: $ballot_image" >> "$log_file" 2>&1
    
    # Use config dir if available from environment
    local config_args=""
    if [ -n "${CONFIG_DIR:-}" ]; then
        config_args="--config-dir=${CONFIG_DIR}"
    fi
    
    # Get coordinates file from environment or use default
    local coords_file="${COORDINATES_FILE:-}"
    if [ -z "$coords_file" ]; then
        log_error "COORDINATES_FILE environment variable not set" >> "$log_file" 2>&1
        return 1
    fi
    
    # Call Laravel artisan command
    if php artisan simulation:create-overlay \
        "$ballot_image" \
        "$appreciation_json" \
        "$coords_file" \
        "$output_file" \
        $config_args \
        --show-legend \
        >> "$log_file" 2>&1; then
        log_success "Overlay generated: $output_file" >> "$log_file" 2>&1
        return 0
    else
        log_error "Failed to generate overlay" >> "$log_file" 2>&1
        return 1
    fi
}

# Export functions
export -f create_overlay generate_overlay
