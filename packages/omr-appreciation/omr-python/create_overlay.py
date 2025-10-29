#!/usr/bin/env python3
"""Create visual overlay showing detected marks and fill ratios."""

import sys
import cv2
import numpy as np
import json
import os
from typing import Dict, List


def load_template_coordinates(template_path: str) -> Dict:
    """Load template coordinates to get bubble positions."""
    # Try to find coordinates.json in various locations
    possible_paths = [
        template_path,
        os.path.join(template_path, 'coordinates.json') if template_path and os.path.isdir(template_path) else None,
        os.path.join(os.path.dirname(template_path or ''), 'coordinates.json'),
        '../../../storage/app/tests/omr-appreciation/latest/template/coordinates.json',
    ]
    
    for path in possible_paths:
        if path and os.path.isfile(path):
            try:
                with open(path, 'r') as f:
                    return json.load(f)
            except Exception:
                continue
    
    return None


def get_mark_style(mark: Dict) -> Dict:
    """Determine visual style for a mark."""
    filled = mark.get('filled', False)
    fill_ratio = mark.get('fill_ratio', 0.0)
    warnings = mark.get('warnings', []) or []
    
    # Green for valid filled marks (high confidence)
    if filled and fill_ratio >= 0.95:
        return {'color': (0, 255, 0), 'thickness': 4, 'label': ''}
    
    # Yellow for ambiguous
    if 'ambiguous' in warnings:
        return {'color': (0, 255, 255), 'thickness': 3, 'label': 'AMBIGUOUS'}
    
    # Yellow for filled but lower confidence
    if filled:
        return {'color': (0, 255, 255), 'thickness': 3, 'label': f"{fill_ratio:.0%}"}
    
    # Orange for faint marks
    if fill_ratio >= 0.16 and fill_ratio < 0.45:
        return {'color': (0, 165, 255), 'thickness': 2, 'label': 'FAINT'}
    
    # Red for empty/not filled
    return {'color': (0, 0, 255), 'thickness': 2, 'label': ''}


