#!/usr/bin/env python3
"""
Barcode Decoder Module

Extracts and decodes barcodes (Code128, QR, etc.) from ballot images using
coordinates from the template. Supports multiple decoders with fallback strategy.

Usage:
    from barcode_decoder import decode_barcode
    
    result = decode_barcode(image, barcode_coords, mm_to_px_ratio=11.811)
    print(result['document_id'])
"""

import cv2
import numpy as np
import subprocess
import tempfile
import os
from typing import Dict, Optional, Tuple


def extract_barcode_roi(
    image: np.ndarray, 
    barcode_coords: Dict, 
    mm_to_px_ratio: float = 11.811,
    padding: int = 50
) -> Tuple[np.ndarray, Dict]:
    """
    Extract barcode region of interest from image using coordinates.
    
    Args:
        image: Input image (BGR format from cv2.imread)
        barcode_coords: Dictionary with 'x', 'y', and 'type' from coordinates.json
        mm_to_px_ratio: Conversion ratio (default: 300 DPI = 11.811 px/mm)
        padding: Extra pixels around barcode region for better detection
        
    Returns:
        Tuple of (roi_image, roi_rect_dict)
        roi_rect_dict contains {x, y, width, height} in pixels
    """
    # Convert mm to pixels
    x_px = int(barcode_coords['x'] * mm_to_px_ratio)
    y_px = int(barcode_coords['y'] * mm_to_px_ratio)
    
    # Adjust ROI size based on barcode type
    barcode_type = barcode_coords.get('type', 'PDF417').upper()
    
    if 'QR' in barcode_type or 'QRCODE' in barcode_type:
        # QR codes are square (typically 12-15mm, but include padding and margin)
        size_mm = 40  # Generous size to capture QR code with quiet zone
        width_px = int(size_mm * mm_to_px_ratio)
        height_px = int(size_mm * mm_to_px_ratio)
    elif 'CODE128' in barcode_type:
        # Code128 is a 1D barcode (narrower than PDF417)
        width_px = int(60 * mm_to_px_ratio)
        height_px = int(20 * mm_to_px_ratio)
    else:
        # PDF417 and other 2D barcodes are typically ~100mm wide x 30mm tall
        width_px = int(100 * mm_to_px_ratio)
        height_px = int(30 * mm_to_px_ratio)
    
    # Apply padding and bounds checking
    h, w = image.shape[:2]
    x1 = max(0, x_px - padding)
    y1 = max(0, y_px - padding)
    x2 = min(w, x_px + width_px + padding)
    y2 = min(h, y_px + height_px + padding)
    
    roi = image[y1:y2, x1:x2]
    
    roi_rect = {
        'x': x1,
        'y': y1,
        'width': x2 - x1,
        'height': y2 - y1
    }
    
    return roi, roi_rect


def preprocess_roi(roi: np.ndarray, apply_clahe: bool = True, apply_sharpen: bool = True) -> np.ndarray:
    """
    Preprocess ROI to improve barcode detection success rate.
    
    Applies CLAHE (Contrast Limited Adaptive Histogram Equalization) and
    sharpening filter to enhance barcode visibility.
    
    Args:
        roi: Input ROI image (BGR or grayscale)
        apply_clahe: Whether to apply CLAHE enhancement
        apply_sharpen: Whether to apply sharpening filter
        
    Returns:
        Preprocessed grayscale image
    """
    # Convert to grayscale if needed
    if len(roi.shape) == 3:
        gray = cv2.cvtColor(roi, cv2.COLOR_BGR2GRAY)
    else:
        gray = roi.copy()
    
    # Apply CLAHE for contrast enhancement
    if apply_clahe:
        clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
        gray = clahe.apply(gray)
    
    # Apply sharpening kernel
    if apply_sharpen:
        sharpen_kernel = np.array([
            [0, -1, 0],
            [-1, 5, -1],
            [0, -1, 0]
        ], dtype=np.float32)
        gray = cv2.filter2D(gray, -1, sharpen_kernel)
    
    return gray


