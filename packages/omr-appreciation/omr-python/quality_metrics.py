"""Quality metrics extraction from homography matrices.

This module extracts quality metrics for ballot alignment validation:
- Rotation angle (θ)
- Shear angle
- Aspect ratio distortion (ratio_tb, ratio_lr)
- Reprojection error

Based on acceptance thresholds from SKEW_ROTATION_TEST_SCENARIO.md
"""

import numpy as np
import cv2
from typing import Dict, Tuple, Optional, List


def decompose_homography_to_rotation(H: np.ndarray) -> float:
    """Extract rotation angle from homography matrix.
    
    Args:
        H: 3x3 homography matrix
        
    Returns:
        Rotation angle in degrees (positive = counterclockwise)
    """
    # Decompose homography to get rotation component
    # H = s * R * [1 0 tx; 0 1 ty; 0 0 1]
    # Extract rotation from upper-left 2x2 submatrix
    
    # Normalize by H[2,2] to get proper projective transform
    H_norm = H / H[2, 2]
    
    # Extract rotation from upper-left 2x2
    rotation_matrix = H_norm[:2, :2]
    
    # Compute angle from rotation matrix using atan2
    # For rotation matrix [[cos θ, -sin θ], [sin θ, cos θ]]
    theta_rad = np.arctan2(rotation_matrix[1, 0], rotation_matrix[0, 0])
    theta_deg = np.degrees(theta_rad)
    
    return theta_deg


def compute_shear_angle(H: np.ndarray) -> float:
    """Compute shear angle from homography matrix.
    
    Shear represents how much the image is "tilted" along one axis.
    
    Args:
        H: 3x3 homography matrix
        
    Returns:
        Shear angle in degrees
    """
    H_norm = H / H[2, 2]
    
    # Extract the 2x2 transformation matrix
    A = H_norm[:2, :2]
    
    # Compute SVD to separate rotation, scale, and shear
    U, S, Vt = np.linalg.svd(A)
    
    # Shear can be estimated from the difference in singular values
    # and the rotation of the transformation
    shear_matrix = U @ np.diag(S) @ Vt
    
    # Calculate shear angle from off-diagonal elements
    # Shear angle ≈ arctan(A[0,1] / A[0,0]) for horizontal shear
    shear_x = np.arctan2(A[0, 1], A[0, 0])
    shear_y = np.arctan2(A[1, 0], A[1, 1])
    
    # Use the maximum absolute shear
    shear_rad = max(abs(shear_x), abs(shear_y))
    shear_deg = np.degrees(shear_rad)
    
    return shear_deg


def compute_aspect_ratios(src_points: np.ndarray, H: np.ndarray, 
                         target_width: int, target_height: int) -> Dict[str, float]:
    """Compute aspect ratio distortion metrics.
    
    Measures how much perspective distortion affects top/bottom and left/right edges.
    
    Args:
        src_points: Source corner points (4x2 array) [TL, TR, BR, BL]
        H: 3x3 homography matrix
        target_width: Expected output width
        target_height: Expected output height
        
    Returns:
        Dict with ratio_tb (top/bottom edge ratio) and ratio_lr (left/right edge ratio)
    """
    # Transform source points through homography
    src_homogeneous = np.column_stack([src_points, np.ones(4)])
    dst_homogeneous = (H @ src_homogeneous.T).T
    
    # Convert from homogeneous coordinates
    dst_points = dst_homogeneous[:, :2] / dst_homogeneous[:, 2:3]
    
    # Expected destination points (corners of target rectangle)
    expected_dst = np.array([
        [0, 0],                              # TL
        [target_width - 1, 0],               # TR
        [target_width - 1, target_height - 1],  # BR
        [0, target_height - 1]               # BL
    ], dtype=np.float32)
    
    # Compute edge lengths in transformed space
    top_edge = np.linalg.norm(dst_points[1] - dst_points[0])
    bottom_edge = np.linalg.norm(dst_points[2] - dst_points[3])
    left_edge = np.linalg.norm(dst_points[3] - dst_points[0])
    right_edge = np.linalg.norm(dst_points[2] - dst_points[1])
    
    # Expected edge lengths
    expected_top = np.linalg.norm(expected_dst[1] - expected_dst[0])
    expected_bottom = np.linalg.norm(expected_dst[2] - expected_dst[3])
    expected_left = np.linalg.norm(expected_dst[3] - expected_dst[0])
    expected_right = np.linalg.norm(expected_dst[2] - expected_dst[1])
    
    # Compute ratios (smaller/larger for each pair)
    ratio_tb = min(top_edge, bottom_edge) / max(top_edge, bottom_edge) if max(top_edge, bottom_edge) > 0 else 0
    ratio_lr = min(left_edge, right_edge) / max(left_edge, right_edge) if max(left_edge, right_edge) > 0 else 0
    
    return {
        'ratio_tb': ratio_tb,
        'ratio_lr': ratio_lr,
        'top_edge': top_edge,
        'bottom_edge': bottom_edge,
        'left_edge': left_edge,
        'right_edge': right_edge,
    }


