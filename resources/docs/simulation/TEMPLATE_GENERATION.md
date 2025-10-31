# Simulation Template Generation

## Overview

This document describes the generation of `coordinates.json` template files with simple bubble IDs for the simulation barangay election.

## Command

```bash
php artisan election:generate-template --config-path=resources/docs/simulation/config
```

### Options

- `--config-path` - Path to directory containing `election.json` and `mapping.yaml`
- `--output` - Output path for `coordinates.json` (default: `resources/docs/simulation/coordinates.json`)

## Generated Template Structure

### Bubble IDs

The template uses **simple grid references** instead of verbose concatenated strings:

**Old Format (Verbose):**
```json
{
  "PRESIDENT_LD_001": {
    "center_x": 10.5,
    "center_y": 20.3,
    "diameter": 5.0
  }
}
```

**New Format (Simple):**
```json
{
  "A1": {
    "center_x": 30,
    "center_y": 80,
    "diameter": 5
  }
}
```

### Bubble Layout

For the simulation barangay election:

- **Row A (A1-A6):** 6 Punong Barangay candidates
  - Horizontal layout at y=80mm
  - Spacing: 25mm between bubbles
  
- **Row B (B1-B50):** 50 Sangguniang Barangay candidates
  - Grid layout starting at y=130mm
  - 10 bubbles per row
  - 15mm vertical spacing between rows

### Complete Template Structure

```json
{
  "document_id": "0102800000",
  "template_id": "simulation-barangay-v1",
  "version": "1.0.0",
  "description": "Simulation template with simple bubble IDs (A1-A6, B1-B50)",
  "ballot_size": {
    "width_mm": 210,
    "height_mm": 297
  },
  "bubble": {
    "A1": { "center_x": 30, "center_y": 80, "diameter": 5 },
    "A2": { "center_x": 55, "center_y": 80, "diameter": 5 },
    ...
    "B50": { "center_x": 255, "center_y": 190, "diameter": 5 }
  },
  "fiducial": {
    "tl": { "x": 8.5, "y": 8.5, "marker_id": 101, "type": "aruco", "dict": "DICT_4X4_100" },
    "tr": { "x": 201.5, "y": 8.5, "marker_id": 102, "type": "aruco", "dict": "DICT_4X4_100" },
    "br": { "x": 201.5, "y": 288.5, "marker_id": 103, "type": "aruco", "dict": "DICT_4X4_100" },
    "bl": { "x": 8.5, "y": 288.5, "marker_id": 104, "type": "aruco", "dict": "DICT_4X4_100" }
  },
  "barcode": {
    "document_barcode": {
      "x": 70, "y": 270, "width": 70, "height": 15,
      "type": "qr", "data": "SIMULATION-001"
    }
  }
}
```

## Integration with BubbleIdGenerator

The command uses `BubbleIdGenerator` service to ensure bubble IDs are consistent with `mapping.yaml`:

```php
$loader = new ElectionConfigLoader;
$generator = new BubbleIdGenerator($loader);
$metadata = $generator->generateBubbleMetadata();

foreach ($metadata as $bubbleId => $meta) {
    // Generate coordinates for $bubbleId (e.g., "A1", "B1")
    $bubbles[$bubbleId] = [
        'center_x' => calculateX($bubbleId),
        'center_y' => calculateY($bubbleId),
        'diameter' => 5.0,
    ];
}
```

## Validation

Run the validation script to verify template integrity:

```bash
python3 resources/docs/simulation/validate_template.py
```

### Validation Checks

✅ All required fields present (document_id, template_id, bubble, fiducial, barcode)  
✅ All 56 expected bubbles present (A1-A6, B1-B50)  
✅ Bubble structure valid (center_x, center_y, diameter)  
✅ All 4 corner fiducials present  
✅ Barcode section valid  
✅ Python parsing compatibility verified  

## Python Compatibility

The template is **backward compatible** with current Python parsing code:

### Current Python Code (appreciate.py)

```python
# Parse bubble_id like "PRESIDENT_LD_001" into contest and code
parts = bubble_id.rsplit('_', 1)
contest = parts[0] if len(parts) > 1 else ''
code = parts[1] if len(parts) > 1 else bubble_id
```

### Behavior with Simple IDs

```python
# bubble_id = "A1"
parts = "A1".rsplit('_', 1)  # ['A1']
contest = ''  # No underscore, so empty string
code = 'A1'   # Falls back to full bubble_id
```

**Result:** Python code continues to work, treating simple IDs as codes with empty contest strings.

## Usage Example

```bash
# 1. Generate template from simulation config
php artisan election:generate-template \
    --config-path=resources/docs/simulation/config

# 2. Validate template
python3 resources/docs/simulation/validate_template.py

# 3. Use with OMR appreciation (when Python is updated in Step 3)
python3 packages/omr-appreciation/omr-python/appreciate_live.py \
    --template resources/docs/simulation/coordinates.json \
    --show-names \
    --validate-contests
```

## Next Steps

After template generation (Step 4), proceed with Step 3:

- Add `load_bubble_metadata()` helper to Python code
- Update `get_candidate_name()` to use metadata lookup
- Update `convert_bubbles_to_zones()` to use metadata
- Support both old and new bubble ID formats during transition

## Benefits

1. ✅ **Simple bubble IDs** - `A1` instead of `PRESIDENT_LD_001`
2. ✅ **Consistent with mapping.yaml** - Generated from same source
3. ✅ **Backward compatible** - Current Python code still works
4. ✅ **Testable** - Can validate template before using
5. ✅ **Flexible** - Easy to regenerate with different layouts
