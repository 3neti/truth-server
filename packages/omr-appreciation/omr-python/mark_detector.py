"""Mark detection for OMR zones."""

import cv2
import numpy as np
from typing import Dict, List, Optional
from utils import get_roi_coordinates


def transform_zone_coordinates(zones: List[Dict], inv_matrix: np.ndarray) -> List[Dict]:
    """Transform zone coordinates using inverse perspective matrix.
    
    Args:
        zones: List of zone definitions with x, y, width, height
        inv_matrix: 3x3 inverse perspective transform matrix
        
    Returns:
        List of zones with transformed coordinates
    """
    transformed_zones = []
    
    for zone in zones:
        # Get original zone coordinates
        x, y, width, height = get_roi_coordinates(zone)
        
        # Transform center point
        center_x = x + width / 2
        center_y = y + height / 2
        
        # Create point array for transformation
        point = np.array([[center_x, center_y]], dtype=np.float32).reshape(-1, 1, 2)
        transformed_point = cv2.perspectiveTransform(point, inv_matrix)
        
        # Extract transformed coordinates
        new_center_x = transformed_point[0][0][0]
        new_center_y = transformed_point[0][0][1]
        
        # Calculate new top-left coordinates
        new_x = int(new_center_x - width / 2)
        new_y = int(new_center_y - height / 2)
        
        # Create transformed zone (preserve all original fields)
        transformed_zone = zone.copy()
        transformed_zone['x'] = new_x
        transformed_zone['y'] = new_y
        
        transformed_zones.append(transformed_zone)
    
    return transformed_zones


def calculate_mark_metrics(image: np.ndarray, x: int, y: int, width: int, height: int) -> dict:
    """Calculate comprehensive metrics for a mark zone.
    
    Args:
        image: Aligned grayscale image
        x, y: Top-left coordinates of ROI
        width, height: ROI dimensions
        
    Returns:
        Dictionary with fill_ratio, confidence, uniformity, and other metrics
    """
    # Extract ROI
    roi = image[y:y+height, x:x+width]
    
    if roi.size == 0:
        return {
            'fill_ratio': 0.0,
            'confidence': 0.0,
            'uniformity': 0.0,
            'mean_darkness': 0.0,
            'std_dev': 0.0
        }
    
    # Calculate basic statistics
    mean_val = np.mean(roi)
    std_dev = np.std(roi)
    min_val = np.min(roi)
    max_val = np.max(roi)
    
    # Use Otsu's method for automatic threshold calculation
    threshold_value, binary = cv2.threshold(roi, 0, 255, cv2.THRESH_BINARY_INV + cv2.THRESH_OTSU)
    
    # Calculate fill ratio
    dark_pixels = np.count_nonzero(binary)
    total_pixels = roi.size
    fill_ratio = dark_pixels / total_pixels
    
    # Calculate confidence based on how clear the mark is
    # High confidence = clear distinction between marked and unmarked
    # Low confidence = ambiguous (e.g., partial marks, smudges)
    
    # Confidence factors:
    # 1. Distance from decision boundary (threshold)
    distance_from_threshold = abs(fill_ratio - 0.3)  # 0.3 is typical threshold
    clarity_score = min(distance_from_threshold / 0.3, 1.0)  # 0 to 1
    
    # 2. Bimodal distribution indicates clear mark
    # For perspective-transformed images, high std_dev is NORMAL for filled marks
    # Check if we have bimodal distribution (both dark and light pixels)
    separation = min((max_val - min_val) / 255.0, 1.0)
    
    # 3. For filled marks, check if we have substantial dark pixels
    # For unfilled marks, check if we have mostly light pixels
    if fill_ratio > 0.3:  # Likely filled
        # High confidence if we have good dark pixel concentration
        dark_concentration = min(fill_ratio / 0.5, 1.0)  # Normalize to 0.5 as "perfect"
        quality_score = dark_concentration
    else:  # Likely unfilled
        # High confidence if fill ratio is very low
        quality_score = 1.0 - min(fill_ratio / 0.15, 1.0)
    
    # 4. Uniformity adjusted for expected bimodality after transform
    # High std_dev is expected for filled marks after perspective transform
    # Only penalize if std_dev is unusually low (possible scanning artifact)
    if fill_ratio > 0.3 and std_dev > 60:
        # This is expected for filled marks after transform
        uniformity = 0.9
    else:
        # For unfilled or low std_dev, use original calculation
        uniformity = 1.0 - min(std_dev / 127.0, 1.0)
    
    # Combined confidence (weighted average)
    confidence = (clarity_score * 0.4 + quality_score * 0.3 + separation * 0.2 + uniformity * 0.1)
    
    # Mean darkness (inverted brightness, 0 = white, 255 = black)
    mean_darkness = 255 - mean_val
    
    return {
        'fill_ratio': fill_ratio,
        'confidence': confidence,
        'uniformity': uniformity,
        'mean_darkness': mean_darkness / 255.0,  # Normalize to 0-1
        'std_dev': std_dev,
        'otsu_threshold': threshold_value
    }


def detect_marks(image: np.ndarray, zones: List[Dict], threshold: float = 0.3, 
                inv_matrix: Optional[np.ndarray] = None) -> List[Dict]:
    """Detect filled marks in all zones with confidence metrics.
    
    Args:
        image: Image to analyze (BGR)
        zones: List of zone definitions from template (in template coordinate space)
        threshold: Fill ratio threshold to consider a mark as filled
        inv_matrix: Optional inverse perspective transform matrix for coordinate alignment.
                   If provided, zone coordinates will be transformed to match the distorted image.
        
    Returns:
        List of results with fill status, confidence, and quality metrics
    """
    # Transform zone coordinates if inverse matrix is provided
    if inv_matrix is not None:
        zones = transform_zone_coordinates(zones, inv_matrix)
    
    # Convert to grayscale
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    
    results = []
    
    for zone in zones:
        x, y, width, height = get_roi_coordinates(zone)
        metrics = calculate_mark_metrics(gray, x, y, width, height)
        
        fill_ratio = metrics['fill_ratio']
        confidence = metrics['confidence']
        
        # Determine fill status
        filled = fill_ratio >= threshold
        
        # Add warning flags for quality issues
        warnings = []
        if 0.15 < fill_ratio < 0.45:  # Ambiguous range
            warnings.append('ambiguous')
        if confidence < 0.5:
            warnings.append('low_confidence')
        if metrics['uniformity'] < 0.4:
            warnings.append('non_uniform')
        if fill_ratio > 0.7:
            warnings.append('overfilled')
        
        result = {
            'id': zone.get('id', ''),
            'contest': zone.get('contest', ''),
            'code': zone.get('code', zone.get('id', '')),
            'candidate': zone.get('candidate', ''),
            'filled': filled,
            'fill_ratio': round(fill_ratio, 3),
            'confidence': round(confidence, 3),
            'quality': {
                'uniformity': round(metrics['uniformity'], 3),
                'mean_darkness': round(metrics['mean_darkness'], 3),
                'std_dev': round(metrics['std_dev'], 2)
            },
            'warnings': warnings if warnings else None
        }
        
        results.append(result)
    
    return results
