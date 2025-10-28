# OMR Overlay Visualization Guide

## Overview

The enhanced overlay system provides color-coded visual feedback for OMR appreciation results, making it easy to identify valid marks, overvotes, ambiguous marks, and unfilled bubbles at a glance.

## Color Coding Scheme

### Mark Status Colors

| Color | Status | Description | Use Case |
|-------|--------|-------------|----------|
| 🟢 **Green** | Valid | Filled mark with high confidence (≥95%) | Normal valid votes |
| 🔴 **Red** | Overvote | Multiple marks in single-choice position | Invalid - won't be counted |
| 🟠 **Orange** | Ambiguous | Mark detected but with warnings | Needs review |
| 🟡 **Yellow** | Low Confidence | Filled but below high confidence threshold | May need verification |
| ⚪ **Gray** | Unfilled | No mark detected (optional display) | Reference only |

### Visual Elements

**Circle Thickness:**
- **4px**: High confidence marks (green, red)
- **3px**: Medium confidence (orange, yellow)
- **2px**: Unfilled reference marks (gray)

**Labels:**
- **Percentage**: Fill ratio or confidence score
- **Status Text**: "OVERVOTE", "⚠ AMBIGUOUS", "LOW CONF"
- **Position**: Right side of circle

**Legend Box:**
- **Location**: Top-right corner
- **Contents**: 
  - Scenario name
  - Color meanings with counts
  - Statistics summary

## Scenario Examples

### Scenario 1: Normal Ballot

**Visual Appearance:**
- 5 bright **green circles** with thick borders
- Each shows percentage (e.g., "100%")
- Legend shows: "✓ Valid: 5"

**Interpretation:**
- All expected marks detected
- High confidence on all marks
- No issues or warnings

```
┌─────────────────────────────────────┐
│ Scenario: Normal                    │
│ ✓ Valid: 5                          │
│ ✗ Overvote: 0                       │
│ ⚠ Ambiguous: 0                      │
│ ○ Unfilled: 0                       │
└─────────────────────────────────────┘
```

### Scenario 2: Overvote Detection

**Visual Appearance:**
- 2 **red circles** on President position
- Both labeled "OVERVOTE"
- Other positions show green circles
- Legend shows: "✗ Overvote: 2"

**Interpretation:**
- Overvote condition detected
- Both marks visible but won't count
- Other positions valid

```
PRESIDENT_LD_001:  ⭕ 98% OVERVOTE
PRESIDENT_SJ_002:  ⭕ 95% OVERVOTE
```

### Scenario 3: Faint Marks

**Visual Appearance:**
- **Orange circle** on faint mark
- Label: "⚠ AMBIGUOUS"
- Lower percentage shown
- May include unfilled marks for context

**Interpretation:**
- Mark detected but below ideal threshold
- May need manual review
- Demonstrates sensitivity limits

```
PRESIDENT_LD_001:  ⚠ 20% ⚠ AMBIGUOUS
```

## Using the Overlay System

### Basic Usage

```php
use Tests\Helpers\OMRSimulator;

$overlayPath = OMRSimulator::createOverlay(
    $filledImagePath,
    $appreciationResults,
    $coordinates
);
```

### With Options

```php
$overlayPath = OMRSimulator::createOverlay(
    $filledImagePath,
    $appreciationResults,
    $coordinates,
    [
        'scenario' => 'normal',        // Scenario name for legend
        'show_legend' => true,          // Display legend box
        'show_unfilled' => false,       // Show gray circles on unfilled
        'contest_limits' => [           // For overvote detection
            'PRESIDENT' => 1,
            'SENATOR' => 12,
        ],
        'dpi' => 300,                   // Image resolution
    ]
);
```

### Options Reference

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `scenario` | string | `'normal'` | Scenario name shown in legend |
| `show_legend` | bool | `true` | Display legend box |
| `show_unfilled` | bool | `false` | Show unfilled bubbles in gray |
| `contest_limits` | array | `[]` | Max selections per contest for overvote detection |
| `dpi` | int | `300` | Image resolution (dots per inch) |
| `highlight_ambiguous` | bool | `false` | Extra emphasis on ambiguous marks |

## Reading the Overlay

### Quick Scan Process

1. **Look at legend** - Get overall statistics
2. **Scan for red circles** - Identify overvotes
3. **Check orange/yellow** - Review ambiguous marks
4. **Verify green circles** - Confirm valid marks match expectations

### Troubleshooting with Overlays

**Problem: Expected mark not showing**
- Check if circle is present but gray (unfilled)
- Look at fill_ratio in results.json
- Verify coordinates in template/coordinates.json

**Problem: Unexpected red circle (overvote)**
- Count filled marks in that position
- Check contest_limits setting
- Verify only one mark was intended

**Problem: Orange circles (ambiguous)**
- Review fill_ratio values
- Consider adjusting detection threshold
- Check for partial marks or smudges

