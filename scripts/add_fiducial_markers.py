#!/usr/bin/env python3
"""
Add fiducial markers to ballot images.

This script overlays fiducial markers (black squares or ArUco) onto
the corners of an existing ballot image.

Usage:
    python3 add_fiducial_markers.py <input> <output> [options]

Options:
    --mode MODE          Fiducial type: black_square, aruco (default: black_square)
    --size PIXELS        Marker size in mm (default: 10)
    --dpi DPI            Image DPI for size calculation (default: 300)
    --margin MM          Distance from edge in mm (default: 5)
    --aruco-ids IDS      ArUco marker IDs for TL,TR,BR,BL (default: 101,102,103,104)
    --aruco-dict DICT    ArUco dictionary (default: DICT_6X6_250)

Examples:
    # Add black square markers
    python3 add_fiducial_markers.py filled.png filled_with_markers.png

    # Add ArUco markers
    python3 add_fiducial_markers.py filled.png filled_aruco.png --mode aruco

Requirements:
    pip3 install opencv-python numpy Pillow
"""

import argparse
import sys
from pathlib import Path

try:
    import cv2
    import numpy as np
    from PIL import Image
except ImportError as e:
    print(f"Error: {e}", file=sys.stderr)
    print("Install: pip3 install opencv-python numpy Pillow", file=sys.stderr)
    sys.exit(1)


def parse_args():
    parser = argparse.ArgumentParser(
        description="Add fiducial markers to ballot images"
    )
    parser.add_argument("input", help="Input image path")
    parser.add_argument("output", help="Output image path")
    parser.add_argument(
        "--mode",
        choices=["black_square", "aruco"],
        default="black_square",
        help="Fiducial marker type (default: black_square)"
    )
    parser.add_argument(
        "--size",
        type=float,
        default=10.0,
        help="Marker size in mm (default: 10)"
    )
    parser.add_argument(
        "--dpi",
        type=int,
        default=300,
        help="Image DPI for size calculation (default: 300)"
    )
    parser.add_argument(
        "--margin",
        type=float,
        default=5.0,
        help="Distance from edge in mm (default: 5)"
    )
    parser.add_argument(
        "--aruco-ids",
        default="101,102,103,104",
        help="ArUco marker IDs for TL,TR,BR,BL (default: 101,102,103,104)"
    )
    parser.add_argument(
        "--aruco-dict",
        default="DICT_6X6_250",
        help="ArUco dictionary (default: DICT_6X6_250)"
    )
    return parser.parse_args()


def mm_to_pixels(mm: float, dpi: int) -> int:
    """Convert millimeters to pixels at given DPI."""
    return int((mm / 25.4) * dpi)


def create_black_square_marker(size_px: int) -> np.ndarray:
    """Create a black square marker."""
    marker = np.zeros((size_px, size_px), dtype=np.uint8)
    return marker


def create_aruco_marker(marker_id: int, size_px: int, dictionary_name: str) -> np.ndarray:
    """Create an ArUco marker."""
    if not hasattr(cv2, "aruco"):
        raise RuntimeError("OpenCV ArUco module not found")
    
    try:
        dict_id = getattr(cv2.aruco, dictionary_name)
        dictionary = cv2.aruco.getPredefinedDictionary(dict_id)
    except AttributeError:
        raise ValueError(f"Unknown ArUco dictionary: {dictionary_name}")
    
    marker = cv2.aruco.generateImageMarker(dictionary, marker_id, size_px)
    return marker


def add_fiducial_markers(
    image: np.ndarray,
    mode: str,
    marker_size_px: int,
    margin_px: int,
    aruco_ids: list = None,
    aruco_dict: str = "DICT_6X6_250"
) -> np.ndarray:
    """
    Add fiducial markers to the four corners of an image.
    
    Args:
        image: Input image (BGR or grayscale)
        mode: Marker type ("black_square" or "aruco")
        marker_size_px: Marker size in pixels
        margin_px: Margin from edge in pixels
        aruco_ids: List of 4 ArUco marker IDs for TL, TR, BR, BL
        aruco_dict: ArUco dictionary name
        
    Returns:
        Image with fiducial markers added
    """
    result = image.copy()
    h, w = image.shape[:2]
    
    # Corner positions: TL, TR, BR, BL
    positions = [
        (margin_px, margin_px),                                # Top-left
        (w - margin_px - marker_size_px, margin_px),           # Top-right
        (w - margin_px - marker_size_px, h - margin_px - marker_size_px),  # Bottom-right
        (margin_px, h - margin_px - marker_size_px),           # Bottom-left
    ]
    
    for i, (x, y) in enumerate(positions):
        # Create marker
        if mode == "aruco":
            if not aruco_ids or i >= len(aruco_ids):
                raise ValueError("ArUco mode requires 4 marker IDs")
            marker = create_aruco_marker(aruco_ids[i], marker_size_px, aruco_dict)
        else:  # black_square
            marker = create_black_square_marker(marker_size_px)
        
        # Convert grayscale marker to BGR if needed
        if len(result.shape) == 3 and len(marker.shape) == 2:
            marker = cv2.cvtColor(marker, cv2.COLOR_GRAY2BGR)
        
        # Place marker on image
        result[y:y+marker_size_px, x:x+marker_size_px] = marker
    
    return result


def main():
    args = parse_args()
    
    # Load input image
    try:
        image = cv2.imread(args.input)
        if image is None:
            print(f"Error: Could not load image: {args.input}", file=sys.stderr)
            return 1
    except Exception as e:
        print(f"Error loading image: {e}", file=sys.stderr)
        return 1
    
    # Calculate sizes in pixels
    marker_size_px = mm_to_pixels(args.size, args.dpi)
    margin_px = mm_to_pixels(args.margin, args.dpi)
    
    print(f"Adding {args.mode} fiducial markers...")
    print(f"  Input: {args.input}")
    print(f"  Output: {args.output}")
    print(f"  Image size: {image.shape[1]}x{image.shape[0]} px")
    print(f"  Marker size: {args.size}mm ({marker_size_px}px)")
    print(f"  Margin: {args.margin}mm ({margin_px}px)")
    
    # Parse ArUco IDs if needed
    aruco_ids = None
    if args.mode == "aruco":
        try:
            aruco_ids = [int(x.strip()) for x in args.aruco_ids.split(',')]
            if len(aruco_ids) != 4:
                print("Error: Expected exactly 4 ArUco IDs", file=sys.stderr)
                return 1
            print(f"  ArUco IDs: {aruco_ids} (TL, TR, BR, BL)")
            print(f"  ArUco dict: {args.aruco_dict}")
        except ValueError:
            print(f"Error: Invalid ArUco IDs: {args.aruco_ids}", file=sys.stderr)
            return 1
    
    # Add markers
    try:
        result = add_fiducial_markers(
            image,
            args.mode,
            marker_size_px,
            margin_px,
            aruco_ids,
            args.aruco_dict
        )
    except Exception as e:
        print(f"Error adding markers: {e}", file=sys.stderr)
        return 1
    
    # Save output
    try:
        cv2.imwrite(args.output, result)
        print(f"✓ Saved: {args.output}")
    except Exception as e:
        print(f"Error saving image: {e}", file=sys.stderr)
        return 1
    
    return 0


if __name__ == "__main__":
    sys.exit(main())
