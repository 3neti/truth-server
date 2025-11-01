#!/usr/bin/env bash
# Ballot Appreciator Library
# Computer vision-based ballot detection and bubble appreciation

set -eo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
source "${SCRIPT_DIR}/common.sh"

# Appreciate ballot image and detect filled bubbles
# Args: ballot_image, coords_file, output_file, [log_file]
appreciate_ballot() {
    local ballot_image="$1"
    local coords_file="$2"
    local output_file="$3"
    local log_file="${4:-/dev/null}"
    
    if [ ! -f "$ballot_image" ]; then
        log_error "Ballot image not found: $ballot_image" | tee -a "$log_file"
        return 1
    fi
    
    if [ ! -f "$coords_file" ]; then
        log_error "Coordinates file not found: $coords_file" | tee -a "$log_file"
        return 1
    fi
    
    log_debug "Appreciating ballot: $ballot_image" >> "$log_file" 2>&1
    
    # Check Python dependencies
    if ! check_python_module cv2; then
        log_error "OpenCV (cv2) required for ballot appreciation" >> "$log_file" 2>&1
        return 1
    fi
    
    if ! check_python_module numpy; then
        log_error "NumPy required for ballot appreciation" >> "$log_file" 2>&1
        return 1
    fi
    
    # Run appreciation using Python
    python3 <<PYAPPRECIATE >> "$log_file" 2>&1
import cv2
import numpy as np
import json
import sys
from pathlib import Path

def detect_aruco_markers(image):
    """Detect ArUco markers in the ballot image"""
    try:
        # Use DICT_4X4_250 to support marker IDs up to 249
        aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_4X4_250)
        
        # Try new API first (OpenCV 4.7+)
        try:
            detector = cv2.aruco.ArucoDetector(aruco_dict)
            corners, ids, rejected = detector.detectMarkers(image)
        except AttributeError:
            # Fall back to old API
            aruco_params = cv2.aruco.DetectorParameters_create()
            corners, ids, rejected = cv2.aruco.detectMarkers(
                image, aruco_dict, parameters=aruco_params
            )
        
        if ids is not None and len(ids) >= 4:
            return True, corners, ids.flatten()
        else:
            return False, None, None
    except Exception as e:
        print(f"ArUco detection error: {e}", file=sys.stderr)
        return False, None, None

def apply_perspective_correction(image, corners, ids, expected_fiducials):
    """Apply perspective correction based on ArUco markers"""
    try:
        # Map detected markers to expected positions
        marker_positions = {}
        for i, marker_id in enumerate(ids):
            if marker_id in expected_fiducials:
                # Get center of marker
                corner = corners[i][0]
                center = corner.mean(axis=0)
                marker_positions[marker_id] = center
        
        if len(marker_positions) < 4:
            print(f"Warning: Only {len(marker_positions)} markers detected, need 4 for perspective correction")
            return image, False
        
        # For now, return original image if markers are detected
        # Full perspective correction would require mapping to ideal positions
        return image, True
        
    except Exception as e:
        print(f"Perspective correction error: {e}", file=sys.stderr)
        return image, False

def calculate_bubble_fill_ratio(image, center_x, center_y, radius, mm_to_px):
    """Calculate fill ratio for a single bubble"""
    try:
        # Convert coordinates to pixels
        cx = int(center_x * mm_to_px)
        cy = int(center_y * mm_to_px)
        r = int(radius * mm_to_px)
        
        # Check bounds
        h, w = image.shape[:2]
        if cx < r or cy < r or cx + r >= w or cy + r >= h:
            return 0.0, 0.0
        
        # Extract bubble region
        bubble_region = image[cy-r:cy+r, cx-r:cx+r]
        
        # Convert to grayscale if needed
        if len(bubble_region.shape) == 3:
            bubble_gray = cv2.cvtColor(bubble_region, cv2.COLOR_BGR2GRAY)
        else:
            bubble_gray = bubble_region
        
        # Create circular mask
        mask = np.zeros(bubble_gray.shape, dtype=np.uint8)
        mask_center = (r, r)
        cv2.circle(mask, mask_center, r-2, 255, -1)  # Slightly smaller to avoid border
        
        # Calculate fill ratio
        # Lower pixel values = darker = more filled
        # Normalize to 0-1 range where 1 = fully filled (black)
        masked_pixels = bubble_gray[mask > 0]
        if len(masked_pixels) == 0:
            return 0.0, 0.0
        
        mean_intensity = np.mean(masked_pixels)
        # Convert to fill ratio: 255 (white) = 0.0, 0 (black) = 1.0
        fill_ratio = 1.0 - (mean_intensity / 255.0)
        
        # Calculate confidence based on standard deviation
        # Low std = uniform fill = high confidence
        std_intensity = np.std(masked_pixels)
        confidence = 1.0 - min(std_intensity / 128.0, 1.0)
        
        return fill_ratio, confidence
        
    except Exception as e:
        print(f"Error calculating fill ratio: {e}", file=sys.stderr)
        return 0.0, 0.0

