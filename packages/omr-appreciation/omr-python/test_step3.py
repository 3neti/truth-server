#!/usr/bin/env python3
"""Test Step 3 implementation - dual-mode bubble ID support"""

import sys
from pathlib import Path
from bubble_metadata import load_bubble_metadata, BubbleMetadata
from typing import Dict, Optional

def test_metadata_loading():
    """Test 1: Metadata loading from simulation config"""
    print("=" * 60)
    print("TEST 1: Metadata Loading")
    print("=" * 60)
    
    config_path = "../../../resources/docs/simulation/config"
    metadata = load_bubble_metadata(config_path)
    
    assert metadata.available, "Metadata should be available"
    assert len(metadata.metadata) == 56, f"Expected 56 bubbles, got {len(metadata.metadata)}"
    
    # Test specific bubble lookups
    a1 = metadata.get('A1')
    assert a1 is not None, "A1 should exist"
    assert a1['candidate_name'] == 'Leonardo DiCaprio', f"Wrong name: {a1['candidate_name']}"
    assert a1['candidate_code'] == 'LD_001', f"Wrong code: {a1['candidate_code']}"
    assert a1['position_code'] == 'PUNONG_BARANGAY-1402702011', f"Wrong position: {a1['position_code']}"
    
    b50 = metadata.get('B50')
    assert b50 is not None, "B50 should exist"
    assert b50['candidate_name'] == 'Helen Mirren', f"Wrong name: {b50['candidate_name']}"
    
    print("✅ Metadata loading works correctly")
    print(f"   Loaded {len(metadata.metadata)} bubbles")
    print(f"   Sample: A1 = {a1['candidate_name']}")
    print(f"   Sample: B50 = {b50['candidate_name']}")
    return True


def test_is_simple_id():
    """Test 2: Simple ID detection"""
    print("\n" + "=" * 60)
    print("TEST 2: Simple ID Detection")
    print("=" * 60)
    
    metadata = BubbleMetadata()
    
    # Simple IDs (no underscore)
    assert metadata.is_simple_id('A1'), "A1 should be simple"
    assert metadata.is_simple_id('B50'), "B50 should be simple"
    assert metadata.is_simple_id('C23'), "C23 should be simple"
    
    # Verbose IDs (with underscore)
    assert not metadata.is_simple_id('PRESIDENT_LD_001'), "Should be verbose"
    assert not metadata.is_simple_id('SENATOR_ES_002'), "Should be verbose"
    
    print("✅ Simple ID detection works correctly")
    return True


def test_without_metadata():
    """Test 3: Graceful degradation without metadata"""
    print("\n" + "=" * 60)
    print("TEST 3: Without Metadata (Backward Compatibility)")
    print("=" * 60)
    
    metadata = load_bubble_metadata(None)
    
    assert not metadata.available, "Metadata should not be available"
    assert len(metadata.metadata) == 0, "Should have no metadata"
    assert metadata.get('A1') is None, "Lookup should return None"
    
    print("✅ Graceful degradation works (backward compatible)")
    return True


def test_convert_bubbles_simulation():
    """Test 4: convert_bubbles_to_zones with simulation template"""
    print("\n" + "=" * 60)
    print("TEST 4: convert_bubbles_to_zones with Simple IDs")
    print("=" * 60)
    
    # Import here to avoid circular imports
    from appreciate_live import convert_bubbles_to_zones
    from utils import load_template
    
    # Load simulation template
    template_path = "../../../resources/docs/simulation/coordinates.json"
    template = load_template(template_path)
    
    # Load metadata
    config_path = "../../../resources/docs/simulation/config"
    metadata = load_bubble_metadata(config_path)
    
    # Convert with metadata
    zones = convert_bubbles_to_zones(template['bubble'], bubble_metadata=metadata)
    
    assert len(zones) == 56, f"Expected 56 zones, got {len(zones)}"
    
    # Check a zone
    a1_zone = next((z for z in zones if z['id'] == 'A1'), None)
    assert a1_zone is not None, "A1 zone should exist"
    assert a1_zone['contest'] == 'PUNONG_BARANGAY-1402702011', f"Wrong contest: {a1_zone['contest']}"
    assert a1_zone['code'] == 'LD_001', f"Wrong code: {a1_zone['code']}"
    
    print("✅ convert_bubbles_to_zones works with metadata")
    print(f"   Zones: {len(zones)}")
    print(f"   Sample: {a1_zone['id']} -> contest={a1_zone['contest'][:20]}..., code={a1_zone['code']}")
    return True


