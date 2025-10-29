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
    parser.add_argument('--no-align', action='store_true',
                       help='Skip fiducial alignment (for perfect test images)')
    
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
    
    # Align image based on fiducials (unless disabled)
    if args.no_align:
        # Skip alignment for perfect test images
        aligned_image = image
    else:
        # Detect fiducials
        fiducials = detect_fiducials(image, template)
        if fiducials is None:
            print("Error: Could not detect 4 fiducial markers", file=sys.stderr)
            sys.exit(1)
        
        # Align image
        try:
            aligned_image, quality_metrics = align_image(image, fiducials, template)
        except Exception as e:
            print(f"Error aligning image: {e}", file=sys.stderr)
            sys.exit(1)
    
    # Detect marks
    try:
        # Handle both 'zones' (array) and 'bubble' (dict) formats
        zones = template.get('zones', [])
        if not zones:
            # Convert bubble dict to zones array
            # Coordinates in JSON are in millimeters, convert to pixels at 300 DPI
            mm_to_pixels = 300 / 25.4  # 11.811 pixels per mm
            
            bubble_dict = template.get('bubble', {})
            zones = []
            for bubble_id, bubble_data in bubble_dict.items():
                # Parse bubble_id like "PRESIDENT_LD_001" into contest and code
                parts = bubble_id.rsplit('_', 1)
                contest = parts[0] if len(parts) > 1 else ''
                code = parts[1] if len(parts) > 1 else bubble_id
                
                # Convert from mm to pixels
                # Prefer center coordinates if available, otherwise use top-left
                center_x_mm = bubble_data.get('center_x', bubble_data.get('x', 0))
                center_y_mm = bubble_data.get('center_y', bubble_data.get('y', 0))
                diameter_mm = bubble_data.get('diameter', bubble_data.get('width', 5))
                
                # Convert to pixels
                center_x_px = center_x_mm * mm_to_pixels
                center_y_px = center_y_mm * mm_to_pixels
                diameter_px = diameter_mm * mm_to_pixels
                
                # Convert center coordinates to top-left for ROI extraction
                x_px = center_x_px - (diameter_px / 2)
                y_px = center_y_px - (diameter_px / 2)
                
                zones.append({
                    'id': bubble_id,
                    'contest': contest,
                    'code': code,
                    'x': int(x_px),
                    'y': int(y_px),
                    'width': int(diameter_px),
                    'height': int(diameter_px)
                })
        
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