def appreciate_ballot_image(ballot_path, coords_path, output_path):
    """Main ballot appreciation function"""
    try:
        # Load ballot image
        ballot = cv2.imread(ballot_path)
        if ballot is None:
            raise Exception(f"Could not load ballot image: {ballot_path}")
        
        # Load coordinates
        with open(coords_path) as f:
            coords = json.load(f)
        
        # Calculate DPI scaling
        mm_to_px = 300 / 25.4  # 300 DPI
        
        # Detect ArUco markers
        print("Detecting ArUco fiducial markers...")
        markers_detected, corners, ids = detect_aruco_markers(ballot)
        
        # Get expected fiducial IDs
        expected_fiducials = [
            int(fid.get('marker_id', fid_id)) 
            for fid_id, fid in coords.get('fiducial', {}).items()
        ]
        
        # Apply perspective correction if needed
        perspective_corrected = False
        if markers_detected:
            print(f"Detected {len(ids)} ArUco markers: {ids}")
            ballot, perspective_corrected = apply_perspective_correction(
                ballot, corners, ids, expected_fiducials
            )
        else:
            print("Warning: No ArUco markers detected, proceeding without perspective correction")
        
        # Process each bubble
        print(f"Processing {len(coords['bubble'])} bubbles...")
        bubbles = []
        
        for bubble_id, bubble_data in coords['bubble'].items():
            cx = bubble_data['center_x']
            cy = bubble_data['center_y']
            diameter = bubble_data['diameter']
            radius = diameter / 2
            
            # Calculate fill ratio
            fill_ratio, confidence = calculate_bubble_fill_ratio(
                ballot, cx, cy, radius, mm_to_px
            )
            
            # Determine if filled (threshold at 0.3)
            filled = fill_ratio > 0.3
            
            bubbles.append({
                'bubble_id': bubble_id,
                'filled': bool(filled),  # Convert numpy.bool_ to Python bool
                'fill_ratio': float(round(fill_ratio, 4)),
                'confidence': float(round(confidence, 4)),
                'center_x': float(cx),
                'center_y': float(cy),
                'radius': float(radius)
            })
        
        # Create results
        results = {
            'ballot_image': ballot_path,
            'coordinates_file': coords_path,
            'fiducials_detected': bool(markers_detected),
            'num_fiducials': int(len(ids)) if ids is not None else 0,
            'perspective_corrected': bool(perspective_corrected),
            'bubbles': bubbles,
            'total_bubbles': int(len(bubbles)),
            'filled_bubbles': int(sum(1 for b in bubbles if b['filled'])),
            'fill_threshold': 0.3,
            'average_confidence': float(round(np.mean([b['confidence'] for b in bubbles]), 4))
        }
        
        # Save results
        with open(output_path, 'w') as f:
            json.dump(results, f, indent=2)
        
        print(f"Appreciation complete:")
        print(f"  Total bubbles: {results['total_bubbles']}")
        print(f"  Filled bubbles: {results['filled_bubbles']}")
        print(f"  Average confidence: {results['average_confidence']:.2f}")
        print(f"  Fiducials detected: {results['num_fiducials']}/4")
        
        return 0
        
    except Exception as e:
        print(f"Error appreciating ballot: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        return 1

# Main execution
if __name__ == '__main__':
    sys.exit(appreciate_ballot_image(
        '$ballot_image',
        '$coords_file',
        '$output_file'
    ))
PYAPPRECIATE

    if [ $? -eq 0 ]; then
        log_success "Ballot appreciated: $output_file" >> "$log_file" 2>&1
        return 0
    else
        log_error "Failed to appreciate ballot" >> "$log_file" 2>&1
        return 1
    fi
}

