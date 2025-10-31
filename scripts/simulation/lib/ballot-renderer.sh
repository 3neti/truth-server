#!/bin/bash
# Ballot rendering functions

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Render blank ballot with bubbles, fiducials, and QR code
# Args: coords_file, output_file, [qr_data]
render_blank_ballot() {
    local coords_file=$1
    local output_file=$2
    local qr_data="${3:-SIMULATION-BALLOT}"
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file"
        return 1
    fi
    
    python3 <<PYRENDER
import cv2
import numpy as np
import json
import sys

try:
    # Load coordinates
    with open('$coords_file') as f:
        coords = json.load(f)
    
    # Get ballot size (convert mm to pixels at 300 DPI)
    mm_to_px = 300 / 25.4  # 300 DPI
    width_mm = coords['ballot_size']['width_mm']
    height_mm = coords['ballot_size']['height_mm']
    width_px = int(width_mm * mm_to_px)
    height_px = int(height_mm * mm_to_px)
    
    # Create white canvas
    ballot = np.ones((height_px, width_px, 3), dtype=np.uint8) * 255
    
    # Draw ArUco fiducial markers
    # Check if ArUco is available
    aruco_available = hasattr(cv2, 'aruco')
    
    for fid_id, fid in coords['fiducial'].items():
        x = int(fid['x'] * mm_to_px)
        y = int(fid['y'] * mm_to_px)
        size = int(10 * mm_to_px)  # 10mm marker
        
        if aruco_available:
            # Generate real ArUco marker
            try:
                marker_id = fid.get('marker_id', 101)
                aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_4X4_100)
                marker_img = cv2.aruco.generateImageMarker(aruco_dict, marker_id, 200)
                
                # Resize and convert to BGR
                marker_resized = cv2.resize(marker_img, (size, size))
                if len(marker_resized.shape) == 2:
                    marker_resized = cv2.cvtColor(marker_resized, cv2.COLOR_GRAY2BGR)
                
                # Place ArUco marker on ballot
                ballot[y:y+size, x:x+size] = marker_resized
            except Exception as e:
                # Fallback to placeholder if ArUco fails
                cv2.rectangle(ballot, (x, y), (x + size, y + size), (0, 0, 0), 3)
                cv2.putText(ballot, str(fid.get('marker_id', fid_id)), (x + 5, y + 25), 
                            cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 0), 2)
        else:
            # Placeholder box if ArUco not available
            cv2.rectangle(ballot, (x, y), (x + size, y + size), (0, 0, 0), 3)
            cv2.putText(ballot, str(fid.get('marker_id', fid_id)), (x + 5, y + 25), 
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 0), 2)
    
    # Draw bubbles
    for bubble_id, bubble in coords['bubble'].items():
        cx = int(bubble['center_x'] * mm_to_px)
        cy = int(bubble['center_y'] * mm_to_px)
        radius = int(bubble['diameter'] / 2 * mm_to_px)
        
        # Draw circle with thicker border
        cv2.circle(ballot, (cx, cy), radius, (0, 0, 0), 3)
        
        # Add bubble ID label
        label_x = cx - radius - 50
        label_y = cy + 5
        cv2.putText(ballot, bubble_id, (label_x, label_y),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.3, (100, 100, 100), 1)
    
    # Add QR code placeholder (if barcode area exists)
    if 'barcode' in coords and 'document_barcode' in coords['barcode']:
        bc = coords['barcode']['document_barcode']
        x = int(bc['x'] * mm_to_px)
        y = int(bc['y'] * mm_to_px)
        w = int(bc['width'] * mm_to_px)
        h = int(bc['height'] * mm_to_px)
        
        # Draw QR placeholder box
        cv2.rectangle(ballot, (x, y), (x + w, y + h), (0, 0, 0), 2)
        cv2.putText(ballot, 'QR', (x + 5, y + h // 2),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.8, (100, 100, 100), 2)
    
    # Save ballot
    cv2.imwrite('$output_file', ballot)
    print(f"Ballot rendered: {width_mm}mm x {height_mm}mm ({width_px}px x {height_px}px)")
    
except Exception as e:
    print(f"Error rendering ballot: {e}", file=sys.stderr)
    sys.exit(1)
PYRENDER

    if [ $? -eq 0 ]; then
        log_success "Blank ballot rendered: $output_file"
        return 0
    else
        log_error "Failed to render blank ballot"
        return 1
    fi
}

# Fill specific bubbles on a ballot
# Args: blank_ballot, bubble_ids (comma-separated), coords_file, output_file
fill_bubbles() {
    local blank_ballot=$1
    local bubble_ids=$2
    local coords_file=$3
    local output_file=$4
    
    if [ ! -f "$blank_ballot" ]; then
        log_error "Blank ballot not found: $blank_ballot"
        return 1
    fi
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file"
        return 1
    fi
    
    python3 <<PYFILL
import cv2
import json
import sys

try:
    # Load ballot and coordinates
    ballot = cv2.imread('$blank_ballot')
    if ballot is None:
        raise Exception("Could not load ballot image")
    
    with open('$coords_file') as f:
        coords = json.load(f)
    
    mm_to_px = 300 / 25.4
    
    # Parse bubble IDs
    bubble_list = '$bubble_ids'.split(',')
    filled_count = 0
    
    for bubble_id in bubble_list:
        bubble_id = bubble_id.strip()
        if bubble_id in coords['bubble']:
            bubble = coords['bubble'][bubble_id]
            cx = int(bubble['center_x'] * mm_to_px)
            cy = int(bubble['center_y'] * mm_to_px)
            radius = int(bubble['diameter'] / 2 * mm_to_px)
            
            # Fill bubble (black) - radius-1 for better fill ratio
            cv2.circle(ballot, (cx, cy), radius - 1, (0, 0, 0), -1)
            filled_count += 1
    
    # Save filled ballot
    cv2.imwrite('$output_file', ballot)
    print(f"Filled {filled_count} bubbles")
    
except Exception as e:
    print(f"Error filling bubbles: {e}", file=sys.stderr)
    sys.exit(1)
PYFILL

    if [ $? -eq 0 ]; then
        log_success "Bubbles filled: $output_file"
        return 0
    else
        log_error "Failed to fill bubbles"
        return 1
    fi
}

# Render complete ballot from votes.json
# Args: votes_file, coords_file, output_file, [log_file]
render_ballot() {
    local votes_file=$1
    local coords_file=$2
    local output_file=$3
    local log_file="${4:-/dev/null}"
    
    if [ ! -f "$votes_file" ]; then
        log_error "Votes file not found: $votes_file" | tee -a "$log_file"
        return 1
    fi
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file" | tee -a "$log_file"
        return 1
    fi
    
    log_debug "Rendering ballot from votes: $votes_file" | tee -a "$log_file"
    
    python3 <<PYRENDER 2>&1 | tee -a "$log_file"
import cv2
import numpy as np
import json
import sys

try:
    # Load coordinates and votes
    with open('$coords_file') as f:
        coords = json.load(f)
    
    with open('$votes_file') as f:
        votes = json.load(f)
    
    # Get ballot size (convert mm to pixels at 300 DPI)
    mm_to_px = 300 / 25.4  # 300 DPI
    width_mm = coords['ballot_size']['width_mm']
    height_mm = coords['ballot_size']['height_mm']
    width_px = int(width_mm * mm_to_px)
    height_px = int(height_mm * mm_to_px)
    
    # Create white canvas
    ballot = np.ones((height_px, width_px, 3), dtype=np.uint8) * 255
    
    # Draw ArUco fiducial markers
    aruco_available = hasattr(cv2, 'aruco')
    
    for fid_id, fid in coords['fiducial'].items():
        x = int(fid['x'] * mm_to_px)
        y = int(fid['y'] * mm_to_px)
        size = int(10 * mm_to_px)  # 10mm marker
        
        if aruco_available:
            # Generate real ArUco marker
            try:
                marker_id = fid.get('marker_id', 101)
                aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_4X4_100)
                marker_img = cv2.aruco.generateImageMarker(aruco_dict, marker_id, 200)
                
                # Resize and convert to BGR
                marker_resized = cv2.resize(marker_img, (size, size))
                if len(marker_resized.shape) == 2:
                    marker_resized = cv2.cvtColor(marker_resized, cv2.COLOR_GRAY2BGR)
                
                # Place ArUco marker on ballot
                ballot[y:y+size, x:x+size] = marker_resized
            except Exception as e:
                # Fallback to placeholder
                cv2.rectangle(ballot, (x, y), (x + size, y + size), (0, 0, 0), 3)
                cv2.putText(ballot, str(marker_id), (x + 5, y + 25), 
                            cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 0), 2)
        else:
            # Placeholder box
            marker_id = fid.get('marker_id', fid_id)
            cv2.rectangle(ballot, (x, y), (x + size, y + size), (0, 0, 0), 3)
            cv2.putText(ballot, str(marker_id), (x + 5, y + 25), 
                        cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 0), 2)
    
    # Draw all bubbles and fill voted ones
    filled_count = 0
    for bubble_id, bubble in coords['bubble'].items():
        cx = int(bubble['center_x'] * mm_to_px)
        cy = int(bubble['center_y'] * mm_to_px)
        radius = int(bubble['diameter'] / 2 * mm_to_px)
        
        # Draw circle border
        cv2.circle(ballot, (cx, cy), radius, (0, 0, 0), 2)
        
        # Check if this bubble should be filled
        if bubble_id in votes:
            vote = votes[bubble_id]
            filled = vote.get('filled', False)
            fill_ratio = vote.get('fill_ratio', 0.7)
            
            if filled or fill_ratio > 0.15:  # Fill if marked or has significant fill
                # Calculate fill intensity based on fill_ratio
                # fill_ratio 1.0 = fully black (0), fill_ratio 0.0 = white (255)
                gray_value = int(255 * (1 - fill_ratio))
                gray_value = max(0, min(255, gray_value))  # Clamp to valid range
                
                # Fill bubble with calculated gray value
                cv2.circle(ballot, (cx, cy), radius - 2, (gray_value, gray_value, gray_value), -1)
                filled_count += 1
        
        # Add bubble ID label (small, gray text to the left)
        label_x = cx - radius - 40
        label_y = cy + 4
        cv2.putText(ballot, bubble_id, (label_x, label_y),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.25, (150, 150, 150), 1)
    
    # Add QR code placeholder (if barcode area exists)
    if 'barcode' in coords and 'document_barcode' in coords['barcode']:
        bc = coords['barcode']['document_barcode']
        x = int(bc['x'] * mm_to_px)
        y = int(bc['y'] * mm_to_px)
        w = int(bc['width'] * mm_to_px)
        h = int(bc['height'] * mm_to_px)
        
        # Draw QR placeholder box
        cv2.rectangle(ballot, (x, y), (x + w, y + h), (0, 0, 0), 2)
        cv2.putText(ballot, 'QR CODE', (x + w//4, y + h // 2),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.6, (100, 100, 100), 2)
    
    # Save ballot
    cv2.imwrite('$output_file', ballot)
    print(f"Ballot rendered: {width_mm}mm x {height_mm}mm ({width_px}px x {height_px}px), {filled_count} bubbles filled")
    
except Exception as e:
    print(f"Error rendering ballot: {e}", file=sys.stderr)
    import traceback
    traceback.print_exc()
    sys.exit(1)
PYRENDER

    if [ $? -eq 0 ]; then
        log_success "Ballot rendered: $output_file" | tee -a "$log_file"
        return 0
    else
        log_error "Failed to render ballot" | tee -a "$log_file"
        return 1
    fi
}

# Export functions
export -f render_blank_ballot fill_bubbles render_ballot
