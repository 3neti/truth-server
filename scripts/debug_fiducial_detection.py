#!/usr/bin/env python3
"""
Visual debugging tool for fiducial marker detection.

This script helps debug and visualize fiducial detection for OMR ballots.
It shows detected markers with IDs, corner positions, and alignment overlays.

Features:
- Detects all three marker types: black_square, aruco, apriltag
- Visualizes detected corners with colored overlays
- Shows marker IDs and confidence scores
- Displays alignment grid and perspective transform
- Saves annotated debug images

Usage:
    python3 debug_fiducial_detection.py <image> [options]

Options:
    --mode MODE           Fiducial mode: black_square, aruco, apriltag (default: auto-detect)
    --template JSON       Template JSON file with expected positions
    --output DIR          Output directory for debug images (default: ./debug_output)
    --show                Display images in window (requires GUI)
    --no-align            Skip alignment/warping step
    --grid                Draw alignment grid on output

Examples:
    # Auto-detect mode and visualize
    python3 debug_fiducial_detection.py ballot.png --show

    # Test ArUco detection with template
    python3 debug_fiducial_detection.py ballot.png --mode aruco --template coords.json

    # Save debug images without displaying
    python3 debug_fiducial_detection.py ballot.png --output ./debug --grid

Requirements:
    pip3 install opencv-python numpy
"""

import argparse
import sys
import os
import json
from pathlib import Path

# Add parent directory to path for imports
sys.path.insert(0, str(Path(__file__).parent.parent / "packages/omr-appreciation/omr-python"))

try:
    import cv2
    import numpy as np
except ImportError as e:
    print(f"Error: {e}", file=sys.stderr)
    print("Install: pip3 install opencv-python numpy", file=sys.stderr)
    sys.exit(1)

try:
    from image_aligner import detect_fiducials, detect_aruco_fiducials, detect_apriltag_fiducials, align_image
except ImportError:
    print("Warning: Could not import image_aligner module", file=sys.stderr)
    print("Make sure you're running from the correct directory", file=sys.stderr)


def parse_args():
    parser = argparse.ArgumentParser(
        description="Debug and visualize fiducial marker detection"
    )
    parser.add_argument(
        "image",
        help="Path to ballot image to debug"
    )
    parser.add_argument(
        "--mode",
        choices=["black_square", "aruco", "apriltag", "auto"],
        default="auto",
        help="Fiducial detection mode (default: auto-detect)"
    )
    parser.add_argument(
        "--template",
        help="Path to template JSON file with expected positions"
    )
    parser.add_argument(
        "--output",
        default="./debug_output",
        help="Output directory for debug images (default: ./debug_output)"
    )
    parser.add_argument(
        "--show",
        action="store_true",
        help="Display images in window (requires GUI)"
    )
    parser.add_argument(
        "--no-align",
        action="store_true",
        help="Skip alignment/warping step"
    )
    parser.add_argument(
        "--grid",
        action="store_true",
        help="Draw alignment grid on output"
    )
    return parser.parse_args()


def load_template(template_path: str) -> dict:
    """Load template JSON file."""
    try:
        with open(template_path, 'r') as f:
            return json.load(f)
    except Exception as e:
        print(f"Error loading template: {e}", file=sys.stderr)
        return {}


