#!/usr/bin/env python3
"""
Quick test script to validate ArUco marker detection without webcam.
Creates a simple test image with the 4 ArUco markers and tests detection.
"""
import cv2
import numpy as np
import sys

def create_test_ballot(width=2480, height=3508):
    """Create a test ballot with 4 ArUco markers at corners."""
    # Create white canvas (A4 @ 300 DPI)
    canvas = np.ones((height, width, 3), dtype=np.uint8) * 255
    
    # Load marker images
    markers = {}
    marker_paths = {
        101: '../../../resources/fiducials/aruco/marker_101.png',  # TL
        102: '../../../resources/fiducials/aruco/marker_102.png',  # TR
        103: '../../../resources/fiducials/aruco/marker_103.png',  # BR
        104: '../../../resources/fiducials/aruco/marker_104.png',  # BL
    }
    
    for marker_id, path in marker_paths.items():
        try:
            img = cv2.imread(path)
            if img is None:
                print(f"❌ Failed to load {path}")
                return None
            # Resize to reasonable size (20mm @ 300 DPI = 236px)
            markers[marker_id] = cv2.resize(img, (236, 236))
            print(f"✓ Loaded marker {marker_id}")
        except Exception as e:
            print(f"❌ Error loading marker {marker_id}: {e}")
            return None
    
    # Place markers at corners (with 50px margin)
    margin = 50
    marker_size = 236
    
    # Top-left (101)
    canvas[margin:margin+marker_size, margin:margin+marker_size] = markers[101]
    
    # Top-right (102)
    canvas[margin:margin+marker_size, width-margin-marker_size:width-margin] = markers[102]
    
    # Bottom-right (103)
    canvas[height-margin-marker_size:height-margin, width-margin-marker_size:width-margin] = markers[103]
    
    # Bottom-left (104)
    canvas[height-margin-marker_size:height-margin, margin:margin+marker_size] = markers[104]
    
    return canvas


def test_detection(image):
    """Test ArUco marker detection on the image."""
    print("\n📷 Testing ArUco detection...")
    
    # Get ArUco dictionary
    aruco_dict = cv2.aruco.getPredefinedDictionary(cv2.aruco.DICT_6X6_250)
    params = cv2.aruco.DetectorParameters()
    detector = cv2.aruco.ArucoDetector(aruco_dict, params)
    
    # Convert to grayscale
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    # Detect markers
    corners, ids, rejected = detector.detectMarkers(gray)
    
    if ids is None or len(ids) == 0:
        print("❌ No markers detected!")
        return False
    
    print(f"✓ Detected {len(ids)} markers")
    detected_ids = sorted([int(i[0]) for i in ids])
    print(f"  IDs: {detected_ids}")
    
    # Check if we have all 4 expected markers
    expected_ids = [101, 102, 103, 104]
    if set(detected_ids) == set(expected_ids):
        print("✅ All 4 expected markers detected!")
        
        # Draw detections
        result = image.copy()
        cv2.aruco.drawDetectedMarkers(result, corners, ids)
        
        # Add labels
        for i, marker_id in enumerate(ids):
            corner = corners[i][0]
            center = corner.mean(axis=0).astype(int)
            cv2.putText(result, str(int(marker_id[0])), tuple(center),
                       cv2.FONT_HERSHEY_SIMPLEX, 2, (0, 0, 255), 3)
        
        # Save result
        cv2.imwrite('test_detection_result.png', result)
        print("📁 Saved detection result to: test_detection_result.png")
        return True
    else:
        print(f"⚠️  Expected markers {expected_ids}, got {detected_ids}")
        return False


def main():
    print("🎯 ArUco Marker Detection Test")
    print("=" * 50)
    
    # Create test ballot
    print("\n🖼️  Creating test ballot...")
    ballot = create_test_ballot()
    
    if ballot is None:
        print("\n❌ Failed to create test ballot")
        print("   Make sure marker images exist in resources/fiducials/aruco/")
        sys.exit(1)
    
    print("✓ Test ballot created (2480x3508)")
    
    # Save test ballot
    cv2.imwrite('test_ballot.png', ballot)
    print("📁 Saved test ballot to: test_ballot.png")
    
    # Test detection
    success = test_detection(ballot)
    
    print("\n" + "=" * 50)
    if success:
        print("✅ DETECTION TEST PASSED")
        print("\nNext steps:")
        print("1. Open test_ballot.png to see the test image")
        print("2. Open test_detection_result.png to see detected markers")
        print("3. Run live demo with your webcam:")
        print("   python3 live_fiducial_appreciation.py --mode aruco --show-warp")
        sys.exit(0)
    else:
        print("❌ DETECTION TEST FAILED")
        print("\nTroubleshooting:")
        print("1. Verify marker images exist: ls ../../../resources/fiducials/aruco/")
        print("2. Check OpenCV version: python3 -c 'import cv2; print(cv2.__version__)'")
        print("3. Verify ArUco module: python3 -c 'import cv2; print(cv2.aruco)'")
        sys.exit(1)


if __name__ == "__main__":
    main()
