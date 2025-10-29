#!/usr/bin/env python3
"""Debug script to visualize coordinate transformations."""

import sys
import cv2
import numpy as np
import json
from image_aligner import detect_fiducials, align_image
from utils import load_template

def visualize_coordinates(image_path, template_path, output_path):
    """Visualize bubble coordinates before and after transformation."""
    
    # Load image and template
    image = cv2.imread(image_path)
    if image is None:
        print(f"Error: Could not load image {image_path}", file=sys.stderr)
        return False
        
    template = load_template(template_path)
    
    # Detect fiducials
    fiducials = detect_fiducials(image, template)
    if fiducials is None:
        print("Error: Could not detect fiducials", file=sys.stderr)
        return False
    
    print(f"Detected {len(fiducials)} fiducials", file=sys.stderr)
    
    # Get inverse matrix
    _, quality_metrics, inv_matrix = align_image(image, fiducials, template, verbose=True)
    
    print(f"\nInverse matrix:\n{inv_matrix}", file=sys.stderr)
    
    # Convert mm to pixels
    mm_to_pixels = 300 / 25.4
    
    # Get bubble coordinates from template
    bubble_dict = template.get('bubble', {})
    zones = []
    for bubble_id, bubble_data in bubble_dict.items():
        center_x_mm = bubble_data.get('center_x', bubble_data.get('x', 0))
        center_y_mm = bubble_data.get('center_y', bubble_data.get('y', 0))
        diameter_mm = bubble_data.get('diameter', bubble_data.get('width', 5))
        
        center_x_px = center_x_mm * mm_to_pixels
        center_y_px = center_y_mm * mm_to_pixels
        diameter_px = diameter_mm * mm_to_pixels
        
        x_px = center_x_px - (diameter_px / 2)
        y_px = center_y_px - (diameter_px / 2)
        
        zones.append({
            'id': bubble_id,
            'x': int(x_px),
            'y': int(y_px),
            'width': int(diameter_px),
            'height': int(diameter_px),
            'center_x': center_x_px,
            'center_y': center_y_px
        })
    
    # Create visualization
    vis = image.copy()
    
    # Draw original coordinates in GREEN
    for zone in zones[:20]:  # First 20 bubbles only
        x, y = int(zone['x']), int(zone['y'])
        w, h = int(zone['width']), int(zone['height'])
        cv2.rectangle(vis, (x, y), (x + w, y + h), (0, 255, 0), 2)
        cv2.circle(vis, (int(zone['center_x']), int(zone['center_y'])), 3, (0, 255, 0), -1)
    
    # Transform coordinates
    for zone in zones[:20]:
        center_x = zone['center_x']
        center_y = zone['center_y']
        
        # Transform center point
        point = np.array([[center_x, center_y]], dtype=np.float32).reshape(-1, 1, 2)
        transformed_point = cv2.perspectiveTransform(point, inv_matrix)
        
        new_center_x = transformed_point[0][0][0]
        new_center_y = transformed_point[0][0][1]
        
        # Calculate new top-left
        new_x = int(new_center_x - zone['width'] / 2)
        new_y = int(new_center_y - zone['height'] / 2)
        
        # Draw transformed coordinates in RED
        cv2.rectangle(vis, (new_x, new_y), 
                     (new_x + zone['width'], new_y + zone['height']), 
                     (0, 0, 255), 2)
        cv2.circle(vis, (int(new_center_x), int(new_center_y)), 3, (0, 0, 255), -1)
        
        # Draw line from original to transformed
        cv2.line(vis, 
                (int(zone['center_x']), int(zone['center_y'])),
                (int(new_center_x), int(new_center_y)),
                (255, 0, 255), 1)
        
        print(f"{zone['id']}: ({zone['center_x']:.1f}, {zone['center_y']:.1f}) -> ({new_center_x:.1f}, {new_center_y:.1f})", 
              file=sys.stderr)
    
    # Draw fiducials in BLUE
    for i, (fx, fy) in enumerate(fiducials):
        cv2.circle(vis, (int(fx), int(fy)), 10, (255, 0, 0), 3)
        cv2.putText(vis, f"F{i}", (int(fx) + 15, int(fy)), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 0, 0), 2)
    
    # Add legend
    cv2.putText(vis, "GREEN = Original coordinates", (10, 30), 
               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)
    cv2.putText(vis, "RED = Transformed coordinates", (10, 60), 
               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 0, 255), 2)
    cv2.putText(vis, "BLUE = Fiducials", (10, 90), 
               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 0, 0), 2)
    cv2.putText(vis, "MAGENTA = Transformation vectors", (10, 120), 
               cv2.FONT_HERSHEY_SIMPLEX, 0.7, (255, 0, 255), 2)
    
    # Save visualization
    cv2.imwrite(output_path, vis)
    print(f"\nVisualization saved to: {output_path}", file=sys.stderr)
    
    return True

if __name__ == '__main__':
    import os
    
    if len(sys.argv) < 3:
        print("Usage: python debug_coordinates.py <image> <template> [output]")
        print("Example: python debug_coordinates.py ballot.png coords.json debug.png")
        sys.exit(1)
    
    image_path = sys.argv[1]
    template_path = sys.argv[2]
    output_path = sys.argv[3] if len(sys.argv) > 3 else 'debug_coordinates.png'
    
    # Set ArUco mode
    os.environ['OMR_FIDUCIAL_MODE'] = 'aruco'
    
    success = visualize_coordinates(image_path, template_path, output_path)
    sys.exit(0 if success else 1)
