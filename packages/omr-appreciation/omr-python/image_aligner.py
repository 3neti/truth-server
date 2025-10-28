"""Image alignment using fiducial markers."""

import cv2
import numpy as np
import os
from typing import List, Tuple, Optional


def detect_apriltag_fiducials(image: np.ndarray, template: dict) -> Optional[List[Tuple[int, int]]]:
    """Detect AprilTag fiducial markers in the image.
    
    Args:
        image: Input image (BGR)
        template: Template dictionary containing AprilTag config
        
    Returns:
        List of 4 (x, y) coordinates for fiducials [TL, TR, BL, BR], or None if detection fails
    """
    # Get AprilTag configuration
    tag_family = os.getenv('OMR_APRILTAG_FAMILY', 'tag36h11')
    corner_ids = [0, 1, 2, 3]  # TL, TR, BR, BL
    
    try:
        # Try importing apriltag library
        try:
            import apriltag
            detector = apriltag.Detector(apriltag.DetectorOptions(families=tag_family))
        except ImportError:
            try:
                from pupil_apriltags import Detector
                detector = Detector(families=tag_family)
            except ImportError:
                print("AprilTag library not found. Install: pip3 install apriltag")
                return None
        
        # Convert to grayscale
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        
        # Detect tags
        detections = detector.detect(gray)
        
        if len(detections) < 4:
            return None
        
        # Map detected IDs to positions
        detected = {}
        for detection in detections:
            tag_id = detection.tag_id
            if tag_id in corner_ids:
                # Get center of tag
                cx, cy = detection.center
                detected[tag_id] = (int(cx), int(cy))
        
        # Check if we have all 4 corners
        if len(detected) < 4:
            return None
        
        # Return in order: TL, TR, BL, BR
        fiducials = [
            detected.get(corner_ids[0]),  # TL (0)
            detected.get(corner_ids[1]),  # TR (1)
            detected.get(corner_ids[3]),  # BL (3)
            detected.get(corner_ids[2]),  # BR (2)
        ]
        
        # Verify all markers found
        if any(f is None for f in fiducials):
            return None
            
        return fiducials
        
    except Exception as e:
        print(f"AprilTag detection error: {e}")
        return None


def detect_aruco_fiducials(image: np.ndarray, template: dict) -> Optional[List[Tuple[int, int]]]:
    """Detect ArUco fiducial markers in the image.
    
    Args:
        image: Input image (BGR)
        template: Template dictionary containing ArUco config
        
    Returns:
        List of 4 (x, y) coordinates for fiducials [TL, TR, BL, BR], or None if detection fails
    """
    # Get ArUco configuration
    aruco_dict_name = os.getenv('OMR_ARUCO_DICTIONARY', 'DICT_6X6_250')
    corner_ids = [101, 102, 103, 104]  # TL, TR, BR, BL
    
    try:
        # Get ArUco dictionary
        aruco_dict_id = getattr(cv2.aruco, aruco_dict_name)
        aruco_dict = cv2.aruco.getPredefinedDictionary(aruco_dict_id)
        aruco_params = cv2.aruco.DetectorParameters()
        
        # Detect markers
        gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        corners, ids, _ = cv2.aruco.detectMarkers(gray, aruco_dict, parameters=aruco_params)
        
        if ids is None or len(ids) < 4:
            return None
        
        # Map detected IDs to positions
        detected = {}
        for i, marker_id in enumerate(ids.flatten()):
            if marker_id in corner_ids:
                # Get center of marker
                corner = corners[i][0]
                cx = int(np.mean(corner[:, 0]))
                cy = int(np.mean(corner[:, 1]))
                detected[marker_id] = (cx, cy)
        
        # Check if we have all 4 corners
        if len(detected) < 4:
            return None
        
        # Return in order: TL, TR, BL, BR
        fiducials = [
            detected.get(corner_ids[0]),  # TL (101)
            detected.get(corner_ids[1]),  # TR (102)
            detected.get(corner_ids[3]),  # BL (104)
            detected.get(corner_ids[2]),  # BR (103)
        ]
        
        # Verify all markers found
        if any(f is None for f in fiducials):
            return None
            
        return fiducials
        
    except (AttributeError, cv2.error) as e:
        print(f"ArUco detection error: {e}")
        return None


def detect_fiducials(image: np.ndarray, template: dict) -> Optional[List[Tuple[int, int]]]:
    """Detect 4 fiducial markers in the image.
    
    Supports multiple fiducial modes:
    - black_square: Traditional black square detection (default)
    - aruco: ArUco marker detection with unique IDs
    - apriltag: AprilTag marker detection with unique IDs
    
    Args:
        image: Input image (BGR)
        template: Template dictionary containing fiducial positions
        
    Returns:
        List of 4 (x, y) coordinates for fiducials, or None if detection fails
    """
    # Check fiducial mode from environment
    fiducial_mode = os.getenv('OMR_FIDUCIAL_MODE', 'black_square')
    
    # Try AprilTag detection if enabled
    if fiducial_mode == 'apriltag':
        apriltag_result = detect_apriltag_fiducials(image, template)
        if apriltag_result is not None:
            return apriltag_result
        print("AprilTag detection failed, falling back to black square detection")
    
    # Try ArUco detection if enabled
    elif fiducial_mode == 'aruco':
        aruco_result = detect_aruco_fiducials(image, template)
        if aruco_result is not None:
            return aruco_result
        print("ArUco detection failed, falling back to black square detection")
    # Handle both formats: 'fiducials' (array) or 'fiducial' (dict with tl/tr/bl/br)
    expected_fiducials = template.get('fiducials', [])
    
    # If not found, try singular 'fiducial' with our format
    if not expected_fiducials:
        fiducial_dict = template.get('fiducial', {})
        if fiducial_dict:
            # Convert our format to expected array format
            expected_fiducials = [
                fiducial_dict.get('tl', {}),
                fiducial_dict.get('tr', {}),
                fiducial_dict.get('bl', {}),
                fiducial_dict.get('br', {}),
            ]
    
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
    # Convert from mm to pixels: assume 300 DPI (11.81 pixels per mm)
    expected_size_mm = expected_fiducials[0].get('width', 14.17325)
    expected_size = expected_size_mm * (300 / 25.4)  # Convert mm to pixels at 300 DPI
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
    
    # Try singular 'fiducial' format
    if not expected:
        fiducial_dict = template.get('fiducial', {})
        if fiducial_dict:
            expected = [
                fiducial_dict.get('tl', {}),
                fiducial_dict.get('tr', {}),
                fiducial_dict.get('bl', {}),
                fiducial_dict.get('br', {}),
            ]
    
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
    # Convert mm to pixels for our format (300 DPI)
    mm_to_pixels = 300 / 25.4
    src_points = np.float32(fiducials)
    dst_points = np.float32([
        [expected[0].get('x', 0) * mm_to_pixels, expected[0].get('y', 0) * mm_to_pixels],
        [expected[1].get('x', 0) * mm_to_pixels, expected[1].get('y', 0) * mm_to_pixels],
        [expected[2].get('x', 0) * mm_to_pixels, expected[2].get('y', 0) * mm_to_pixels],
        [expected[3].get('x', 0) * mm_to_pixels, expected[3].get('y', 0) * mm_to_pixels]
    ])
    
    # Compute perspective transform matrix
    matrix = cv2.getPerspectiveTransform(src_points, dst_points)
    
    # Apply transform
    h, w = image.shape[:2]
    aligned = cv2.warpPerspective(image, matrix, (w, h))
    
    return aligned
