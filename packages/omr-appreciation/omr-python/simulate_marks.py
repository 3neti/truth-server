#!/usr/bin/env python3
"""Simulate filled marks on ballot images for testing.

This script draws realistic pen/pencil marks at specified zone positions
to simulate how a voter would fill out a ballot.
"""

import sys
import argparse
import cv2
import numpy as np
from utils import load_template, get_roi_coordinates


def draw_filled_mark(image: np.ndarray, zone: dict, fill_percentage: float = 0.75) -> None:
    """Draw a realistic filled mark in a zone.
    
    Args:
        image: Image to draw on (modified in place)
        zone: Zone definition with x, y, width, height
        fill_percentage: How much of the zone to fill (0.0 to 1.0)
    """
    x, y, width, height = get_roi_coordinates(zone)
    
    # Calculate center and radius
    center_x = x + width // 2
    center_y = y + height // 2
    radius = int(min(width, height) * fill_percentage / 2)
    
    # Draw filled circle (simulating pen mark)
    cv2.circle(image, (center_x, center_y), radius, (0, 0, 0), -1)
    
    # Add slight texture to make it more realistic (optional)
    # Simulate pen pressure variation with random noise
    noise_region = image[max(0, center_y - radius):min(image.shape[0], center_y + radius),
                         max(0, center_x - radius):min(image.shape[1], center_x + radius)]
    
    if noise_region.size > 0:
        noise = np.random.normal(0, 3, noise_region.shape).astype(np.int16)
        noise_region[:] = np.clip(noise_region.astype(np.int16) + noise, 0, 255).astype(np.uint8)


def main():
    """Main entry point for mark simulation."""
    parser = argparse.ArgumentParser(
        description='Simulate filled marks on ballot images for testing'
    )
    parser.add_argument('image', help='Path to ballot image (will be modified)')
    parser.add_argument('template', help='Path to template JSON file')
    parser.add_argument('--mark-zones', '-m', required=True,
                       help='Comma-separated list of zone indices to mark (e.g., "0,3,5")')
    parser.add_argument('--fill', '-f', type=float, default=0.75,
                       help='Fill percentage (0.0 to 1.0, default: 0.75)')
    parser.add_argument('--output', '-o',
                       help='Output path (default: overwrite input image)')
    
    args = parser.parse_args()
    
    # Parse mark zones
    try:
        mark_indices = [int(idx.strip()) for idx in args.mark_zones.split(',')]
    except ValueError:
        print(f"Error: Invalid mark-zones format. Use comma-separated numbers.", file=sys.stderr)
        sys.exit(1)
    
    # Load template
    try:
        template = load_template(args.template)
    except Exception as e:
        print(f"Error loading template: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Load image
    try:
        image = cv2.imread(args.image)
        if image is None:
            raise ValueError(f"Could not load image: {args.image}")
    except Exception as e:
        print(f"Error loading image: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Get zones
    zones = template.get('zones', [])
    if not zones:
        print("Error: No zones found in template", file=sys.stderr)
        sys.exit(1)
    
    # Validate mark indices
    invalid_indices = [idx for idx in mark_indices if idx < 0 or idx >= len(zones)]
    if invalid_indices:
        print(f"Error: Invalid zone indices: {invalid_indices}", file=sys.stderr)
        print(f"Valid range: 0 to {len(zones) - 1}", file=sys.stderr)
        sys.exit(1)
    
    # Draw marks
    print(f"Drawing marks on {len(mark_indices)} zone(s)...", file=sys.stderr)
    for idx in mark_indices:
        zone = zones[idx]
        draw_filled_mark(image, zone, args.fill)
        zone_id = zone.get('id', f'Zone {idx}')
        print(f"  ✓ Marked: {zone_id}", file=sys.stderr)
    
    # Save output
    output_path = args.output if args.output else args.image
    try:
        cv2.imwrite(output_path, image)
        print(f"✅ Saved to: {output_path}", file=sys.stderr)
    except Exception as e:
        print(f"Error saving image: {e}", file=sys.stderr)
        sys.exit(1)


if __name__ == '__main__':
    main()
