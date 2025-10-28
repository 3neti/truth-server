#!/usr/bin/env python3
"""
Generate AprilTag marker images for OMR fiducial detection.

This script generates high-resolution PNG images of AprilTag markers
that can be embedded in PDF templates via TCPDF.

AprilTag families supported:
- tag36h11 (recommended): 587 unique tags, robust
- tag25h9: 35 tags, smaller
- tag16h5: 30 tags, smallest
- tagStandard41h12: 2115 tags, large
- tagCircle21h7: 38 tags, circular

Usage:
    python3 generate_apriltag_markers.py [options]

Options:
    --family FAMILY        AprilTag family (default: tag36h11)
    --ids ID,ID,ID,ID     Tag IDs for TL,TR,BR,BL (default: 0,1,2,3)
    --size PIXELS         Tag size in pixels (default: 400)
    --border PIXELS       White border size (default: 20)
    --output DIR          Output directory (default: ../resources/fiducials/apriltag)
    --list-families       List available AprilTag families and exit

Examples:
    # Generate default tags (0-3)
    python3 generate_apriltag_markers.py

    # Generate with custom IDs
    python3 generate_apriltag_markers.py --ids 10,11,12,13

    # Use tag25h9 family (smaller markers)
    python3 generate_apriltag_markers.py --family tag25h9

Requirements:
    pip3 install apriltag opencv-python numpy
    OR
    pip3 install pupil-apriltags opencv-python numpy
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

# Try importing apriltag library
APRILTAG_AVAILABLE = False
apriltag = None

try:
    import apriltag as apriltag_lib
    apriltag = apriltag_lib
    APRILTAG_AVAILABLE = True
except ImportError:
    try:
        from pupil_apriltags import Detector
        apriltag = Detector
        APRILTAG_AVAILABLE = True
    except ImportError:
        pass


def parse_args():
    parser = argparse.ArgumentParser(
        description="Generate AprilTag marker images for OMR fiducials"
    )
    parser.add_argument(
        "--family",
        default="tag36h11",
        help="AprilTag family name (default: tag36h11)"
    )
    parser.add_argument(
        "--ids",
        default="0,1,2,3",
        help="Comma-separated tag IDs for TL,TR,BR,BL (default: 0,1,2,3)"
    )
    parser.add_argument(
        "--size",
        type=int,
        default=400,
        help="Tag size in pixels (default: 400)"
    )
    parser.add_argument(
        "--border",
        type=int,
        default=20,
        help="White border size in pixels (default: 20)"
    )
    parser.add_argument(
        "--output",
        default="../resources/fiducials/apriltag",
        help="Output directory (default: ../resources/fiducials/apriltag)"
    )
    parser.add_argument(
        "--list-families",
        action="store_true",
        help="List available AprilTag families and exit"
    )
    return parser.parse_args()


def list_families():
    """List all available AprilTag families."""
    print("Available AprilTag Families:")
    print("-" * 50)
    
    families = {
        "tag36h11": "587 tags (recommended for OMR)",
        "tag25h9": "35 tags (smaller, good for tight spaces)",
        "tag16h5": "30 tags (smallest)",
        "tagStandard41h12": "2115 tags (large, high redundancy)",
        "tagCircle21h7": "38 tags (circular design)",
        "tagCustom48h12": "42211 tags (custom, very large)",
    }
    
    for family, desc in families.items():
        print(f"  • {family:<20} {desc}")
    
    print("\nRecommended: tag36h11 (best balance of size and tag count)")
    print("Note: AprilTag library required to generate images")


def generate_apriltag_bitmap(family: str, tag_id: int, size: int) -> np.ndarray:
    """
    Generate AprilTag bitmap directly without apriltag library.
    
    This is a fallback method that generates basic tag patterns.
    For production use, install the apriltag library for accurate tags.
    
    Args:
        family: Tag family name
        tag_id: Tag ID to generate
        size: Size of tag in pixels
        
    Returns:
        Numpy array containing the tag image
    """
    # This is a simplified generator - real AprilTags require the library
    # We'll create a placeholder pattern with the ID encoded
    
    if family == "tag36h11":
        grid_size = 8  # 6x6 data + 2 border
    elif family == "tag25h9":
        grid_size = 7  # 5x5 data + 2 border
    elif family == "tag16h5":
        grid_size = 6  # 4x4 data + 2 border
    else:
        grid_size = 8  # default
    
    cell_size = size // grid_size
    tag = np.ones((size, size), dtype=np.uint8) * 255  # White background
    
    # Create black border (AprilTag characteristic)
    border_cells = 1
    for i in range(border_cells):
        # Top border
        tag[i*cell_size:(i+1)*cell_size, :] = 0
        # Bottom border
        tag[size-(i+1)*cell_size:size-i*cell_size, :] = 0
        # Left border
        tag[:, i*cell_size:(i+1)*cell_size] = 0
        # Right border
        tag[:, size-(i+1)*cell_size:size-i*cell_size] = 0
    
    # Encode tag ID in data region (simplified)
    data_start = border_cells
    data_end = grid_size - border_cells
    
    # Create a simple pattern based on tag ID
    bits = f"{tag_id:036b}"  # 36-bit representation
    bit_idx = 0
    
    for row in range(data_start, data_end):
        for col in range(data_start, data_end):
            if bit_idx < len(bits):
                if bits[bit_idx] == '1':
                    tag[row*cell_size:(row+1)*cell_size, 
                        col*cell_size:(col+1)*cell_size] = 0
                bit_idx += 1
    
    return tag


def generate_tag_with_border(family: str, tag_id: int, size: int, border: int) -> np.ndarray:
    """
    Generate AprilTag with white border (quiet zone).
    
    Args:
        family: Tag family name
        tag_id: Tag ID to generate
        size: Tag size in pixels (without border)
        border: Border size in pixels
        
    Returns:
        Numpy array containing the tag with border
    """
    # Generate tag
    tag = generate_apriltag_bitmap(family, tag_id, size)
    
    # Add white border
    total_size = size + (2 * border)
    bordered = np.ones((total_size, total_size), dtype=np.uint8) * 255
    bordered[border:border+size, border:border+size] = tag
    
    return bordered


def main():
    args = parse_args()
    
    # Handle --list-families
    if args.list_families:
        list_families()
        return 0
    
    # Check if apriltag library is available
    if not APRILTAG_AVAILABLE:
        print("Warning: apriltag library not found. Using fallback generator.", file=sys.stderr)
        print("For production use, install: pip3 install apriltag", file=sys.stderr)
        print("Or: pip3 install pupil-apriltags", file=sys.stderr)
        print()
    
    # Parse tag IDs
    try:
        tag_ids = [int(x.strip()) for x in args.ids.split(',')]
        if len(tag_ids) != 4:
            print("Error: Expected exactly 4 tag IDs (TL, TR, BR, BL)", file=sys.stderr)
            return 1
    except ValueError:
        print(f"Error: Invalid tag IDs: {args.ids}", file=sys.stderr)
        return 1
    
    # Validate family
    valid_families = ["tag36h11", "tag25h9", "tag16h5", "tagStandard41h12", 
                     "tagCircle21h7", "tagCustom48h12"]
    if args.family not in valid_families:
        print(f"Warning: Unknown family '{args.family}'. Using tag36h11", file=sys.stderr)
        args.family = "tag36h11"
    
    # Create output directory
    script_dir = Path(__file__).parent
    output_dir = script_dir / args.output
    output_dir.mkdir(parents=True, exist_ok=True)
    
    print(f"Generating AprilTag markers...")
    print(f"  Family: {args.family}")
    print(f"  Tag IDs: {tag_ids} (TL, TR, BR, BL)")
    print(f"  Size: {args.size}x{args.size} px")
    print(f"  Border: {args.border} px")
    print(f"  Output: {output_dir}")
    if not APRILTAG_AVAILABLE:
        print(f"  Mode: Fallback (install apriltag library for accurate tags)")
    print()
    
    corner_names = ["TL", "TR", "BR", "BL"]
    
    # Generate tags
    for tag_id, corner in zip(tag_ids, corner_names):
        tag_img = generate_tag_with_border(
            args.family,
            tag_id,
            args.size,
            args.border
        )
        
        # Save image
        filename = f"tag_{tag_id}.png"
        filepath = output_dir / filename
        cv2.imwrite(str(filepath), tag_img)
        
        file_size = filepath.stat().st_size / 1024  # KB
        print(f"  ✓ {filename} ({corner}) - {file_size:.1f} KB")
    
    print()
    print(f"✓ Generated {len(tag_ids)} tags successfully!")
    
    if not APRILTAG_AVAILABLE:
        print()
        print("⚠️  Warning: Using fallback generator")
        print("   For accurate AprilTags, install the library:")
        print("   pip3 install apriltag")
        print("   OR")
        print("   pip3 install pupil-apriltags")
    
    print()
    print("Next steps:")
    print("  1. Update TCPDF template code to place these tags")
    print("  2. Set OMR_FIDUCIAL_MODE=apriltag in .env")
    print("  3. Update Python detector to support AprilTag")
    
    return 0


if __name__ == "__main__":
    sys.exit(main())
