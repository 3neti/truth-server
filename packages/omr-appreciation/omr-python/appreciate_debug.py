#!/usr/bin/env python3
"""Debug version of OMR appreciation that saves visualization."""

import sys
import cv2
from utils import load_template, output_json, get_roi_coordinates
from image_aligner import detect_fiducials, align_image
from mark_detector import detect_marks


def main():
    """Main entry point for OMR appreciation with debug output."""
    if len(sys.argv) < 3:
        print("Usage: python appreciate_debug.py <image_path> <template_path> [output_image]", file=sys.stderr)
        sys.exit(1)
    
    image_path = sys.argv[1]
    template_path = sys.argv[2]
    output_image = sys.argv[3] if len(sys.argv) > 3 else "debug_output.jpg"
    
    # Load template
    template = load_template(template_path)
    
    # Load image
    image = cv2.imread(image_path)
    if image is None:
        print(f"Error: Could not load image: {image_path}", file=sys.stderr)
        sys.exit(1)
    
    # Detect fiducials
    fiducials = detect_fiducials(image, template)
    if fiducials is None:
        print("Error: Could not detect 4 fiducial markers", file=sys.stderr)
        sys.exit(1)
    
    # Draw fiducials on original image (for debugging)
    debug_image = image.copy()
    for i, (x, y) in enumerate(fiducials):
        cv2.circle(debug_image, (x, y), 10, (0, 255, 0), 3)
        cv2.putText(debug_image, f"F{i}", (x+15, y), cv2.FONT_HERSHEY_SIMPLEX, 1, (0, 255, 0), 2)
    
    # Align image
    aligned_image = align_image(image, fiducials, template)
    
    # Draw zones on aligned image
    zones = template.get('zones', [])
    debug_aligned = aligned_image.copy()
    for zone in zones:
        x, y, width, height = get_roi_coordinates(zone)
        cv2.rectangle(debug_aligned, (x, y), (x + width, y + height), (0, 0, 255), 2)
        cv2.putText(debug_aligned, zone.get('id', '')[:10], (x, y-5), 
                    cv2.FONT_HERSHEY_SIMPLEX, 0.5, (0, 0, 255), 1)
    
    # Detect marks
    results = detect_marks(aligned_image, zones)
    
    # Draw fill ratios and confidence on aligned image
    for i, result in enumerate(results):
        zone = zones[i]
        x, y, width, height = get_roi_coordinates(zone)
        fill_text = f"{result['fill_ratio']:.2f}"
        conf_text = f"C:{result['confidence']:.2f}"
        color = (0, 255, 0) if result['filled'] else (0, 0, 255)
        cv2.putText(debug_aligned, fill_text, (x + width + 5, y + 20),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.5, color, 2)
        cv2.putText(debug_aligned, conf_text, (x + width + 5, y + 40),
                    cv2.FONT_HERSHEY_SIMPLEX, 0.4, (255, 0, 255), 1)
    
    # Save debug images
    cv2.imwrite(output_image.replace('.jpg', '_original.jpg'), debug_image)
    cv2.imwrite(output_image, debug_aligned)
    
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
