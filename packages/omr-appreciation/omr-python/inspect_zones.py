#!/usr/bin/env python3
"""Inspect what's actually in the marked zones."""

import sys
import cv2
import numpy as np
from utils import load_template, get_roi_coordinates

if len(sys.argv) != 3:
    print("Usage: python inspect_zones.py <image> <template>")
    sys.exit(1)

image = cv2.imread(sys.argv[1])
template = load_template(sys.argv[2])

print(f"Image size: {image.shape}")
print(f"\nInspecting zones:\n")

for i, zone in enumerate(template['zones']):
    x, y, w, h = get_roi_coordinates(zone)
    roi = image[y:y+h, x:x+w]
    
    # Convert to grayscale
    if len(roi.shape) == 3:
        gray_roi = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
    else:
        gray_roi = roi
    
    # Calculate statistics
    mean_val = np.mean(gray_roi)
    min_val = np.min(gray_roi)
    dark_pixels = np.sum(gray_roi < 128)
    total_pixels = gray_roi.size
    dark_ratio = dark_pixels / total_pixels if total_pixels > 0 else 0
    
    print(f"Zone {i}: {zone['id']}")
    print(f"  Position: ({x}, {y}) Size: {w}x{h}")
    print(f"  Mean brightness: {mean_val:.1f}")
    print(f"  Min brightness: {min_val}")
    print(f"  Dark pixel ratio: {dark_ratio:.3f}")
    print()
