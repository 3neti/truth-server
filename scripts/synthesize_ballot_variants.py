#!/usr/bin/env python3
"""
Synthetic Ballot Variant Generator

Generates test ballot images with known geometric distortions for quality gate testing.
Produces 9 test cases matching SKEW_ROTATION_TEST_SCENARIO.md test matrix:

- U0: Reference upright (no distortion)
- R1-R3: Rotation tests (+3°, +10°, -20°)
- S1-S2: Shear tests (2°, 6°)
- P1-P3: Perspective tests (ratio 0.98, 0.95, 0.90)

Usage:
    python3 synthesize_ballot_variants.py --input reference_ballot.png --output fixtures/
"""

import argparse
import cv2
import numpy as np
import os
from pathlib import Path
from typing import Tuple


def apply_rotation(image: np.ndarray, angle_deg: float) -> np.ndarray:
    """Apply rotation transform to image.
    
    Args:
        image: Input image
        angle_deg: Rotation angle in degrees (positive = counterclockwise)
        
    Returns:
        Rotated image (same size as input, preserving corner markers)
    """
    h, w = image.shape[:2]
    
    # Calculate expanded canvas size to preserve corners during rotation
    angle_rad = np.radians(abs(angle_deg))
    new_w = int(abs(w * np.cos(angle_rad)) + abs(h * np.sin(angle_rad)))
    new_h = int(abs(h * np.cos(angle_rad)) + abs(w * np.sin(angle_rad)))
    
    # Pad image to expanded size
    pad_w = (new_w - w) // 2
    pad_h = (new_h - h) // 2
    padded = cv2.copyMakeBorder(image, pad_h, pad_h, pad_w, pad_w,
                                cv2.BORDER_CONSTANT, value=(255, 255, 255))
    
    # Rotate around new center
    center = (new_w // 2, new_h // 2)
    M = cv2.getRotationMatrix2D(center, angle_deg, 1.0)
    rotated = cv2.warpAffine(padded, M, (new_w, new_h),
                            borderMode=cv2.BORDER_CONSTANT,
                            borderValue=(255, 255, 255))
    
    # Crop back to original size (centered)
    y_start = (new_h - h) // 2
    x_start = (new_w - w) // 2
    cropped = rotated[y_start:y_start+h, x_start:x_start+w]
    
    return cropped


def apply_shear(image: np.ndarray, shear_angle_deg: float, axis: str = 'x') -> np.ndarray:
    """Apply shear transform to image.
    
    Args:
        image: Input image
        shear_angle_deg: Shear angle in degrees
        axis: 'x' for horizontal shear, 'y' for vertical shear
        
    Returns:
        Sheared image
    """
    h, w = image.shape[:2]
    shear_factor = np.tan(np.radians(shear_angle_deg))
    
    if axis == 'x':
        # Horizontal shear
        M = np.array([
            [1, shear_factor, 0],
            [0, 1, 0]
        ], dtype=np.float32)
    else:
        # Vertical shear
        M = np.array([
            [1, 0, 0],
            [shear_factor, 1, 0]
        ], dtype=np.float32)
    
    # Apply shear
    sheared = cv2.warpAffine(image, M, (w, h),
                             borderMode=cv2.BORDER_CONSTANT,
                             borderValue=(255, 255, 255))
    
    return sheared


def apply_perspective(image: np.ndarray, ratio_tb: float, ratio_lr: float = 1.0) -> np.ndarray:
    """Apply perspective distortion to image.
    
    Creates trapezoidal distortion by adjusting corner positions.
    
    Args:
        image: Input image
        ratio_tb: Top-to-bottom edge ratio (e.g., 0.95 = top is 95% of bottom width)
        ratio_lr: Left-to-right edge ratio (e.g., 0.95 = left is 95% of right height)
        
    Returns:
        Perspective-distorted image
    """
    h, w = image.shape[:2]
    
    # Source points (original corners)
    src = np.array([
        [0, 0],           # TL
        [w - 1, 0],       # TR
        [w - 1, h - 1],   # BR
        [0, h - 1]        # BL
    ], dtype=np.float32)
    
    # Destination points (distorted corners)
    # Adjust top edge to be ratio_tb * bottom edge
    top_offset = (w * (1 - ratio_tb)) / 2
    left_offset = (h * (1 - ratio_lr)) / 2
    
    dst = np.array([
        [top_offset, left_offset],                    # TL (moved inward)
        [w - 1 - top_offset, left_offset],            # TR (moved inward)
        [w - 1, h - 1 - left_offset],                 # BR
        [0, h - 1 - left_offset]                      # BL
    ], dtype=np.float32)
    
    # Compute perspective transform
    M = cv2.getPerspectiveTransform(src, dst)
    
    # Apply transform
    distorted = cv2.warpPerspective(image, M, (w, h),
                                    borderMode=cv2.BORDER_CONSTANT,
                                    borderValue=(255, 255, 255))
    
    return distorted


def generate_test_variants(input_path: str, output_dir: str, verbose: bool = True):
    """Generate all test variants from reference ballot.
    
    Args:
        input_path: Path to reference ballot image
        output_dir: Output directory for generated variants
        verbose: Print progress information
    """
    # Load reference image
    if verbose:
        print(f"Loading reference ballot: {input_path}")
    
    reference = cv2.imread(input_path)
    if reference is None:
        raise ValueError(f"Failed to load image: {input_path}")
    
    h, w = reference.shape[:2]
    if verbose:
        print(f"  Image size: {w}x{h}")
    
    # Create output directory
    Path(output_dir).mkdir(parents=True, exist_ok=True)
    
    # Test matrix from SKEW_ROTATION_TEST_SCENARIO.md
    test_cases = [
        # Case ID, Description, Transform, Expected Metrics
        ('U0', 'Reference upright', None, {'theta': 0, 'shear': 0, 'ratio': 1.00}),
        
        # Rotation tests
        ('R1', 'Rotation +3°', ('rotate', 3), {'theta': 3, 'shear': 0, 'ratio': 1.00}),
        ('R2', 'Rotation +10°', ('rotate', 10), {'theta': 10, 'shear': 0, 'ratio': 1.00}),
        ('R3', 'Rotation -20°', ('rotate', -20), {'theta': -20, 'shear': 0, 'ratio': 1.00}),
        
        # Shear tests
        ('S1', 'Shear 2°', ('shear', 2), {'theta': 0, 'shear': 2, 'ratio': 1.00}),
        ('S2', 'Shear 6°', ('shear', 6), {'theta': 0, 'shear': 6, 'ratio': 1.00}),
        
        # Perspective tests
        ('P1', 'Perspective ratio 0.98', ('perspective', 0.98), {'theta': 0, 'shear': 0, 'ratio': 0.98}),
        ('P2', 'Perspective ratio 0.95', ('perspective', 0.95), {'theta': 0, 'shear': 0, 'ratio': 0.95}),
        ('P3', 'Perspective ratio 0.90', ('perspective', 0.90), {'theta': 0, 'shear': 0, 'ratio': 0.90}),
    ]
    
    results = []
    
    for case_id, description, transform, expected_metrics in test_cases:
        if verbose:
            print(f"\n[{case_id}] {description}")
        
        # Apply transform
        if transform is None:
            # Reference image (no transform)
            output = reference.copy()
        elif transform[0] == 'rotate':
            output = apply_rotation(reference, transform[1])
            if verbose:
                print(f"  Applied rotation: {transform[1]:+.1f}°")
        elif transform[0] == 'shear':
            output = apply_shear(reference, transform[1], axis='x')
            if verbose:
                print(f"  Applied shear: {transform[1]:.1f}°")
        elif transform[0] == 'perspective':
            output = apply_perspective(reference, transform[1], transform[1])
            if verbose:
                print(f"  Applied perspective: ratio={transform[1]:.2f}")
        else:
            raise ValueError(f"Unknown transform: {transform[0]}")
        
        # Save output
        filename = f"{case_id}_{description.lower().replace(' ', '_').replace('°', 'deg')}.png"
        output_path = os.path.join(output_dir, filename)
        cv2.imwrite(output_path, output)
        
        if verbose:
            print(f"  Saved: {filename}")
        
        results.append({
            'case_id': case_id,
            'description': description,
            'filename': filename,
            'path': output_path,
            'expected': expected_metrics
        })
    
    # Generate README
    readme_path = os.path.join(output_dir, 'README.md')
    generate_readme(readme_path, results, input_path)
    
    if verbose:
        print(f"\n✓ Generated {len(results)} test variants")
        print(f"✓ Saved to: {output_dir}")
        print(f"✓ README: {readme_path}")
    
    return results


def generate_readme(output_path: str, results: list, reference_path: str):
    """Generate README documentation for test fixtures.
    
    Args:
        output_path: Path to README.md
        results: List of generated test case results
        reference_path: Path to reference ballot
    """
    with open(output_path, 'w') as f:
        f.write("# Synthetic Ballot Test Fixtures\n\n")
        f.write("Generated test ballots with known geometric distortions for quality gate validation.\n\n")
        f.write(f"**Reference Ballot:** `{os.path.basename(reference_path)}`\n\n")
        f.write("## Test Matrix\n\n")
        f.write("| Case ID | Description | Expected θ | Expected Shear | Expected Ratio | Verdict |\n")
        f.write("|---------|-------------|------------|----------------|----------------|----------|\n")
        
        # Map expected metrics to verdicts
        for r in results:
            exp = r['expected']
            
            # Determine expected verdict
            theta_abs = abs(exp['theta'])
            if theta_abs <= 3 and exp['shear'] <= 2 and exp['ratio'] >= 0.95:
                verdict = 'Green'
            elif theta_abs <= 10 and exp['shear'] <= 6 and exp['ratio'] >= 0.90:
                verdict = 'Amber'
            else:
                verdict = 'Red'
            
            f.write(f"| {r['case_id']} | {r['description']} | "
                   f"~{exp['theta']:+.0f}° | ~{exp['shear']:.0f}° | "
                   f"~{exp['ratio']:.2f} | {verdict} |\n")
        
        f.write("\n## Files\n\n")
        for r in results:
            f.write(f"- **{r['filename']}** - {r['description']}\n")
        
        f.write("\n## Acceptance Thresholds\n\n")
        f.write("Per `SKEW_ROTATION_TEST_SCENARIO.md`:\n\n")
        f.write("- **Rotation θ**: Green ≤3°, Amber 3-10°, Red >10°\n")
        f.write("- **Shear**: Green ≤2°, Amber 2-6°, Red >6°\n")
        f.write("- **Aspect Ratio**: Green ≥0.95, Amber 0.90-0.95, Red <0.90\n")
        f.write("- **Reproj Error**: Green <1.5px, Amber 1.5-3px, Red >3px\n")
        
        f.write("\n## Usage\n\n")
        f.write("```bash\n")
        f.write("# Test with quality metrics\n")
        f.write("cd packages/omr-appreciation/omr-python\n")
        f.write("python3 appreciate.py --input ../../../storage/.../U0_reference_upright.png\n\n")
        f.write("# Run full test suite\n")
        f.write("./scripts/test-omr-appreciation.sh\n")
        f.write("```\n")
        
        f.write("\n---\n")
        f.write(f"\n*Generated by synthesize_ballot_variants.py*\n")


def main():
    parser = argparse.ArgumentParser(
        description='Generate synthetic ballot variants with known distortions',
        formatter_class=argparse.RawDescriptionHelpFormatter,
        epilog="""
Examples:
  # Generate from test ballot
  python3 synthesize_ballot_variants.py \\
    --input packages/omr-appreciation/examples/test_ballot.png \\
    --output storage/app/tests/omr-appreciation/fixtures/skew-rotation

  # Quiet mode
  python3 synthesize_ballot_variants.py -i test.png -o fixtures/ --quiet
"""
    )
    
    parser.add_argument('-i', '--input', required=True,
                       help='Path to reference ballot image')
    parser.add_argument('-o', '--output', required=True,
                       help='Output directory for generated variants')
    parser.add_argument('-q', '--quiet', action='store_true',
                       help='Suppress progress output')
    
    args = parser.parse_args()
    
    try:
        results = generate_test_variants(
            args.input,
            args.output,
            verbose=not args.quiet
        )
        
        if not args.quiet:
            print("\n" + "=" * 60)
            print("✅ SUCCESS")
            print("=" * 60)
            print(f"Generated {len(results)} test fixtures")
            print(f"Location: {args.output}")
            print("\nNext steps:")
            print("1. Review generated images")
            print("2. Run: ./scripts/test-omr-appreciation.sh")
            print("3. Check quality metrics for each fixture")
        
        return 0
        
    except Exception as e:
        print(f"❌ ERROR: {e}", file=sys.stderr)
        if not args.quiet:
            import traceback
            traceback.print_exc()
        return 1


if __name__ == '__main__':
    import sys
    sys.exit(main())
