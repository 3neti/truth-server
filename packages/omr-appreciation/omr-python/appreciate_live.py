#!/usr/bin/env python3
"""Live AR Ballot Appreciation - Refactored to use core OMR modules

Real-time ballot appreciation using webcam with AR visualization.
Integrates with image_aligner, mark_detector, barcode_decoder, and utils.
"""
import argparse
import json
import time
import math
import sys
from typing import Dict, Any, Optional
import cv2
import numpy as np

# Import core OMR modules
from image_aligner import detect_fiducials, align_image
from mark_detector import detect_marks
from barcode_decoder import decode_barcode
from utils import load_template


def parse_args():
    ap = argparse.ArgumentParser(
        description='Live AR Ballot Appreciation (Webcam with AR overlay)',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # With actual template
  python appreciate_live.py --template coordinates.json --threshold 0.3
  
  # With demo grid (for testing)
  python appreciate_live.py --demo-grid --size 2480x3508
        """
    )
    ap.add_argument('--camera', type=int, default=0,
                   help='Camera device index (default: 0)')
    ap.add_argument('--template', type=str, default='',
                   help='Path to coordinates.json template file')
    ap.add_argument('--threshold', type=float, default=0.30,
                   help='Fill detection threshold 0.0-1.0 (default: 0.30)')
    ap.add_argument('--demo-grid', action='store_true',
                   help='Use demo grid bubbles (for testing without template)')
    ap.add_argument('--size', default='2480x3508',
                   help='Ballot size in pixels WxH (for demo mode, default: 2480x3508)')
    ap.add_argument('--show-warp', action='store_true',
                   help='Show warped ballot view (debug)')
    ap.add_argument('--no-fps', action='store_true',
                   help='Hide FPS counter')
    ap.add_argument('--no-barcode', action='store_true',
                   help='Skip barcode decode (faster)')
    return ap.parse_args()


def demo_grid_bubbles(W: int, H: int, r: int = 18, cols: int = 6, rows: int = 10, 
                      mx: int = 300, my: int = 500, gx: int = 220, gy: int = 180):
    """Generate demo grid of bubbles for testing without template."""
    bubbles = {}
    y = my
    lbl = 1
    for _ in range(rows):
        x = mx
        for _ in range(cols):
            bubble_id = f'DEMO_B{lbl:02d}'
            bubbles[bubble_id] = {
                'center_x': x / 11.811,  # Convert px to mm
                'center_y': y / 11.811,
                'diameter': (r * 2) / 11.811
            }
            x += gx
            lbl += 1
        y += gy
    return bubbles


def create_demo_template(W: int, H: int):
    """Create minimal template for demo mode."""
    template = {
        'bubble': demo_grid_bubbles(W, H),
        'fiducial': {
            'tl': {'x': 8.5, 'y': 8.5, 'marker_id': 101, 'type': 'aruco'},
            'tr': {'x': W/11.811 - 36.85, 'y': 8.5, 'marker_id': 102, 'type': 'aruco'},
            'br': {'x': W/11.811 - 36.85, 'y': H/11.811 - 36.85, 'marker_id': 103, 'type': 'aruco'},
            'bl': {'x': 8.5, 'y': H/11.811 - 36.85, 'marker_id': 104, 'type': 'aruco'}
        },
        'barcode': {},
        'document_id': 'DEMO-BALLOT'
    }
    return template


def convert_bubbles_to_zones(bubbles: Dict, mm_to_px: float = 11.811):
    """Convert bubble dict to zones format for mark_detector."""
    zones = []
    for bubble_id, bubble in bubbles.items():
        center_x_px = bubble['center_x'] * mm_to_px
        center_y_px = bubble['center_y'] * mm_to_px
        diameter_px = bubble['diameter'] * mm_to_px
        radius_px = diameter_px / 2
        
        zones.append({
            'id': bubble_id,
            'contest': bubble_id.rsplit('_', 1)[0],  # e.g., PRESIDENT_001 -> PRESIDENT
            'code': bubble_id.rsplit('_', 1)[1] if '_' in bubble_id else bubble_id,
            'x': int(center_x_px - radius_px),
            'y': int(center_y_px - radius_px),
            'width': int(diameter_px),
            'height': int(diameter_px)
        })
    return zones


def draw_overlay(frame, fiducials, results, barcode_result=None, quality=None, 
                angle_deg=None, fps=None, show_names=False):
    """Draw AR overlay with fiducials, bubbles, barcode info, and quality."""
    mm_to_px = 11.811
    
    # Draw fiducials (if detected)
    if fiducials:
        for corner_name, fid_point in fiducials.items():
            if fid_point is not None:
                pt = tuple(fid_point.astype(int))
                cv2.circle(frame, pt, 8, (255, 255, 0), -1)
                cv2.circle(frame, pt, 12, (255, 255, 0), 2)
    
    # Draw bubbles
    for bubble_id, result in results.items():
        if not result.get('center_x') or not result.get('center_y'):
            continue
            
        cx = int(result['center_x'])
        cy = int(result['center_y'])
        r = int(result.get('radius', 20))
        
        # Color based on fill status
        if result.get('filled', False):
            fill_ratio = result.get('fill_ratio', 0)
            if fill_ratio >= 0.95:
                color = (0, 255, 0)  # Green - good fill
            else:
                color = (0, 255, 255)  # Yellow - low confidence
        else:
            color = (0, 0, 255)  # Red - not filled
        
        # Draw circle
        cv2.circle(frame, (cx, cy), r, color, 2)
        
        # Draw label if requested
        if show_names:
            label = result.get('id', bubble_id)
            cv2.putText(frame, label, (cx + r + 5, cy), 
                       cv2.FONT_HERSHEY_SIMPLEX, 0.4, color, 1, cv2.LINE_AA)
    
    # Draw info overlay (top-left corner)
    y = 30
    
    # Barcode info
    if barcode_result and barcode_result.get('decoded'):
        doc_id = barcode_result.get('document_id', 'UNKNOWN')
        source = barcode_result.get('source', 'unknown')
        decoder = barcode_result.get('decoder', 'none')
        
        color = (0, 255, 0) if source == 'visual' else (0, 165, 255)
        cv2.putText(frame, f'Ballot: {doc_id}', (20, y), 
                   cv2.FONT_HERSHEY_DUPLEX, 0.8, color, 2, cv2.LINE_AA)
        y += 28
        cv2.putText(frame, f'Decoder: {decoder}', (20, y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, color, 1, cv2.LINE_AA)
        y += 24
    
    # Quality info
    if quality:
        overall = quality.get('overall', 'unknown')
        if overall == 'green':
            q_color = (0, 255, 0)
        elif overall == 'amber':
            q_color = (0, 165, 255)
        else:
            q_color = (0, 0, 255)
        
        cv2.putText(frame, f'Quality: {overall.upper()}', (20, y), 
                   cv2.FONT_HERSHEY_DUPLEX, 0.8, q_color, 2, cv2.LINE_AA)
        y += 28
    
    # Angle
    if angle_deg is not None:
        cv2.putText(frame, f'Angle: {angle_deg:+.1f}°', (20, y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1, cv2.LINE_AA)
        y += 24
    
    # FPS
    if fps is not None:
        cv2.putText(frame, f'FPS: {fps:.1f}', (20, y), 
                   cv2.FONT_HERSHEY_SIMPLEX, 0.6, (255, 255, 255), 1, cv2.LINE_AA)


def compute_angle(fiducials):
    """Compute ballot angle from top-left and top-right fiducials."""
    if not fiducials or 'tl' not in fiducials or 'tr' not in fiducials:
        return None
    
    tl = fiducials['tl']
    tr = fiducials['tr']
    
    if tl is None or tr is None:
        return None
    
    vx, vy = (tr - tl)
    angle = math.degrees(math.atan2(vy, vx))
    return angle


def main():
    args = parse_args()
    
    # Load or create template
    if args.template:
        try:
            template = load_template(args.template)
            print(f'✓ Loaded template: {args.template}')
        except Exception as e:
            print(f'✗ Error loading template: {e}', file=sys.stderr)
            sys.exit(1)
    elif args.demo_grid:
        W, H = map(int, args.size.lower().split('x'))
        template = create_demo_template(W, H)
        print('✓ Using demo grid template')
    else:
        print('Error: Provide --template or --demo-grid', file=sys.stderr)
        sys.exit(1)
    
    # Convert bubbles to zones for mark detector
    zones = convert_bubbles_to_zones(template['bubble'])
    print(f'✓ Loaded {len(zones)} bubbles')
    
    # Get barcode config
    barcode_config = template.get('barcode', {}).get('document_barcode', None)
    mm_to_px = 11.811
    
    # Open camera
    cap = cv2.VideoCapture(args.camera)
    if not cap.isOpened():
        print(f'✗ Cannot open camera {args.camera}', file=sys.stderr)
        sys.exit(1)
    
    print(f'✓ Camera {args.camera} opened')
    print('\nControls:')
    print('  ESC - Exit')
    print('  W   - Toggle warped view (debug)')
    print('\nStarting live appreciation...\n')
    
    # State
    prev_t = time.time()
    fps = None
    show_warp = args.show_warp
    
    while True:
        ok, frame = cap.read()
        if not ok:
            print('✗ Failed to read frame', file=sys.stderr)
            break
        
        # Detect fiducials using core module
        fiducials = detect_fiducials(frame, template)
        
        # Initialize results
        results = {}
        barcode_result = None
        quality = None
        angle = None
        
        # If we have enough fiducials, do alignment and detection
        if fiducials and sum(1 for v in fiducials.values() if v is not None) >= 4:
            try:
                # Align image using core module
                aligned, quality, inv_matrix = align_image(frame, fiducials, template)
                
                # Compute angle
                angle = compute_angle(fiducials)
                
                # Detect marks using core module
                results = detect_marks(aligned, zones, threshold=args.threshold, inv_matrix=inv_matrix)
                
                # Decode barcode using core module (if not disabled)
                if not args.no_barcode and barcode_config:
                    barcode_result = decode_barcode(
                        frame,
                        barcode_config,
                        mm_to_px_ratio=mm_to_px,
                        metadata_fallback=template.get('document_id')
                    )
                
                # Show warped view if enabled
                if show_warp:
                    cv2.imshow('Warped Page (debug)', aligned)
                    
            except Exception as e:
                print(f'Warning: Processing error: {e}', file=sys.stderr)
        
        # Draw overlay
        draw_overlay(frame, fiducials, results, barcode_result, quality, angle, 
                    fps=None if args.no_fps else fps)
        
        # Update FPS
        now = time.time()
        dt = now - prev_t
        fps = (1.0 / dt) if dt > 0 else fps
        prev_t = now
        
        # Display
        cv2.imshow('Live AR Ballot Appreciation', frame)
        
        # Handle keys
        key = cv2.waitKey(1) & 0xFF
        if key == 27:  # ESC
            break
        elif key == ord('w') or key == ord('W'):
            show_warp = not show_warp
            if not show_warp:
                cv2.destroyWindow('Warped Page (debug)')
    
    # Cleanup
    cap.release()
    cv2.destroyAllWindows()
    print('\n✓ Appreciation session ended')


if __name__ == '__main__':
    main()
