#!/usr/bin/env python3
"""
Generate ArUco marker images for OMR fiducial detection.

This script generates high-resolution PNG images of ArUco markers
that can be embedded in PDF templates via TCPDF.

Usage:
    python3 generate_aruco_markers.py [options]

Options:
    --dict DICT_NAME        ArUco dictionary (default: DICT_6X6_250)
    --ids ID,ID,ID,ID      Marker IDs for TL,TR,BR,BL (default: 101,102,103,104)
    --size PIXELS          Marker size in pixels (default: 400)
    --output DIR           Output directory (default: ../resources/fiducials/aruco)
    --border PIXELS        White border size (default: 20)

Examples:
    # Generate default markers (101-104)
    python3 generate_aruco_markers.py

    # Generate with custom IDs
    python3 generate_aruco_markers.py --ids 10,11,12,13

    # Generate larger markers
    python3 generate_aruco_markers.py --size 800

Requirements:
    pip3 install opencv-python numpy
"""

import argparse
import sys
import os
from pathlib import Path

try:
    import cv2
    import numpy as np
except ImportError as e:
    print(f"Error: {e}", file=sys.stderr)
    print("Install required packages: pip3 install opencv-python numpy", file=sys.stderr)
    sys.exit(1)


def parse_args():
    parser = argparse.ArgumentParser(
        description="Generate ArUco marker images for OMR fiducials"
    )
    parser.add_argument(
        "--dict",
        dest="dictionary",
        default="DICT_6X6_250",
        help="ArUco dictionary name (default: DICT_6X6_250)"
    )
    parser.add_argument(
        "--ids",
        default="101,102,103,104",
        help="Comma-separated marker IDs for TL,TR,BR,BL (default: 101,102,103,104)"
    )
    parser.add_argument(
        "--size",
        type=int,
        default=400,
        help="Marker size in pixels (default: 400)"
    )
    parser.add_argument(
        "--output",
        default="../resources/fiducials/aruco",
        help="Output directory (default: ../resources/fiducials/aruco)"
    )
    parser.add_argument(
        "--border",
        type=int,
        default=20,
        help="White border size in pixels (default: 20)"
    )
    parser.add_argument(
        "--list-dicts",
        action="store_true",
        help="List available ArUco dictionaries and exit"
    )
    return parser.parse_args()


def list_dictionaries():
    """List all available ArUco dictionaries."""
    print("Available ArUco Dictionaries:")
    print("-" * 40)
    
    dicts = [
        "DICT_4X4_50", "DICT_4X4_100", "DICT_4X4_250", "DICT_4X4_1000",
        "DICT_5X5_50", "DICT_5X5_100", "DICT_5X5_250", "DICT_5X5_1000",
        "DICT_6X6_50", "DICT_6X6_100", "DICT_6X6_250", "DICT_6X6_1000",
        "DICT_7X7_50", "DICT_7X7_100", "DICT_7X7_250", "DICT_7X7_1000",
        "DICT_ARUCO_ORIGINAL",
    ]
    
    for dict_name in dicts:
        if hasattr(cv2.aruco, dict_name):
            print(f"  • {dict_name}")
    
    print("\nRecommended: DICT_6X6_250 (good balance of size and reliability)")
    print("Note: Larger grids (7x7) are more robust but require more space")


def get_aruco_dictionary(name):
    """Get ArUco dictionary by name."""
    if not hasattr(cv2, "aruco"):
        raise RuntimeError("OpenCV ArUco module not found. Install opencv-contrib-python.")
    
    try:
        dict_id = getattr(cv2.aruco, name)
        return cv2.aruco.getPredefinedDictionary(dict_id)
    except AttributeError:
        raise ValueError(f"Unknown ArUco dictionary: {name}. Use --list-dicts to see options.")


def generate_marker(dictionary, marker_id, marker_size, border_size):
    """
    Generate a single ArUco marker with white border.
    
    Args:
        dictionary: ArUco dictionary object
        marker_id: Marker ID to generate
        marker_size: Size of marker in pixels (without border)
        border_size: Size of white border in pixels
        
    Returns:
        numpy array containing the marker image with border
    """
    # Generate marker without border
    marker_img = cv2.aruco.generateImageMarker(dictionary, marker_id, marker_size)
    
    # Add white border (quiet zone)
    total_size = marker_size + (2 * border_size)
    bordered_img = np.ones((total_size, total_size), dtype=np.uint8) * 255
    bordered_img[border_size:border_size+marker_size, border_size:border_size+marker_size] = marker_img
    
    return bordered_img


def main():
    args = parse_args()
    
    # Handle --list-dicts
    if args.list_dicts:
        list_dictionaries()
        return 0
    
    # Parse marker IDs
    try:
        marker_ids = [int(x.strip()) for x in args.ids.split(',')]
        if len(marker_ids) != 4:
            print("Error: Expected exactly 4 marker IDs (TL, TR, BR, BL)", file=sys.stderr)
            return 1
    except ValueError:
        print(f"Error: Invalid marker IDs: {args.ids}", file=sys.stderr)
        return 1
    
    # Get ArUco dictionary
    try:
        dictionary = get_aruco_dictionary(args.dictionary)
    except (RuntimeError, ValueError) as e:
        print(f"Error: {e}", file=sys.stderr)
        return 1
    
    # Create output directory
    script_dir = Path(__file__).parent
    output_dir = script_dir / args.output
    output_dir.mkdir(parents=True, exist_ok=True)
    
    print(f"Generating ArUco markers...")
    print(f"  Dictionary: {args.dictionary}")
    print(f"  Marker IDs: {marker_ids} (TL, TR, BR, BL)")
    print(f"  Size: {args.size}x{args.size} px")
    print(f"  Border: {args.border} px")
    print(f"  Output: {output_dir}")
    print()
    
    corner_names = ["TL", "TR", "BR", "BL"]
    
    # Generate markers
    for marker_id, corner in zip(marker_ids, corner_names):
        marker_img = generate_marker(
            dictionary, 
            marker_id, 
            args.size, 
            args.border
        )
        
        # Save image
        filename = f"marker_{marker_id}.png"
        filepath = output_dir / filename
        cv2.imwrite(str(filepath), marker_img)
        
        file_size = filepath.stat().st_size / 1024  # KB
        print(f"  ✓ {filename} ({corner}) - {file_size:.1f} KB")
    
    print()
    print(f"✓ Generated {len(marker_ids)} markers successfully!")
    print(f"\nNext steps:")
    print(f"  1. Update TCPDF template code to place these markers")
    print(f"  2. Set OMR_FIDUCIAL_MODE=aruco in .env")
    print(f"  3. Update Python detector to support ArUco")
    
    return 0


if __name__ == "__main__":
    sys.exit(main())