def compute_reprojection_error(src_points: np.ndarray, dst_points: np.ndarray, 
                               H: np.ndarray) -> float:
    """Compute reprojection error for homography fit quality.
    
    Measures how accurately the homography transforms source to destination points.
    
    Args:
        src_points: Source corner points (Nx2)
        dst_points: Destination corner points (Nx2)
        H: 3x3 homography matrix
        
    Returns:
        Mean reprojection error in pixels
    """
    # Transform source points through homography
    src_homogeneous = np.column_stack([src_points, np.ones(len(src_points))])
    projected_homogeneous = (H @ src_homogeneous.T).T
    
    # Convert from homogeneous coordinates
    projected = projected_homogeneous[:, :2] / projected_homogeneous[:, 2:3]
    
    # Compute Euclidean distance between projected and actual destination
    errors = np.linalg.norm(projected - dst_points, axis=1)
    mean_error = np.mean(errors)
    
    return mean_error


def compute_quality_metrics(src_points: np.ndarray, H: np.ndarray,
                           target_width: int, target_height: int) -> Dict[str, float]:
    """Compute all quality metrics from homography and source points.
    
    Args:
        src_points: Source corner points (4x2 array) [TL, TR, BR, BL]
        H: 3x3 homography matrix
        target_width: Expected output width
        target_height: Expected output height
        
    Returns:
        Dictionary containing all quality metrics:
        - theta_deg: Rotation angle in degrees
        - shear_deg: Shear angle in degrees
        - ratio_tb: Top/bottom edge ratio
        - ratio_lr: Left/right edge ratio
        - reproj_error_px: Mean reprojection error in pixels
    """
    # Expected destination points (ideal corners)
    dst_points = np.array([
        [0, 0],
        [target_width - 1, 0],
        [target_width - 1, target_height - 1],
        [0, target_height - 1]
    ], dtype=np.float32)
    
    # Compute individual metrics
    theta = decompose_homography_to_rotation(H)
    shear = compute_shear_angle(H)
    ratios = compute_aspect_ratios(src_points, H, target_width, target_height)
    reproj_error = compute_reprojection_error(src_points, dst_points, H)
    
    return {
        'theta_deg': theta,
        'shear_deg': shear,
        'ratio_tb': ratios['ratio_tb'],
        'ratio_lr': ratios['ratio_lr'],
        'reproj_error_px': reproj_error,
        # Additional diagnostic info
        'top_edge': ratios['top_edge'],
        'bottom_edge': ratios['bottom_edge'],
        'left_edge': ratios['left_edge'],
        'right_edge': ratios['right_edge'],
    }