def create_overlay(image_path: str, results_path: str, output_path: str, template_path: str = None) -> bool:
    """Create overlay visualization showing detected marks on actual bubble locations.
    
    Args:
        image_path: Path to original ballot image
        results_path: Path to appreciation results JSON
        output_path: Path to save overlay image
        template_path: Optional path to coordinates.json
        
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
        
        # Load template to get bubble positions
        template = load_template_coordinates(template_path or os.path.dirname(results_path))
        
        # Create overlay
        overlay = image.copy()
        mm_to_pixels = 300 / 25.4
        
        # Get bubble coordinates (if template available)
        bubbles = template.get('bubble', {}) if template else {}
        
        # Stats
        stats = {'filled': 0, 'unfilled': 0, 'ambiguous': 0}
        
        # Draw each detected mark
        for result in results:
            bubble_id = result.get('id', '')
            filled = result.get('filled', False)
            fill_ratio = result.get('fill_ratio', 0.0)
            
            # Skip if not in template
            if bubble_id not in bubbles:
                continue
            
            # Only show filled marks to reduce clutter (like PHP version)
            if not filled:
                continue
            
            bubble = bubbles[bubble_id]
            
            # Get bubble position
            center_x_mm = bubble.get('center_x', bubble.get('x', 0))
            center_y_mm = bubble.get('center_y', bubble.get('y', 0))
            diameter_mm = bubble.get('diameter', bubble.get('width', 5))
            
            center_x = int(center_x_mm * mm_to_pixels)
            center_y = int(center_y_mm * mm_to_pixels)
            radius = int((diameter_mm / 2) * mm_to_pixels) + 5  # +5px offset
            
            # Get style
            style = get_mark_style(result)
            
            # Draw circle around bubble
            cv2.circle(overlay, (center_x, center_y), radius, style['color'], style['thickness'])
            
            # Draw fill ratio text
            font = cv2.FONT_HERSHEY_SIMPLEX
            text = f"{fill_ratio:.0%}"
            if style['label']:
                text += f" {style['label']}"
            
            # Position text to the right of circle
            text_x = center_x + radius + 10
            text_y = center_y + 5
            
            cv2.putText(overlay, text, (text_x, text_y), font, 0.6, style['color'], 2)
            
            # Update stats
            if fill_ratio >= 0.95:
                stats['filled'] += 1
            elif 'ambiguous' in (result.get('warnings', []) or []):
                stats['ambiguous'] += 1
        
        # Draw legend and mark list
        font = cv2.FONT_HERSHEY_SIMPLEX
        legend_x = 20
        legend_y = 40
        line_height = 25
        
        # Summary
        filled_count = sum(1 for r in results if r.get('filled', False))
        circles_drawn = stats['filled'] + stats.get('ambiguous', 0)
        
        cv2.putText(overlay, f"Detected: {filled_count} filled marks ({circles_drawn} visible)", 
                   (legend_x, legend_y), font, 0.7, (255, 255, 255), 2)
        
        # If many marks aren't visible, show them in a list
        if filled_count > circles_drawn + 2:
            # List filled marks (up to 20)
            filled_marks = [r for r in results if r.get('filled', False)]
            y_pos = legend_y + line_height + 10
            
            cv2.putText(overlay, f"Hidden marks ({filled_count - circles_drawn}):", 
                       (legend_x, y_pos), font, 0.5, (200, 200, 200), 1)
            y_pos += 20
            
            for mark in filled_marks[circles_drawn:circles_drawn+15]:  # Show next 15
                mark_id = mark.get('id', 'unknown')[:35]  # Truncate long IDs
                fill_pct = int(mark.get('fill_ratio', 0) * 100)
                cv2.putText(overlay, f"  {mark_id} ({fill_pct}%)", 
                           (legend_x, y_pos), font, 0.4, (180, 180, 180), 1)
                y_pos += 18
                
                # Stop if we run out of space
                if y_pos > overlay.shape[0] - 50:
                    remaining = filled_count - circles_drawn - 15
                    if remaining > 0:
                        cv2.putText(overlay, f"  ... and {remaining} more", 
                                   (legend_x, y_pos), font, 0.4, (150, 150, 150), 1)
                    break
        else:
            # Color legend
            cv2.putText(overlay, "GREEN = Valid (>95%)", (legend_x, legend_y + line_height), font, 0.5, (0, 255, 0), 2)
            cv2.putText(overlay, "YELLOW = Ambiguous", (legend_x, legend_y + line_height * 2), font, 0.5, (0, 255, 255), 2)
        
        # Save overlay
        cv2.imwrite(output_path, overlay)
        print(f"Overlay saved: {filled_count} marks visualized", file=sys.stderr)
        return True
        
    except Exception as e:
        print(f"Error creating overlay: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc(file=sys.stderr)
        return False


def create_simple_overlay(image: np.ndarray, results: List[Dict], output_path: str) -> bool:
    """Fallback overlay with just text summary."""
    overlay = image.copy()
    filled_count = sum(1 for r in results if r.get('filled', False))
    
    font = cv2.FONT_HERSHEY_SIMPLEX
    text = f"Detected: {filled_count} filled marks"
    cv2.putText(overlay, text, (20, 40), font, 1.0, (0, 255, 0), 3)
    
    cv2.imwrite(output_path, overlay)
    return True


if __name__ == '__main__':
    import argparse
    
    parser = argparse.ArgumentParser(
        description='Create visual overlay showing detected marks on ballot'
    )
    parser.add_argument('image', help='Path to ballot image')
    parser.add_argument('results', help='Path to appreciation results JSON')
    parser.add_argument('output', help='Path to save overlay image')
    parser.add_argument('--template', '-t', help='Path to template coordinates.json (optional)')
    
    args = parser.parse_args()
    
    success = create_overlay(args.image, args.results, args.output, args.template)
    sys.exit(0 if success else 1)