def test_convert_bubbles_without_metadata():
    """Test 5: convert_bubbles_to_zones without metadata (fallback)"""
    print("\n" + "=" * 60)
    print("TEST 5: convert_bubbles_to_zones without Metadata (Fallback)")
    print("=" * 60)
    
    from appreciate_live import convert_bubbles_to_zones
    from utils import load_template
    
    # Load simulation template
    template_path = "../../../resources/docs/simulation/coordinates.json"
    template = load_template(template_path)
    
    # Convert WITHOUT metadata
    zones = convert_bubbles_to_zones(template['bubble'], bubble_metadata=None)
    
    assert len(zones) == 56, f"Expected 56 zones, got {len(zones)}"
    
    # Check fallback behavior - simple IDs have no contest
    a1_zone = next((z for z in zones if z['id'] == 'A1'), None)
    assert a1_zone is not None, "A1 zone should exist"
    assert a1_zone['contest'] == '', f"Contest should be empty for simple ID: {a1_zone['contest']}"
    assert a1_zone['code'] == 'A1', f"Code should be full ID: {a1_zone['code']}"
    
    print("✅ convert_bubbles_to_zones works without metadata (fallback)")
    print(f"   Simple IDs fall back gracefully")
    print(f"   A1 -> contest='', code='A1'")
    return True


def test_get_candidate_name():
    """Test 6: get_candidate_name with metadata"""
    print("\n" + "=" * 60)
    print("TEST 6: get_candidate_name with Metadata")
    print("=" * 60)
    
    from appreciate_live import get_candidate_name
    
    # Load metadata
    config_path = "../../../resources/docs/simulation/config"
    metadata = load_bubble_metadata(config_path)
    
    # Test with simple ID
    name = get_candidate_name('A1', None, metadata)
    assert name == 'Leonardo DiCaprio', f"Wrong name: {name}"
    
    name = get_candidate_name('B50', None, metadata)
    assert name == 'Helen Mirren', f"Wrong name: {name}"
    
    print("✅ get_candidate_name works with metadata")
    print(f"   A1 -> Leonardo DiCaprio")
    print(f"   B50 -> Helen Mirren")
    return True


def run_all_tests():
    """Run all tests"""
    print("\n🧪 STEP 3 IMPLEMENTATION TESTS")
    print("=" * 60)
    print("Testing dual-mode bubble ID support (simple + verbose)")
    print("=" * 60)
    
    tests = [
        ("Metadata Loading", test_metadata_loading),
        ("Simple ID Detection", test_is_simple_id),
        ("Without Metadata", test_without_metadata),
        ("convert_bubbles (with metadata)", test_convert_bubbles_simulation),
        ("convert_bubbles (without metadata)", test_convert_bubbles_without_metadata),
        ("get_candidate_name", test_get_candidate_name),
    ]
    
    passed = 0
    failed = 0
    
    for test_name, test_func in tests:
        try:
            test_func()
            passed += 1
        except Exception as e:
            print(f"\n❌ TEST FAILED: {test_name}")
            print(f"   Error: {e}")
            import traceback
            traceback.print_exc()
            failed += 1
    
    print("\n" + "=" * 60)
    print(f"TEST SUMMARY: {passed} passed, {failed} failed")
    print("=" * 60)
    
    if failed == 0:
        print("✅ ALL TESTS PASSED - Step 3 implementation is working!")
        return 0
    else:
        print(f"❌ {failed} TESTS FAILED")
        return 1


if __name__ == '__main__':
    sys.exit(run_all_tests())
