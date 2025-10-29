#!/usr/bin/env python3
"""Quick quality metrics test for a single fixture.

Tests quality metrics on synthetic ballot fixtures without running full OMR appreciation.
Simulates perfect fiducial detection and computes alignment quality metrics.

Usage:
    python3 test_quality_on_fixture.py <image_path>
"""
import sys
import cv2
import numpy as np

# Import quality metrics module
try:
    from quality_metrics import (
        compute_quality_metrics,
        check_quality_thresholds,
        format_quality_report
    )
    QUALITY_METRICS_AVAILABLE = True
except ImportError:
    print("ERROR: quality_metrics module not available")
    sys.exit(1)


def test_fixture_quality(image_path: str) -> int:
    """Test quality metrics on a fixture image.
    
    Args:
        image_path: Path to fixture image
        
    Returns:
        Exit code (0=success, 1=error)
    """
    # Load image
    image = cv2.imread(image_path)
    
    if image is None:
        print(f"ERROR: Failed to load image: {image_path}")
        return 1
    
    h, w = image.shape[:2]
    print(f"Loaded: {image_path}")
    print(f"Size: {w}x{h}")
    print()
    
    # Detect actual ArUco markers in the image
    # This will detect the markers in their rotated/distorted positions
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    try:
        aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)
        aruco_params = cv2.aruco.DetectorParameters()
        detector = cv2.aruco.ArucoDetector(aruco_dict, aruco_params)
        corners, ids, _ = detector.detectMarkers(gray)
    except Exception as e:
        print(f"ERROR: ArUco detection failed: {e}")
        return 1
    
    if ids is None or len(ids) < 4:
        print(f"ERROR: Need 4 ArUco markers, found {len(ids) if ids is not None else 0}")
        return 1
    
    # Map detected markers to corner positions [TL=101, TR=102, BR=103, BL=104]
    corner_ids = [101, 102, 103, 104]  # TL, TR, BR, BL
    detected = {}
    
    for i, marker_id in enumerate(ids.flatten()):
        if marker_id in corner_ids:
            # Get center of marker
            corner = corners[i][0]
            cx = np.mean(corner[:, 0])
            cy = np.mean(corner[:, 1])
            detected[marker_id] = [cx, cy]
    
    if len(detected) < 4:
        print(f"ERROR: Need all 4 corner markers (101-104), found {list(detected.keys())}")
        return 1
    
    # Source points (detected marker centers in distorted image) [TL, TR, BR, BL]
    src_points = np.array([
        detected[101],  # TL
        detected[102],  # TR
        detected[103],  # BR
        detected[104],  # BL
    ], dtype=np.float32)
    
    print(f"Detected markers at:")
    print(f"  TL (101): {detected[101]}")
    print(f"  TR (102): {detected[102]}")
    print(f"  BR (103): {detected[103]}")
    print(f"  BL (104): {detected[104]}")
    print()
    
    # Expected destination (ideal corners where markers should be)
    margin = 50
    marker_size = 236
    dst_points = np.array([
        [margin + marker_size//2, margin + marker_size//2],  # TL
        [w - margin - marker_size//2, margin + marker_size//2],  # TR
        [w - margin - marker_size//2, h - margin - marker_size//2],  # BR
        [margin + marker_size//2, h - margin - marker_size//2],  # BL
    ], dtype=np.float32)
    
    # Compute homography from detected (distorted) to expected (ideal) positions
    H, status = cv2.findHomography(src_points, dst_points, cv2.RANSAC, 5.0)
    
    if H is None:
        print("ERROR: Failed to compute homography")
        return 1
    
    # Compute quality metrics
    try:
        metrics = compute_quality_metrics(src_points, H, w, h)
        verdicts = check_quality_thresholds(metrics)
        
        # Print formatted report
        print(format_quality_report(metrics, verdicts))
        
        # Return exit code based on overall verdict
        if verdicts['overall'] == 'red':
            return 1  # Failed quality gates
        else:
            return 0  # Passed (green or amber acceptable)
            
    except Exception as e:
        print(f"ERROR: Failed to compute quality metrics: {e}")
        import traceback
        traceback.print_exc()
        return 1


if __name__ == "__main__":
    if len(sys.argv) < 2:
        print("Usage: test_quality_on_fixture.py <image_path>")
        sys.exit(1)
    
    image_path = sys.argv[1]
    exit_code = test_fixture_quality(image_path)
    sys.exit(exit_code)