def decode_pdf417_pyzxing(roi: np.ndarray) -> Optional[Dict]:
    """
    Decode PDF417 barcode using pyzxing library (ZXing wrapper).
    
    Args:
        roi: Preprocessed ROI image (BGR or grayscale)
        
    Returns:
        Dictionary with decode result or None if not found:
        {
            'data': str,
            'type': str,
            'rect': None,
            'confidence': float
        }
    """
    try:
        from pyzxing import BarCodeReader
        
        # Save ROI to temporary file (pyzxing requires file path)
        with tempfile.NamedTemporaryFile(suffix='.png', delete=False) as tmp:
            tmp_path = tmp.name
            cv2.imwrite(tmp_path, roi)
        
        try:
            # Decode with pyzxing
            reader = BarCodeReader()
            results = reader.decode(tmp_path)
            
            # Clean up temp file
            os.unlink(tmp_path)
            
            # Look for PDF417 results
            for result in results:
                barcode_format = result.get('format', b'').decode('utf-8') if isinstance(result.get('format'), bytes) else str(result.get('format', ''))
                if 'PDF' in barcode_format.upper():
                    data = result.get('parsed', result.get('raw', b''))
                    if isinstance(data, bytes):
                        data = data.decode('utf-8', errors='replace')
                    
                    return {
                        'data': str(data).strip(),
                        'type': 'PDF417',
                        'rect': None,  # pyzxing doesn't provide position
                        'confidence': 1.0
                    }
            
            return None
        finally:
            # Ensure temp file cleanup even if decode fails
            if os.path.exists(tmp_path):
                os.unlink(tmp_path)
        
    except ImportError:
        # pyzxing not available
        return None
    except Exception as e:
        # Decode failed
        import sys
        print(f"pyzxing decode error: {e}", file=sys.stderr)
        return None


def decode_pdf417_pyzbar(roi: np.ndarray) -> Optional[Dict]:
    """
    Decode barcode using pyzbar library.
    
    Supports: Code128, Code39, QR Code, EAN, UPC, and more.
    Note: pyzbar/zbar does NOT support PDF417.
    
    Args:
        roi: Preprocessed ROI image (grayscale)
        
    Returns:
        Dictionary with decode result or None if not found
    """
    try:
        from pyzbar import pyzbar
        
        # Decode all barcodes in ROI
        barcodes = pyzbar.decode(roi)
        
        # Look for any supported barcode
        for barcode in barcodes:
            try:
                data = barcode.data.decode('utf-8', errors='replace').strip()
            except Exception:
                data = str(barcode.data)
            
            return {
                'data': data,
                'type': barcode.type,
                'rect': {
                    'x': barcode.rect.left,
                    'y': barcode.rect.top,
                    'width': barcode.rect.width,
                    'height': barcode.rect.height
                },
                'confidence': 0.9  # Slightly lower than pyzxing for PDF417
            }
        
        return None
        
    except ImportError:
        # pyzbar not available
        return None
    except Exception as e:
        # Decode failed
        import sys
        print(f"pyzbar decode error: {e}", file=sys.stderr)
        return None


def decode_pdf417_zxing(roi: np.ndarray) -> Optional[Dict]:
    """
    Decode PDF417 barcode using ZXing CLI as fallback.
    
    Args:
        roi: Preprocessed ROI image (grayscale)
        
    Returns:
        Dictionary with decode result or None if not found
    """
    try:
        # Save ROI to temporary file
        with tempfile.NamedTemporaryFile(suffix='.png', delete=False) as tmp:
            tmp_path = tmp.name
            cv2.imwrite(tmp_path, roi)
        
        # Run ZXing CLI
        result = subprocess.run(
            ['zxing', '--multi', tmp_path],
            capture_output=True,
            text=True,
            timeout=5
        )
        
        # Clean up temp file
        os.unlink(tmp_path)
        
        if result.returncode == 0:
            # Parse output: "file:path: PDF417: DATA"
            for line in result.stdout.strip().splitlines():
                if 'PDF417' in line:
                    parts = line.split(':', maxsplit=2)
                    if len(parts) >= 3:
                        data = parts[2].strip()
                        return {
                            'data': data,
                            'type': 'PDF417',
                            'rect': None,  # ZXing CLI doesn't provide rect
                            'confidence': 0.9  # Slightly lower than pyzbar
                        }
        
        return None
        
    except (FileNotFoundError, subprocess.TimeoutExpired, Exception):
        # ZXing not available or failed
        return None


