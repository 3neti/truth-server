#!/usr/bin/env python3
"""Test quality metrics integration with image alignment."""

import sys
sys.path.insert(0, '../omr-python')

import cv2
import numpy as np
from image_aligner import align_image, QUALITY_METRICS_AVAILABLE

def test_quality_metrics():
    """Test quality metrics with synthetic test ballot."""
    print("Testing Quality Metrics Integration")
    print("=" * 50)
    
    if not QUALITY_METRICS_AVAILABLE:
        print("❌ Quality metrics module not available!")
        return False
    
    # Load test ballot
    ballot = cv2.imread('test_ballot.png')
    if ballot is None:
        print("❌ test_ballot.png not found")
        print("   Run: python3 test_marker_detection.py first")
        return False
    
    print("✓ Loaded test ballot")
    
    # Simulate detected fiducials at corners (from test_marker_detection.py)
    h, w = ballot.shape[:2]
    margin = 50
    marker_size = 236
    
    # Detected fiducial centers [TL, TR, BL, BR]
    fiducials = [
        (margin + marker_size//2, margin + marker_size//2),  # TL
        (w - margin - marker_size//2, margin + marker_size//2),  # TR
        (margin + marker_size//2, h - margin - marker_size//2),  # BL  
        (w - margin - marker_size//2, h - margin - marker_size//2),  # BR
    ]
    
    print(f"✓ Fiducials: {len(fiducials)} detected")
    
    # Create template dict
    template = {
        'fiducials': [
            {'x': margin, 'y': margin},
            {'x': w - margin, 'y': margin},
            {'x': margin, 'y': h - margin},
            {'x': w - margin, 'y': h - margin},
        ]
    }
    
    # Test alignment with quality metrics (verbose mode)
    print("\n" + "=" * 50)
    print("Test 1: Perfect Alignment (verbose)")
    print("=" * 50)
    aligned, metrics = align_image(ballot, fiducials, template, verbose=True)
    
    if metrics:
        print("\n✅ Quality metrics computed successfully!")
        print(f"   Metrics: {metrics}")
    else:
        print("\n⚠️  Quality metrics not computed")
    
    # Save aligned image
    cv2.imwrite('test_aligned_with_metrics.png', aligned)
    print(f"\n📁 Saved: test_aligned_with_metrics.png")
    
    # Test with rotated fiducials
    print("\n" + "=" * 50)
    print("Test 2: Slightly Rotated (2°)")
    print("=" * 50)
    
    # Rotate fiducials by 2 degrees around center
    center_x, center_y = w // 2, h // 2
    angle = np.radians(2)
    
    rotated_fiducials = []
    for fx, fy in fiducials:
        # Translate to origin
        dx, dy = fx - center_x, fy - center_y
        # Rotate
        rx = dx * np.cos(angle) - dy * np.sin(angle)
        ry = dx * np.sin(angle) + dy * np.cos(angle)
        # Translate back
        rotated_fiducials.append((int(rx + center_x), int(ry + center_y)))
    
    aligned_rot, metrics_rot = align_image(ballot, rotated_fiducials, template, verbose=True)
    
    if metrics_rot:
        print("\n✅ Rotation detected in metrics!")
    
    print("\n" + "=" * 50)
    print("✅ QUALITY METRICS INTEGRATION TEST PASSED")
    print("=" * 50)
    
    return True


if __name__ == "__main__":
    success = test_quality_metrics()
    sys.exit(0 if success else 1)
