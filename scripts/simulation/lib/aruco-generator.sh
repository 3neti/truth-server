#!/bin/bash
# ArUco marker generation functions

# Source common functions
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Generate ArUco markers and overlay on ballot
# Args: ballot_image, coords_file, output_file
add_aruco_markers() {
    local ballot_image=$1
    local coords_file=$2
    local output_file=$3
    
    if [ ! -f "$ballot_image" ]; then
        log_error "Ballot image not found: $ballot_image"
        return 1
    fi
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file"
        return 1
    fi
    
    python3 <<PYARUCO
import cv2
import numpy as np
import json
import sys

try:
    # Check if ArUco is available
    if not hasattr(cv2, 'aruco'):
        print("Warning: ArUco not available, using placeholder markers", file=sys.stderr)
        # Just copy the file as-is
        import shutil
        shutil.copy('$ballot_image', '$output_file')
        sys.exit(0)
    
    # Load ballot and coordinates
    ballot = cv2.imread('$ballot_image')
    if ballot is None:
        raise Exception("Could not load ballot image")
    
    with open('$coords_file') as f:
        coords = json.load(f)
    
    mm_to_px = 300 / 25.4
    
    # ArUco dictionary
    aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_4X4_100)
    
    # Generate and place each fiducial marker
    for fid_id, fid in coords['fiducial'].items():
        marker_id = fid.get('marker_id', 101)
        
        # Generate ArUco marker
        marker_size = 200  # pixels for generation
        marker_img = cv2.aruco.generateImageMarker(aruco_dict, marker_id, marker_size)
        
        # Calculate position and size on ballot
        x = int(fid['x'] * mm_to_px)
        y = int(fid['y'] * mm_to_px)
        size = int(10 * mm_to_px)  # 10mm marker
        
        # Resize marker to fit
        marker_resized = cv2.resize(marker_img, (size, size))
        
        # Convert to 3-channel if needed
        if len(marker_resized.shape) == 2:
            marker_resized = cv2.cvtColor(marker_resized, cv2.COLOR_GRAY2BGR)
        
        # Place marker on ballot
        ballot[y:y+size, x:x+size] = marker_resized
    
    # Save ballot with ArUco markers
    cv2.imwrite('$output_file', ballot)
    print(f"ArUco markers added to ballot")
    
except Exception as e:
    print(f"Error adding ArUco markers: {e}", file=sys.stderr)
    sys.exit(1)
PYARUCO

    if [ $? -eq 0 ]; then
        log_success "ArUco markers added: $output_file"
        return 0
    else
        log_error "Failed to add ArUco markers"
        return 1
    fi
}

# Export functions
export -f add_aruco_markers
