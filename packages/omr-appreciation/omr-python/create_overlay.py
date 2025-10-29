#!/usr/bin/env python3
"""Create visual overlay showing detected marks and fill ratios."""

import sys
import cv2
import numpy as np
import json
from typing import Dict, List


def create_overlay(image_path: str, results_path: str, output_path: str) -> bool:
    """Create overlay visualization showing detected marks.
    
    Args:
        image_path: Path to original ballot image
        results_path: Path to appreciation results JSON
        output_path: Path to save overlay image
        
    Returns:
        True if successful, False otherwise
    """
    try:
        # Load image
        image = cv2.imread(image_path)
        if image is None:
            print(f"Error: Could not load image: {image_path}", file=sys.stderr)
            return False
        
        # Load results
        with open(results_path, 'r') as f:
            data = json.load(f)
        
        results = data.get('results', [])
        if not results:
            print("Error: No results found in JSON", file=sys.stderr)
            return False
        
        # Create overlay
        overlay = image.copy()
        
        # Convert mm to pixels (300 DPI)
        mm_to_pixels = 300 / 25.4
        
        # Draw each bubble
        for result in results:
            bubble_id = result.get('id', '')
            filled = result.get('filled', False)
            fill_ratio = result.get('fill_ratio', 0.0)
            confidence = result.get('confidence', 0.0)
            
            # Get bubble coordinates from ID (would need template, so skip for now)
            # Instead, we'll draw a legend at the bottom
            
        # Count filled vs total
        filled_count = sum(1 for r in results if r.get('filled', False))
        total_count = len(results)
        
        # Draw summary at top
        font = cv2.FONT_HERSHEY_SIMPLEX
        font_scale = 1.2
        thickness = 3
        
        # Background rectangle for text
        text = f"Detected: {filled_count}/{total_count} marks filled"
        (text_w, text_h), baseline = cv2.getTextSize(text, font, font_scale, thickness)
        
        # Draw black background
        cv2.rectangle(overlay, (10, 10), (text_w + 30, text_h + 30), (0, 0, 0), -1)
        
        # Draw text
        cv2.putText(overlay, text, (20, text_h + 20), font, font_scale, (0, 255, 0), thickness)
        
        # Add color legend
        legend_y = text_h + 60
        cv2.putText(overlay, "GREEN = Filled", (20, legend_y), font, 0.6, (0, 255, 0), 2)
        cv2.putText(overlay, "RED = Empty", (20, legend_y + 30), font, 0.6, (0, 0, 255), 2)
        cv2.putText(overlay, "YELLOW = Ambiguous", (20, legend_y + 60), font, 0.6, (0, 255, 255), 2)
        
        # Draw detailed results in columns (top filled marks)
        filled_marks = [r for r in results if r.get('filled', False)][:20]  # Top 20
        
        details_y = legend_y + 120
        for i, mark in enumerate(filled_marks):
            mark_id = mark.get('id', '')
            fill_ratio = mark.get('fill_ratio', 0.0)
            
            # Truncate long IDs
            if len(mark_id) > 30:
                mark_id = mark_id[:27] + '...'
            
            text = f"{mark_id}: {fill_ratio:.1%}"
            cv2.putText(overlay, text, (20, details_y + i * 25), font, 0.5, (255, 255, 255), 1)
        
        # Save overlay
        cv2.imwrite(output_path, overlay)
        print(f"Overlay saved to: {output_path}", file=sys.stderr)
        return True
        
    except Exception as e:
        print(f"Error creating overlay: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        return False


if __name__ == '__main__':
    if len(sys.argv) != 4:
        print("Usage: python create_overlay.py <image> <results_json> <output>")
        print("Example: python create_overlay.py ballot.png results.json overlay.png")
        sys.exit(1)
    
    image_path = sys.argv[1]
    results_path = sys.argv[2]
    output_path = sys.argv[3]
    
    success = create_overlay(image_path, results_path, output_path)
    sys.exit(0 if success else 1)
