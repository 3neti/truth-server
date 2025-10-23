#!/usr/bin/env python3
"""Main OMR appreciation script.

Usage:
    python appreciate.py <image_path> <template_path> [--threshold THRESHOLD]
"""

import sys
import argparse
import cv2
from utils import load_template, output_json
from image_aligner import detect_fiducials, align_image
from mark_detector import detect_marks


def main():
    """Main entry point for OMR appreciation."""
    parser = argparse.ArgumentParser(
        description='Appreciate OMR marks on scanned ballot images'
    )
    parser.add_argument('image', help='Path to scanned ballot image')
    parser.add_argument('template', help='Path to template JSON file')
    parser.add_argument('--threshold', '-t', type=float, default=0.3,
                       help='Fill threshold (0.0 to 1.0, default: 0.3)')
    
    args = parser.parse_args()
    
    image_path = args.image
    template_path = args.template
    threshold = args.threshold
    
    # Load template
    try:
        template = load_template(template_path)
    except Exception as e:
        print(f"Error loading template: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Load image
    try:
        image = cv2.imread(image_path)
        if image is None:
            raise ValueError(f"Could not load image: {image_path}")
    except Exception as e:
        print(f"Error loading image: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Detect fiducials
    fiducials = detect_fiducials(image, template)
    if fiducials is None:
        print("Error: Could not detect 4 fiducial markers", file=sys.stderr)
        sys.exit(1)
    
    # Align image
    try:
        aligned_image = align_image(image, fiducials, template)
    except Exception as e:
        print(f"Error aligning image: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Detect marks
    try:
        zones = template.get('zones', [])
        results = detect_marks(aligned_image, zones, threshold=threshold)
    except Exception as e:
        print(f"Error detecting marks: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Prepare output
    output = {
        'document_id': template.get('document_id', ''),
        'template_id': template.get('template_id', ''),
        'results': results
    }
    
    # Output JSON
    output_json(output)


if __name__ == '__main__':
    main()
