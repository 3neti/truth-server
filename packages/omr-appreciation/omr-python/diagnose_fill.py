#!/usr/bin/env python3
"""Diagnose fill ratio calculation."""

import sys
import cv2
import numpy as np
from utils import load_template, get_roi_coordinates
from image_aligner import detect_fiducials, align_image

if len(sys.argv) != 4:
    print("Usage: python diagnose_fill.py <image> <template> <zone_index>")
    sys.exit(1)

image = cv2.imread(sys.argv[1])
template = load_template(sys.argv[2])
zone_idx = int(sys.argv[3])

zone = template['zones'][zone_idx]
x, y, w, h = get_roi_coordinates(zone)

print(f"Diagnosing Zone {zone_idx}: {zone['id']}")
print(f"Position: ({x}, {y}) Size: {w}x{h}\n")

# Before alignment
print("=== BEFORE ALIGNMENT (Raw image) ===")
roi_before = image[y:y+h, x:x+w]
gray_before = cv2.cvtColor(roi_before, cv2.COLOR_BGR2GRAY)

print(f"ROI shape: {gray_before.shape}")
print(f"Mean pixel value: {np.mean(gray_before):.1f}")
print(f"Min/Max: {np.min(gray_before)} / {np.max(gray_before)}")

# Test different thresholds
for thresh in [50, 100, 127, 150, 200]:
    _, binary = cv2.threshold(gray_before, thresh, 255, cv2.THRESH_BINARY_INV)
    dark_ratio = np.count_nonzero(binary) / binary.size
    print(f"Threshold {thresh:3d}: {dark_ratio:.3f} dark pixels")

print()

# After alignment
print("=== AFTER ALIGNMENT (Perspective transform) ===")
fiducials = detect_fiducials(image, template)
if fiducials:
    print(f"Fiducials detected at: {fiducials}")
    aligned = align_image(image, fiducials, template)
    
    roi_after = aligned[y:y+h, x:x+w]
    gray_after = cv2.cvtColor(roi_after, cv2.COLOR_BGR2GRAY)
    
    print(f"ROI shape: {gray_after.shape}")
    print(f"Mean pixel value: {np.mean(gray_after):.1f}")
    print(f"Min/Max: {np.min(gray_after)} / {np.max(gray_after)}")
    
    # Test different thresholds
    for thresh in [50, 100, 127, 150, 200]:
        _, binary = cv2.threshold(gray_after, thresh, 255, cv2.THRESH_BINARY_INV)
        dark_ratio = np.count_nonzero(binary) / binary.size
        print(f"Threshold {thresh:3d}: {dark_ratio:.3f} dark pixels")
    
    # Show histogram
    print("\nPixel value distribution (after alignment):")
    hist, bins = np.histogram(gray_after.flatten(), bins=10, range=(0, 256))
    for i, count in enumerate(hist):
        start = int(bins[i])
        end = int(bins[i+1])
        bar = '█' * int(count / max(hist) * 40)
        print(f"  {start:3d}-{end:3d}: {bar} ({count})")
else:
    print("ERROR: Could not detect fiducials")
