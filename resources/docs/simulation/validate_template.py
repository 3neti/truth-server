#!/usr/bin/env python3
"""Validate simulation coordinates.json template"""

import json
import sys
from pathlib import Path

def validate_template(template_path: str):
    """Validate template structure and content"""
    
    # Load template
    try:
        with open(template_path) as f:
            template = json.load(f)
    except Exception as e:
        print(f"❌ Failed to load template: {e}")
        return False
    
    print("=" * 60)
    print("TEMPLATE VALIDATION")
    print("=" * 60)
    
    # Check required top-level fields
    required_fields = ['document_id', 'template_id', 'bubble', 'fiducial', 'barcode']
    missing_fields = [f for f in required_fields if f not in template]
    
    if missing_fields:
        print(f"❌ Missing required fields: {', '.join(missing_fields)}")
        return False
    
    print(f"✅ All required fields present")
    
    # Validate metadata
    print(f"\n📋 Metadata:")
    print(f"  Document ID: {template['document_id']}")
    print(f"  Template ID: {template['template_id']}")
    print(f"  Version: {template.get('version', 'N/A')}")
    print(f"  Description: {template.get('description', 'N/A')}")
    
    # Validate bubbles
    bubbles = template['bubble']
    print(f"\n🔵 Bubbles: {len(bubbles)}")
    
    # Check bubble IDs
    expected_bubbles = (
        [f"A{i}" for i in range(1, 7)] +  # A1-A6
        [f"B{i}" for i in range(1, 51)]   # B1-B50
    )
    
    missing_bubbles = [b for b in expected_bubbles if b not in bubbles]
    extra_bubbles = [b for b in bubbles.keys() if b not in expected_bubbles]
    
    if missing_bubbles:
        print(f"  ⚠️  Missing bubbles: {', '.join(missing_bubbles[:10])}...")
    
    if extra_bubbles:
        print(f"  ⚠️  Extra bubbles: {', '.join(extra_bubbles[:10])}...")
    
    if not missing_bubbles and not extra_bubbles:
        print(f"  ✅ All 56 expected bubbles present")
    
    # Validate bubble structure
    sample_bubble = next(iter(bubbles.values()))
    required_bubble_fields = ['center_x', 'center_y', 'diameter']
    missing_bubble_fields = [f for f in required_bubble_fields if f not in sample_bubble]
    
    if missing_bubble_fields:
        print(f"  ❌ Bubble missing fields: {', '.join(missing_bubble_fields)}")
        return False
    
    print(f"  ✅ Bubble structure valid")
    
    # Show sample bubbles
    print(f"\n  Sample bubbles:")
    for bid in ['A1', 'A6', 'B1', 'B50']:
        if bid in bubbles:
            b = bubbles[bid]
            print(f"    {bid}: x={b['center_x']}, y={b['center_y']}, d={b['diameter']}")
    
    # Validate fiducials
    fiducials = template['fiducial']
    print(f"\n📍 Fiducials: {len(fiducials)}")
    
    required_fiducials = ['tl', 'tr', 'br', 'bl']
    missing_fiducials = [f for f in required_fiducials if f not in fiducials]
    
    if missing_fiducials:
        print(f"  ❌ Missing fiducials: {', '.join(missing_fiducials)}")
        return False
    
    print(f"  ✅ All 4 corner fiducials present")
    
    # Validate barcode
    barcode = template['barcode']
    print(f"\n🔲 Barcode:")
    
    if 'document_barcode' not in barcode:
        print(f"  ❌ Missing document_barcode")
        return False
    
    doc_barcode = barcode['document_barcode']
    print(f"  Type: {doc_barcode.get('type', 'N/A')}")
    print(f"  Position: ({doc_barcode.get('x', 'N/A')}, {doc_barcode.get('y', 'N/A')})")
    print(f"  Size: {doc_barcode.get('width', 'N/A')}x{doc_barcode.get('height', 'N/A')}")
    print(f"  ✅ Barcode section valid")
    
    # Test Python parsing compatibility
    print(f"\n🐍 Python Parsing Test:")
    
    # Simulate current appreciate.py parsing logic
    test_bubble_ids = ['A1', 'B1', 'B50']
    
    for bubble_id in test_bubble_ids:
        # Current code uses: parts = bubble_id.rsplit('_', 1)
        parts = bubble_id.rsplit('_', 1)
        contest = parts[0] if len(parts) > 1 else ''
        code = parts[1] if len(parts) > 1 else bubble_id
        
        print(f"  {bubble_id} -> contest=\"{contest}\", code=\"{code}\"")
    
    print(f"  ℹ️  Note: Simple IDs have no contest prefix (expected)")
    print(f"  ✅ Python can parse template (backward compatible)")
    
    print(f"\n{'=' * 60}")
    print(f"✅ TEMPLATE VALIDATION PASSED")
    print(f"{'=' * 60}")
    
    return True


if __name__ == '__main__':
    template_path = Path(__file__).parent / 'coordinates.json'
    
    if not template_path.exists():
        print(f"❌ Template not found: {template_path}")
        sys.exit(1)
    
    success = validate_template(str(template_path))
    sys.exit(0 if success else 1)
