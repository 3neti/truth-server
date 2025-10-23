"""Image alignment using fiducial markers."""

import cv2
import numpy as np
from typing import List, Tuple, Optional


def detect_fiducials(image: np.ndarray, template: dict) -> Optional[List[Tuple[int, int]]]:
    """Detect 4 fiducial markers (black squares) in the image.
    
    Args:
        image: Input image (BGR)
        template: Template dictionary containing fiducial positions
        
    Returns:
        List of 4 (x, y) coordinates for fiducials, or None if detection fails
    """
    # Get expected fiducial positions and sizes from template
    expected_fiducials = template.get('fiducials', [])
    if len(expected_fiducials) != 4:
        return None
    
    # Convert to grayscale
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    # Use adaptive threshold for better detection
    binary = cv2.adaptiveThreshold(
        gray, 255, cv2.ADAPTIVE_THRESH_GAUSSIAN_C, 
        cv2.THRESH_BINARY_INV, 11, 2
    )
    
    # Apply morphological operations to clean up
    kernel = np.ones((3, 3), np.uint8)
    binary = cv2.morphologyEx(binary, cv2.MORPH_CLOSE, kernel)
    binary = cv2.morphologyEx(binary, cv2.MORPH_OPEN, kernel)
    
    # Find contours
    contours, _ = cv2.findContours(binary, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
    
    # Expected fiducial size from template (use average)
    expected_size = expected_fiducials[0].get('width', 70)
    min_area = (expected_size * 0.5) ** 2  # 50% smaller
    max_area = (expected_size * 2.0) ** 2  # 200% larger
    
    # Filter for square-like contours
    candidates = []
    for contour in contours:
        area = cv2.contourArea(contour)
        if area < min_area or area > max_area:
            continue
            
        # Get bounding rectangle
        x, y, w, h = cv2.boundingRect(contour)
        aspect_ratio = float(w) / h if h > 0 else 0
        
        # Square should have aspect ratio close to 1
        if 0.7 <= aspect_ratio <= 1.4:
            # Get center point
            M = cv2.moments(contour)
            if M["m00"] != 0:
                cx = int(M["m10"] / M["m00"])
                cy = int(M["m01"] / M["m00"])
                candidates.append((cx, cy, area))
    
    # We need at least 4 candidates
    if len(candidates) < 4:
        return None
    
    # If more than 4, pick the 4 corner-most candidates
    # Strategy: find candidates closest to the 4 corners
    h, w = image.shape[:2]
    corners = [
        (0, 0),        # top-left
        (w, 0),        # top-right
        (0, h),        # bottom-left
        (w, h)         # bottom-right
    ]
    
    fiducials = []
    used_indices = set()
    
    for corner_x, corner_y in corners:
        best_dist = float('inf')
        best_idx = -1
        
        for idx, (cx, cy, _) in enumerate(candidates):
            if idx in used_indices:
                continue
            dist = ((cx - corner_x) ** 2 + (cy - corner_y) ** 2) ** 0.5
            if dist < best_dist:
                best_dist = dist
                best_idx = idx
        
        if best_idx >= 0:
            fiducials.append((candidates[best_idx][0], candidates[best_idx][1]))
            used_indices.add(best_idx)
    
    # We need exactly 4 fiducials
    if len(fiducials) != 4:
        return None
    
    return fiducials


def align_image(image: np.ndarray, fiducials: List[Tuple[int, int]], template: dict) -> np.ndarray:
    """Apply perspective transform to align image based on fiducials.
    
    Args:
        image: Input image
        fiducials: List of 4 detected fiducial coordinates
        template: Template dictionary with expected fiducial positions
        
    Returns:
        Aligned (deskewed) image
    """
    # Get expected fiducial positions from template
    expected = template.get('fiducials', [])
    
    if len(expected) != 4:
        # Fallback: use image corners
        h, w = image.shape[:2]
        expected = [
            {'x': 50, 'y': 50},
            {'x': w - 50, 'y': 50},
            {'x': w - 50, 'y': h - 50},
            {'x': 50, 'y': h - 50}
        ]
    
    # Convert to numpy arrays
    src_points = np.float32(fiducials)
    dst_points = np.float32([
        [expected[0]['x'], expected[0]['y']],
        [expected[1]['x'], expected[1]['y']],
        [expected[2]['x'], expected[2]['y']],
        [expected[3]['x'], expected[3]['y']]
    ])
    
    # Compute perspective transform matrix
    matrix = cv2.getPerspectiveTransform(src_points, dst_points)
    
    # Apply transform
    h, w = image.shape[:2]
    aligned = cv2.warpPerspective(image, matrix, (w, h))
    
    return aligned
