"""Shared utility functions for OMR appreciation."""

import json
from typing import Dict, List, Tuple


def load_template(template_path: str) -> Dict:
    """Load template JSON file."""
    with open(template_path, 'r') as f:
        return json.load(f)


def get_roi_coordinates(zone: Dict) -> Tuple[int, int, int, int]:
    """Extract ROI coordinates from zone definition.
    
    Returns (x, y, width, height) tuple.
    """
    return (
        int(zone['x']),
        int(zone['y']),
        int(zone['width']),
        int(zone['height'])
    )


def output_json(data: Dict) -> None:
    """Output data as JSON to stdout."""
    print(json.dumps(data, indent=2))