**Problem: No overlay generated**
- Check for Imagick errors in logs
- Verify font path exists
- Ensure image permissions are correct

## Legend Interpretation

### Valid Count
Number of marks that:
- Are filled (detected as marked)
- Have fill_ratio ≥ 0.95
- Are NOT overvotes
- Have no major warnings

### Overvote Count
Number of marks that:
- Are filled
- Exceed the contest limit
- Will NOT be counted in results

### Ambiguous Count
Number of marks that:
- Are filled OR have ambiguous warnings
- Have fill_ratio between 0.15-0.45
- OR have low confidence
- OR are non-uniform
- Need manual review

### Unfilled Count
Number of bubbles that:
- Were not detected as filled
- Only shown if `show_unfilled` is true
- For reference/context only

## Advanced Features

### Custom Contest Limits

```php
$overlayPath = OMRSimulator::createOverlay(
    $filledImagePath,
    $appreciationResults,
    $coordinates,
    [
        'contest_limits' => [
            'PRESIDENT' => 1,           // Single choice
            'VICE-PRESIDENT' => 1,
            'SENATOR' => 12,            // Multiple choice (up to 12)
            'REPRESENTATIVE-PARTY-LIST' => 1,
        ],
    ]
);
```

### Showing Unfilled Bubbles

Useful for verifying coordinate mappings:

```php
$overlayPath = OMRSimulator::createOverlay(
    $filledImagePath,
    $appreciationResults,
    $coordinates,
    [
        'show_unfilled' => true,  // Show ALL bubbles in gray if not filled
    ]
);
```

### Scenario-Specific Styling

The overlay automatically adjusts based on scenario:

- **Normal**: Focus on valid/invalid distinction
- **Overvote**: Red highlighting for multiple marks
- **Faint**: Emphasis on confidence levels

## Integration with Tests

The overlay is automatically generated for each test scenario:

```bash
# View overlays after test run
cd storage/app/tests/omr-appreciation/latest

# Normal ballot
open scenario-1-normal/overlay.png

# Overvote detection  
open scenario-2-overvote/overlay.png

# Faint marks
open scenario-3-faint/overlay.png
```

## Comparison Workflow

### Comparing Different Runs

```bash
# View side by side
open storage/app/tests/omr-appreciation/runs/2025-10-28_101530/scenario-1-normal/overlay.png
open storage/app/tests/omr-appreciation/runs/2025-10-28_163745/scenario-1-normal/overlay.png
```

### Before/After Code Changes

1. Run tests before changes
2. Note the run ID
3. Make code changes
4. Run tests again
5. Compare overlays from both runs

## Best Practices

### Visual Verification

✅ **Do:**
- Always check overlays after test runs
- Compare against expected bubbles
- Look for unexpected patterns
- Verify legend counts match expectations

❌ **Don't:**
- Rely solely on pass/fail status
- Ignore orange/yellow warnings
- Skip visual inspection for critical tests

### Color Blindness Considerations

The system uses:
- **Shape differences** (circle thickness)
- **Labels** (text descriptions)
- **Position** (legend with symbols)

Not just color alone.

### Print and Documentation

Overlays are suitable for:
- Technical documentation
- Bug reports
- Test result archiving
- Presentations
- Training materials

## Technical Details

### Image Format
- **Format**: PNG with RGB color
- **Resolution**: 300 DPI (default)
- **Size**: Same as input ballot image
- **Transparency**: Legend has semi-transparent background

### Font Requirements
- **Font**: Arial (fallback to system default)
- **Path**: `/System/Library/Fonts/Supplemental/Arial.ttf`
- **Sizes**: 9-14pt depending on element

### Performance
- **Overhead**: Minimal (~100-200ms per overlay)
- **Memory**: Same as base image + drawing operations
- **Scaling**: Linear with number of marks

## Troubleshooting

### Font Not Found

```php
// OMRSimulator checks for font existence
$fontPath = '/System/Library/Fonts/Supplemental/Arial.ttf';
if (file_exists($fontPath)) {
    $draw->setFont($fontPath);
}
// Falls back to system default if not found
```

### Legend Cut Off

Adjust legend position in `OMRSimulator::drawLegend()`:

```php
$legendX = $width - 280;  // Distance from right edge
$legendY = 20;            // Distance from top
```

### Colors Not Showing

Check:
1. Imagick installed and enabled
2. Color mode is RGB (not grayscale)
3. No color profile conflicts

## Future Enhancements

Potential additions:
- [ ] Configurable legend position
- [ ] Custom color schemes
- [ ] Bubble ID labels on overlay
- [ ] Comparison mode (side-by-side)
- [ ] Export to SVG for scalability
- [ ] Confidence heat maps
- [ ] Animation for before/after

---

*Last updated: 2025-10-28*