def check_quality_thresholds(metrics: Dict[str, float]) -> Dict[str, str]:
    """Apply quality thresholds from SKEW_ROTATION_TEST_SCENARIO.md.
    
    Thresholds:
    - Rotation θ: Green ≤3°, Amber 3-10°, Red >10°
    - Shear: Green ≤2°, Amber 2-6°, Red >6°
    - Aspect ratios: Green ≥0.95, Amber 0.90-0.95, Red <0.90
    - Reproj error: Green <1.5px, Amber 1.5-3px, Red >3px
    
    Args:
        metrics: Dictionary of computed quality metrics
        
    Returns:
        Dictionary mapping metric names to verdicts ('green', 'amber', 'red')
    """
    verdicts = {}
    
    # Rotation threshold
    theta_abs = abs(metrics['theta_deg'])
    if theta_abs <= 3.0:
        verdicts['theta'] = 'green'
    elif theta_abs <= 10.0:
        verdicts['theta'] = 'amber'
    else:
        verdicts['theta'] = 'red'
    
    # Shear threshold
    if metrics['shear_deg'] <= 2.0:
        verdicts['shear'] = 'green'
    elif metrics['shear_deg'] <= 6.0:
        verdicts['shear'] = 'amber'
    else:
        verdicts['shear'] = 'red'
    
    # Aspect ratio thresholds (both ratios must pass)
    min_ratio = min(metrics['ratio_tb'], metrics['ratio_lr'])
    if min_ratio >= 0.95:
        verdicts['aspect_ratio'] = 'green'
    elif min_ratio >= 0.90:
        verdicts['aspect_ratio'] = 'amber'
    else:
        verdicts['aspect_ratio'] = 'red'
    
    # Reprojection error threshold
    if metrics['reproj_error_px'] < 1.5:
        verdicts['reproj_error'] = 'green'
    elif metrics['reproj_error_px'] < 3.0:
        verdicts['reproj_error'] = 'amber'
    else:
        verdicts['reproj_error'] = 'red'
    
    # Overall verdict (worst of all metrics)
    verdict_priority = {'green': 0, 'amber': 1, 'red': 2}
    overall_verdict = max(verdicts.values(), key=lambda v: verdict_priority[v])
    verdicts['overall'] = overall_verdict
    
    return verdicts


def format_quality_report(metrics: Dict[str, float], verdicts: Dict[str, str]) -> str:
    """Format quality metrics as human-readable report.
    
    Args:
        metrics: Computed quality metrics
        verdicts: Quality threshold verdicts
        
    Returns:
        Formatted string report
    """
    # Emoji indicators
    indicators = {
        'green': '✅',
        'amber': '⚠️',
        'red': '❌'
    }
    
    report = "Quality Metrics Report\n"
    report += "=" * 50 + "\n"
    report += f"{indicators[verdicts['theta']]} Rotation:      θ = {metrics['theta_deg']:+6.2f}° ({verdicts['theta']})\n"
    report += f"{indicators[verdicts['shear']]} Shear:         {metrics['shear_deg']:6.2f}° ({verdicts['shear']})\n"
    report += f"{indicators[verdicts['aspect_ratio']]} Aspect Ratio:  TB={metrics['ratio_tb']:.3f} LR={metrics['ratio_lr']:.3f} ({verdicts['aspect_ratio']})\n"
    report += f"{indicators[verdicts['reproj_error']]} Reproj Error:  {metrics['reproj_error_px']:.2f} px ({verdicts['reproj_error']})\n"
    report += "=" * 50 + "\n"
    report += f"Overall: {indicators[verdicts['overall']]} {verdicts['overall'].upper()}\n"
    
    return report


if __name__ == "__main__":
    # Self-test with identity homography
    print("Quality Metrics Self-Test")
    print("=" * 50)
    
    # Perfect alignment (identity)
    src = np.array([[0, 0], [100, 0], [100, 100], [0, 100]], dtype=np.float32)
    H_identity = np.eye(3)
    
    metrics = compute_quality_metrics(src, H_identity, 100, 100)
    verdicts = check_quality_thresholds(metrics)
    print("\n1. Identity Transform (perfect alignment):")
    print(format_quality_report(metrics, verdicts))
    
    # Rotated 5 degrees
    angle = np.radians(5)
    H_rot = np.array([
        [np.cos(angle), -np.sin(angle), 0],
        [np.sin(angle), np.cos(angle), 0],
        [0, 0, 1]
    ])
    metrics_rot = compute_quality_metrics(src, H_rot, 100, 100)
    verdicts_rot = check_quality_thresholds(metrics_rot)
    print("\n2. 5° Rotation:")
    print(format_quality_report(metrics_rot, verdicts_rot))
    
    print("\n✓ Self-test complete")
