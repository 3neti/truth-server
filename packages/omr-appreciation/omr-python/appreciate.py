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
from barcode_decoder import decode_barcode
from bubble_metadata import load_bubble_metadata


def generate_ballot_cast_format(document_id: str, results: list) -> str:
    """Generate compact ballot format for Laravel election:cast-ballot command.
    
    Args:
        document_id: Ballot ID (e.g., 'BAL-001')
        results: List of mark detection results
        
    Returns:
        Compact ballot string in format:
        "BAL-001|POSITION1:CODE1,CODE2;POSITION2:CODE3"
    """
    # Group filled marks by contest/position
    positions = {}
    for result in results:
        if result.get('filled', False):
            contest = result.get('contest', '')
            code = result.get('code', '')
            
            if contest and code:
                if contest not in positions:
                    positions[contest] = []
                positions[contest].append(code)
    
    # Build compact format: POSITION:CODE1,CODE2;POSITION2:CODE3
    position_strings = []
    for position, codes in sorted(positions.items()):
        codes_str = ','.join(codes)
        position_strings.append(f"{position}:{codes_str}")
    
    # Join with semicolons
    ballot_votes = ';'.join(position_strings)
    
    # Final format: BALLOT-ID|VOTES
    return f"{document_id}|{ballot_votes}"


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
    parser.add_argument('--config-path', type=str, default=None,
                       help='Path to election config directory (for bubble metadata lookup)')
    
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
    
    # Load bubble metadata if config path provided
    bubble_metadata = load_bubble_metadata(args.config_path)
    
    # Load image
    try:
        image = cv2.imread(image_path)
        if image is None:
            raise ValueError(f"Could not load image: {image_path}")
    except Exception as e:
        print(f"Error loading image: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Align image based on fiducials (unless disabled)
    inv_matrix = None  # No transformation needed if alignment is skipped
    quality_metrics = None
    fiducial_coords = None
    
    if args.no_align:
        # Skip alignment for perfect test images
        aligned_image = image
    else:
        # Detect fiducials
        fiducials = detect_fiducials(image, template)
        if fiducials is None:
            print("Error: Could not detect 4 fiducial markers", file=sys.stderr)
            sys.exit(1)
        
        # Store fiducial coordinates for output
        fiducial_coords = {
            'tl': {'x': int(fiducials[0][0]), 'y': int(fiducials[0][1])},
            'tr': {'x': int(fiducials[1][0]), 'y': int(fiducials[1][1])},
            'bl': {'x': int(fiducials[2][0]), 'y': int(fiducials[2][1])},
            'br': {'x': int(fiducials[3][0]), 'y': int(fiducials[3][1])},
        }
        
        # Align image (returns original image + inverse matrix for coordinate transform)
        try:
            aligned_image, quality_metrics, inv_matrix = align_image(image, fiducials, template)
        except Exception as e:
            print(f"Error aligning image: {e}", file=sys.stderr)
            sys.exit(1)
    
    # Decode barcode (QR code, Code128, etc.) from ballot footer
    barcode_result = None
    barcode_coords = template.get('barcode', {}).get('document_barcode', {})
    if barcode_coords:
        try:
            mm_to_pixels = 300 / 25.4  # 11.811 pixels per mm
            metadata_fallback = template.get('barcode', {}).get('document_barcode', {}).get('data')
            barcode_result = decode_barcode(
                image,  # Use original image (barcode is not affected by alignment)
                barcode_coords,
                mm_to_px_ratio=mm_to_pixels,
                metadata_fallback=metadata_fallback
            )
        except Exception as e:
            print(f"Warning: Barcode decode failed: {e}", file=sys.stderr)
            # Continue without barcode - not critical for mark detection
    
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
                # Determine contest and code (supports both simple and verbose IDs)
                if bubble_metadata and bubble_metadata.available:
                    meta = bubble_metadata.get(bubble_id)
                    if meta:
                        # Use metadata (simple ID format)
                        contest = meta['position_code']
                        code = meta['candidate_code']
                    else:
                        # Metadata available but bubble not found - parse as fallback
                        parts = bubble_id.rsplit('_', 1)
                        contest = parts[0] if len(parts) > 1 else ''
                        code = parts[1] if len(parts) > 1 else bubble_id
                else:
                    # No metadata - use legacy parsing (verbose ID format)
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
        
        results = detect_marks(aligned_image, zones, threshold=threshold, inv_matrix=inv_matrix)
    except Exception as e:
        print(f"Error detecting marks: {e}", file=sys.stderr)
        sys.exit(1)
    
    # Prepare output
    document_id = barcode_result['document_id'] if barcode_result and barcode_result['decoded'] else template.get('document_id', '')
    
    # Generate compact ballot cast format
    ballot_cast_format = generate_ballot_cast_format(document_id, results)
    
    output = {
        'document_id': document_id,
        'template_id': template.get('template_id', ''),
        'ballot_cast_format': ballot_cast_format,
        'results': results
    }
    
    # Include barcode metadata if available
    if barcode_result:
        output['barcode'] = {
            'decoded': barcode_result['decoded'],
            'decoder': barcode_result['decoder'],
            'confidence': barcode_result['confidence'],
            'source': barcode_result['source'],
            'barcode_type': barcode_result.get('barcode_type'),
            'attempts': barcode_result.get('attempts', []),
            'roi_size': barcode_result.get('roi_size'),
            'decode_time_ms': round(barcode_result.get('decode_time_ms', 0.0), 2)
        }
    
    # Include fiducial alignment data if available
    if fiducial_coords:
        output['fiducials'] = {
            'detected': fiducial_coords,
            'count': 4
        }
    
    # Include quality metrics if available
    if quality_metrics:
        # Import quality check function
        try:
            from quality_metrics import check_quality_thresholds
            verdicts = check_quality_thresholds(quality_metrics)
            
            output['quality'] = {
                'metrics': {
                    'rotation_deg': round(quality_metrics['theta_deg'], 2),
                    'shear_deg': round(quality_metrics['shear_deg'], 2),
                    'aspect_ratio_tb': round(quality_metrics['ratio_tb'], 3),
                    'aspect_ratio_lr': round(quality_metrics['ratio_lr'], 3),
                    'reprojection_error_px': round(quality_metrics['reproj_error_px'], 2)
                },
                'verdicts': verdicts,
                'overall': verdicts['overall']
            }
        except ImportError:
            # Quality metrics module not available, include raw metrics
            output['quality'] = {
                'metrics': {
                    'rotation_deg': round(quality_metrics['theta_deg'], 2),
                    'shear_deg': round(quality_metrics['shear_deg'], 2),
                    'aspect_ratio_tb': round(quality_metrics['ratio_tb'], 3),
                    'aspect_ratio_lr': round(quality_metrics['ratio_lr'], 3),
                    'reprojection_error_px': round(quality_metrics['reproj_error_px'], 2)
                }
            }
    
    # Output JSON
    output_json(output)


if __name__ == '__main__':
    main()