def decode_barcode(
    image: np.ndarray,
    barcode_coords: Dict,
    mm_to_px_ratio: float = 11.811,
    metadata_fallback: Optional[str] = None
) -> Dict:
    """
    Main barcode decoding function with multi-decoder fallback strategy.
    
    Strategy:
    1. Try pyzbar on preprocessed ROI
    2. If fails, try pyzxing (PDF417 support)
    3. If fails, try ZXing CLI
    4. If fails, fall back to metadata from coordinates.json
    
    Args:
        image: Input ballot image (BGR format)
        barcode_coords: Barcode coordinates from coordinates.json
        mm_to_px_ratio: Pixel to mm conversion ratio (default for 300 DPI)
        metadata_fallback: Fallback document ID from coordinates.json
        
    Returns:
        Dictionary with:
        {
            'document_id': str or None,
            'decoder': 'pyzbar', 'pyzxing', 'zxing_cli', 'metadata', or 'none',
            'confidence': float (0.0 to 1.0),
            'rect': dict or None,
            'decoded': bool,
            'source': 'visual' or 'metadata',
            'barcode_type': str or None,
            'attempts': list of decoder names attempted,
            'roi_size': tuple of (width, height),
            'decode_time_ms': float
        }
    """
    import time
    start_time = time.time()
    
    result = {
        'document_id': None,
        'decoder': 'none',
        'confidence': 0.0,
        'rect': None,
        'decoded': False,
        'source': 'none',
        'barcode_type': None,
        'attempts': [],
        'roi_size': None,
        'decode_time_ms': 0.0
    }
    
    # Extract ROI
    try:
        roi, roi_rect = extract_barcode_roi(image, barcode_coords, mm_to_px_ratio)
        result['rect'] = roi_rect
        result['roi_size'] = (roi_rect['width'], roi_rect['height'])
    except Exception as e:
        import sys
        print(f"Failed to extract barcode ROI: {e}", file=sys.stderr)
        result['attempts'].append('roi_extraction_failed')
        # Fall back to metadata immediately if ROI extraction fails
        if metadata_fallback:
            result['document_id'] = metadata_fallback
            result['decoder'] = 'metadata'
            result['confidence'] = 1.0
            result['decoded'] = True
            result['source'] = 'metadata'
        result['decode_time_ms'] = (time.time() - start_time) * 1000
        return result
    
    # Preprocess ROI
    roi_processed = preprocess_roi(roi)
    
    # Try pyzbar first (supports Code128, QR, Code39, etc.)
    result['attempts'].append('pyzbar')
    decode_result = decode_pdf417_pyzbar(roi_processed)
    if decode_result:
        result['document_id'] = decode_result['data']
        result['decoder'] = 'pyzbar'
        result['confidence'] = decode_result['confidence']
        result['decoded'] = True
        result['source'] = 'visual'
        result['barcode_type'] = decode_result['type']
        result['decode_time_ms'] = (time.time() - start_time) * 1000
        return result
    
    # Try pyzxing fallback (supports PDF417, requires Java)
    result['attempts'].append('pyzxing')
    decode_result = decode_pdf417_pyzxing(roi_processed)
    if decode_result:
        result['document_id'] = decode_result['data']
        result['decoder'] = 'pyzxing'
        result['confidence'] = decode_result['confidence']
        result['decoded'] = True
        result['source'] = 'visual'
        result['barcode_type'] = decode_result['type']
        result['decode_time_ms'] = (time.time() - start_time) * 1000
        return result
    
    # Try ZXing CLI fallback (if installed)
    result['attempts'].append('zxing_cli')
    decode_result = decode_pdf417_zxing(roi_processed)
    if decode_result:
        result['document_id'] = decode_result['data']
        result['decoder'] = 'zxing_cli'
        result['confidence'] = decode_result['confidence']
        result['decoded'] = True
        result['source'] = 'visual'
        result['barcode_type'] = decode_result['type']
        result['decode_time_ms'] = (time.time() - start_time) * 1000
        return result
    
    # Fall back to metadata if visual decode failed
    result['attempts'].append('metadata_fallback')
    if metadata_fallback:
        result['document_id'] = metadata_fallback
        result['decoder'] = 'metadata'
        result['confidence'] = 1.0
        result['decoded'] = True
        result['source'] = 'metadata'
    
    result['decode_time_ms'] = (time.time() - start_time) * 1000
    return result


# Convenience function for CLI usage
if __name__ == "__main__":
    import sys
    import json
    
    if len(sys.argv) < 2:
        print("Usage: python barcode_decoder.py <image_path> [x_mm] [y_mm] [type]")
        print("Example: python barcode_decoder.py ballot.png 99 254 QRCODE")
        print("         python barcode_decoder.py ballot.png 75 264 PDF417")
        sys.exit(1)
    
    image_path = sys.argv[1]
    x_mm = float(sys.argv[2]) if len(sys.argv) > 2 else 75
    y_mm = float(sys.argv[3]) if len(sys.argv) > 3 else 254
    barcode_type = sys.argv[4] if len(sys.argv) > 4 else 'QRCODE'
    
    # Load image
    image = cv2.imread(image_path)
    if image is None:
        print(f"Error: Could not load image: {image_path}")
        sys.exit(1)
    
    # Decode
    barcode_coords = {'x': x_mm, 'y': y_mm, 'type': barcode_type}
    result = decode_barcode(image, barcode_coords)
    
    # Output JSON
    print(json.dumps(result, indent=2))