# Compare appreciation results with ground truth
# Args: results_file, votes_file, output_file
compare_results() {
    local results_file="$1"
    local votes_file="$2"
    local output_file="$3"
    
    if [ ! -f "$results_file" ]; then
        log_error "Results file not found: $results_file"
        return 1
    fi
    
    if [ ! -f "$votes_file" ]; then
        log_error "Votes file not found: $votes_file"
        return 1
    fi
    
    log_debug "Comparing results with ground truth"
    
    python3 <<PYCOMPARE
import json
import sys

try:
    # Load results and ground truth
    with open('$results_file') as f:
        results = json.load(f)
    
    with open('$votes_file') as f:
        votes = json.load(f)
    
    # Create lookup for results
    detected = {}
    for bubble in results['bubbles']:
        detected[bubble['bubble_id']] = bubble['filled']
    
    # Compare with ground truth
    true_positives = 0
    false_positives = 0
    true_negatives = 0
    false_negatives = 0
    
    all_bubble_ids = set(detected.keys()) | set(votes.keys())
    
    for bubble_id in all_bubble_ids:
        ground_truth = votes.get(bubble_id, {}).get('filled', False)
        detected_filled = detected.get(bubble_id, False)
        
        if ground_truth and detected_filled:
            true_positives += 1
        elif not ground_truth and detected_filled:
            false_positives += 1
        elif not ground_truth and not detected_filled:
            true_negatives += 1
        elif ground_truth and not detected_filled:
            false_negatives += 1
    
    total = len(all_bubble_ids)
    accuracy = (true_positives + true_negatives) / total if total > 0 else 0
    precision = true_positives / (true_positives + false_positives) if (true_positives + false_positives) > 0 else 0
    recall = true_positives / (true_positives + false_negatives) if (true_positives + false_negatives) > 0 else 0
    f1 = 2 * (precision * recall) / (precision + recall) if (precision + recall) > 0 else 0
    
    comparison = {
        'true_positives': true_positives,
        'false_positives': false_positives,
        'true_negatives': true_negatives,
        'false_negatives': false_negatives,
        'total_bubbles': total,
        'accuracy': round(accuracy, 4),
        'precision': round(precision, 4),
        'recall': round(recall, 4),
        'f1_score': round(f1, 4)
    }
    
    with open('$output_file', 'w') as f:
        json.dump(comparison, f, indent=2)
    
    print(f"Comparison complete:")
    print(f"  Accuracy: {accuracy:.2%}")
    print(f"  Precision: {precision:.2%}")
    print(f"  Recall: {recall:.2%}")
    print(f"  F1 Score: {f1:.4f}")
    
except Exception as e:
    print(f"Error comparing results: {e}", file=sys.stderr)
    sys.exit(1)
PYCOMPARE

    if [ $? -eq 0 ]; then
        log_success "Comparison complete: $output_file"
        return 0
    else
        log_error "Failed to compare results"
        return 1
    fi
}

# Generate visual overlay on ballot image
# Args: ballot_image, results_file, output_file, [log_file]
generate_overlay() {
    local ballot_image="$1"
    local results_file="$2"
    local output_file="$3"
    local log_file="${4:-/dev/null}"
    
    if [ ! -f "$ballot_image" ]; then
        log_error "Ballot image not found: $ballot_image" >> "$log_file" 2>&1
        return 1
    fi
    
    if [ ! -f "$results_file" ]; then
        log_error "Results file not found: $results_file" >> "$log_file" 2>&1
        return 1
    fi
    
    log_debug "Generating overlay: $output_file" >> "$log_file" 2>&1
    
    # Use Python to generate overlay
    python3 <<PYOVERLAY >> "$log_file" 2>&1
import cv2
import json
import sys

try:
    # Load ballot and results
    ballot = cv2.imread('$ballot_image')
    if ballot is None:
        raise Exception("Could not load ballot image")
    
    with open('$results_file') as f:
        results = json.load(f)
    
    # Create copy for overlay
    overlay = ballot.copy()
    
    # Draw circles on filled bubbles
    mm_to_px = 300 / 25.4  # 300 DPI
    
    for bubble in results['bubbles']:
        cx = int(bubble['center_x'] * mm_to_px)
        cy = int(bubble['center_y'] * mm_to_px)
        radius = int(bubble['radius'] * mm_to_px)
        
        # Color based on filled status
        if bubble['filled']:
            # Green for filled
            color = (0, 255, 0)
            thickness = 3
        else:
            # Red for not filled
            color = (0, 0, 255)
            thickness = 2
        
        # Draw circle
        cv2.circle(overlay, (cx, cy), radius, color, thickness)
        
        # Add fill ratio text
        fill_ratio = bubble['fill_ratio']
        label = f"{fill_ratio:.2f}"
        cv2.putText(overlay, label, (cx + radius + 5, cy + 5),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.3, color, 1)
    
    # Save overlay
    cv2.imwrite('$output_file', overlay)
    print(f"Overlay generated with {results['filled_bubbles']} filled bubbles")
    
except Exception as e:
    print(f"Error generating overlay: {e}", file=sys.stderr)
    import traceback
    traceback.print_exc()
    sys.exit(1)
PYOVERLAY
    
    if [ $? -eq 0 ]; then
        log_success "Overlay generated: $output_file" >> "$log_file" 2>&1
        return 0
    else
        log_error "Overlay generation failed" >> "$log_file" 2>&1
        return 1
    fi
}

# Export functions
export -f appreciate_ballot compare_results generate_overlay