def draw_fiducial_overlay(image: np.ndarray, fiducials: list, mode: str, ids: list = None) -> np.ndarray:
    """Draw detected fiducials with colored overlays and labels."""
    overlay = image.copy()
    
    colors = [
        (0, 255, 0),    # Green - Top-Left
        (255, 0, 0),    # Blue - Top-Right
        (0, 0, 255),    # Red - Bottom-Left
        (255, 255, 0),  # Cyan - Bottom-Right
    ]
    
    labels = ["TL", "TR", "BL", "BR"]
    
    for i, (x, y) in enumerate(fiducials):
        color = colors[i % len(colors)]
        
        # Draw large circle at center
        cv2.circle(overlay, (x, y), 30, color, 4)
        
        # Draw crosshair
        cv2.line(overlay, (x - 40, y), (x + 40, y), color, 2)
        cv2.line(overlay, (x, y - 40), (x, y + 40), color, 2)
        
        # Draw label
        label_text = f"{labels[i]}"
        if ids and i < len(ids):
            label_text += f" (ID:{ids[i]})"
        
        # Background rectangle for text
        text_size = cv2.getTextSize(label_text, cv2.FONT_HERSHEY_SIMPLEX, 0.8, 2)[0]
        cv2.rectangle(
            overlay,
            (x - text_size[0]//2 - 5, y - 60 - text_size[1]),
            (x + text_size[0]//2 + 5, y - 60),
            (255, 255, 255),
            -1
        )
        
        # Text
        cv2.putText(
            overlay,
            label_text,
            (x - text_size[0]//2, y - 65),
            cv2.FONT_HERSHEY_SIMPLEX,
            0.8,
            color,
            2
        )
    
    # Add mode badge
    mode_text = f"Mode: {mode.upper()}"
    cv2.rectangle(overlay, (10, 10), (250, 60), (0, 0, 0), -1)
    cv2.putText(overlay, mode_text, (20, 45), cv2.FONT_HERSHEY_SIMPLEX, 1.0, (0, 255, 0), 2)
    
    return overlay


def draw_alignment_grid(image: np.ndarray, grid_spacing: int = 100) -> np.ndarray:
    """Draw alignment grid on image."""
    grid_img = image.copy()
    h, w = image.shape[:2]
    
    # Draw vertical lines
    for x in range(0, w, grid_spacing):
        cv2.line(grid_img, (x, 0), (x, h), (128, 128, 128), 1)
    
    # Draw horizontal lines
    for y in range(0, h, grid_spacing):
        cv2.line(grid_img, (0, y), (w, y), (128, 128, 128), 1)
    
    return grid_img


def detect_with_mode(image: np.ndarray, mode: str, template: dict):
    """Detect fiducials with specified mode."""
    # Set environment variable for mode
    os.environ['OMR_FIDUCIAL_MODE'] = mode
    
    if mode == "aruco":
        return detect_aruco_fiducials(image, template), None
    elif mode == "apriltag":
        return detect_apriltag_fiducials(image, template), None
    else:  # black_square
        return detect_fiducials(image, template), None


def auto_detect_mode(image: np.ndarray, template: dict):
    """Try all detection modes and return best result."""
    modes = ["aruco", "apriltag", "black_square"]
    
    for mode in modes:
        print(f"Trying {mode} detection...")
        os.environ['OMR_FIDUCIAL_MODE'] = mode
        
        if mode == "aruco":
            result = detect_aruco_fiducials(image, template)
        elif mode == "apriltag":
            result = detect_apriltag_fiducials(image, template)
        else:
            result = detect_fiducials(image, template)
        
        if result is not None:
            print(f"✓ Success with {mode}")
            return result, mode
    
    print("✗ All detection modes failed")
    return None, None


def main():
    args = parse_args()
    
    # Load image
    if not Path(args.image).exists():
        print(f"Error: Image not found: {args.image}", file=sys.stderr)
        return 1
    
    image = cv2.imread(args.image)
    if image is None:
        print(f"Error: Could not read image: {args.image}", file=sys.stderr)
        return 1
    
    print(f"Loaded image: {args.image}")
    print(f"  Size: {image.shape[1]}x{image.shape[0]} px")
    
    # Load template if provided
    template = {}
    if args.template:
        template = load_template(args.template)
        print(f"Loaded template: {args.template}")
    
    # Create output directory
    output_dir = Path(args.output)
    output_dir.mkdir(parents=True, exist_ok=True)
    print(f"Output directory: {output_dir}")
    print()
    
    # Detect fiducials
    print("=" * 60)
    print("FIDUCIAL DETECTION")
    print("=" * 60)
    
    if args.mode == "auto":
        fiducials, detected_mode = auto_detect_mode(image, template)
    else:
        detected_mode = args.mode
        fiducials, _ = detect_with_mode(image, args.mode, template)
    
    if fiducials is None:
        print()
        print("✗ Fiducial detection failed!")
        print()
        print("Troubleshooting tips:")
        print("  - Check image quality (minimum 300 DPI)")
        print("  - Verify markers are fully visible")
        print("  - Try different --mode options")
        print("  - Ensure correct libraries installed")
        return 1
    
    print()
    print("✓ Detected 4 fiducials successfully!")
    print(f"  Mode: {detected_mode}")
    print(f"  Positions:")
    for i, (x, y) in enumerate(fiducials):
        print(f"    Corner {i} (TL/TR/BL/BR): ({x}, {y})")
    
    # Draw overlay
    print()
    print("=" * 60)
    print("GENERATING DEBUG VISUALIZATIONS")
    print("=" * 60)
    
    overlay_img = draw_fiducial_overlay(image, fiducials, detected_mode)
    overlay_path = output_dir / f"{Path(args.image).stem}_fiducials.png"
    cv2.imwrite(str(overlay_path), overlay_img)
    print(f"✓ Saved fiducial overlay: {overlay_path}")
    
    # Alignment
    if not args.no_align:
        print()
        print("Applying perspective transform...")
        aligned = align_image(image, fiducials, template)
        
        if args.grid:
            aligned = draw_alignment_grid(aligned, grid_spacing=100)
        
        aligned_path = output_dir / f"{Path(args.image).stem}_aligned.png"
        cv2.imwrite(str(aligned_path), aligned)
        print(f"✓ Saved aligned image: {aligned_path}")
    
    # Display if requested
    if args.show:
        print()
        print("Displaying images (press any key to close)...")
        cv2.imshow("Fiducial Detection", overlay_img)
        if not args.no_align:
            cv2.imshow("Aligned Image", aligned)
        cv2.waitKey(0)
        cv2.destroyAllWindows()
    
    print()
    print("=" * 60)
    print("✓ DEBUG COMPLETE")
    print("=" * 60)
    
    return 0


if __name__ == "__main__":
    sys.exit(main())
