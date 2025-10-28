# OMR Overlay Configuration Guide

## Overview

Overlay visualization settings are now fully configurable through `config/omr-template.php`. This allows you to customize fonts, colors, layout, and behavior without modifying code.

## Configuration Location

All overlay settings are in the `overlay` section of `config/omr-template.php`:

```php
'overlay' => [
    'fonts' => [...],
    'colors' => [...],
    'circles' => [...],
    'layout' => [...],
    'legend' => [...],
    'candidates' => [...],
    'font_path' => '...',
],
```

## Configuration Sections

### Font Sizes

Control text size for different overlay elements:

```php
'fonts' => [
    'valid_marks' => 40,        // Valid marks with candidate names
    'other_marks' => 35,        // Overvotes, faint marks, low confidence
    'legend_title' => 14,       // Legend scenario title
    'legend_text' => 11,        // Legend statistics
],
```

**Environment Variables:**
- `OMR_OVERLAY_FONT_VALID` - Size for valid marks
- `OMR_OVERLAY_FONT_OTHER` - Size for other marks
- `OMR_OVERLAY_FONT_LEGEND_TITLE` - Legend title size
- `OMR_OVERLAY_FONT_LEGEND_TEXT` - Legend text size

### Colors

Customize mark colors for different statuses:

```php
'colors' => [
    'valid' => 'lime',              // High-confidence valid marks
    'overvote' => 'red',            // Multiple marks in single-choice
    'ambiguous' => 'orange',        // Marks with warnings
    'faint' => 'orange',            // Below threshold (16-45% fill)
    'unfilled' => 'gray',           // No mark detected
    'low_confidence' => 'yellow',   // Filled but <95% confidence
],
```

**Supported Color Formats:**
- CSS names: `'red'`, `'lime'`, `'orange'`
- Hex codes: `'#FF0000'`, `'#00FF00'`
- RGB: `'rgb(255, 0, 0)'`
- RGBA: `'rgba(255, 0, 0, 0.8)'`

**Environment Variables:**
- `OMR_OVERLAY_COLOR_VALID`
- `OMR_OVERLAY_COLOR_OVERVOTE`
- `OMR_OVERLAY_COLOR_AMBIGUOUS`
- `OMR_OVERLAY_COLOR_FAINT`
- `OMR_OVERLAY_COLOR_UNFILLED`
- `OMR_OVERLAY_COLOR_LOW_CONF`

### Circle Styles

Visual styling for mark circles:

```php
'circles' => [
    'valid_thickness' => 4,         // Thick border for valid marks
    'overvote_thickness' => 4,      // Thick border for overvotes
    'other_thickness' => 3,         // Medium for ambiguous/low confidence
    'unfilled_thickness' => 2,      // Thin border for unfilled
    'radius_offset' => 5,           // Extra pixels added to bubble radius
],
```

### Text Layout

Positioning and formatting of annotations:

```php
'layout' => [
    'separator' => ' | ',           // Between percentage, status, name
    'text_offset_x' => 12,          // Horizontal distance from bubble (pixels)
    'text_offset_y' => 5,           // Vertical adjustment for centering (pixels)
],
```

**Environment Variables:**
- `OMR_OVERLAY_SEPARATOR` - Text separator

### Legend Box

Statistics and color key display:

```php
'legend' => [
    'enabled' => true,              // Show/hide legend
    'position' => 'top-right',      // Position on image
    'width' => 260,                 // Box width (pixels)
    'height' => 140,                // Box height (pixels)
    'margin_x' => 280,              // Distance from right edge
    'margin_y' => 20,               // Distance from top edge
    'background' => 'rgba(255, 255, 255, 0.9)',  // Semi-transparent white
    'border_color' => 'black',
    'border_width' => 2,
],
```

**Environment Variables:**
- `OMR_OVERLAY_LEGEND` - Enable/disable legend (true/false)

### Candidate Names

Configuration for displaying candidate names:

```php
'candidates' => [
    'enabled' => true,              // Show candidate names on valid marks
    'questionnaire_document_id' => 'PH-2025-QUESTIONNAIRE-CURRIMAO-001',
    'auto_load' => true,            // Automatically load questionnaire in tests
],
```

**Environment Variables:**
- `OMR_OVERLAY_SHOW_NAMES` - Enable/disable names (true/false)
- `OMR_QUESTIONNAIRE_ID` - Questionnaire document ID

### Font Path

TrueType font file location:

```php
'font_path' => '/System/Library/Fonts/Supplemental/Arial.ttf',
```

Falls back to system default if file doesn't exist.

## Usage Examples

### Example 1: Larger Fonts for Projection

For presentations or large displays:

```bash
# In .env
OMR_OVERLAY_FONT_VALID=60
OMR_OVERLAY_FONT_OTHER=50
```

### Example 2: Custom Color Scheme

Blue/green color scheme:

```php
// In config/omr-template.php
'colors' => [
    'valid' => '#00FF00',           // Bright green
    'overvote' => '#FF0000',        // Red
    'ambiguous' => '#FFA500',       // Orange
    'faint' => '#FFD700',           // Gold
    'unfilled' => '#808080',        // Gray
    'low_confidence' => '#FFFF00',  // Yellow
],
```

### Example 3: Compact Legend

Smaller legend for space-constrained layouts:

```php
'legend' => [
    'enabled' => true,
    'width' => 200,
    'height' => 100,
    'margin_x' => 220,
],
```

### Example 4: Hide Legend

For minimal overlays:

```bash
# In .env
OMR_OVERLAY_LEGEND=false
```

Or in code:

```php
$overlayPath = OMRSimulator::createOverlay(
    $filledPng,
    $results,
    $coordinates,
    ['show_legend' => false]  // Overrides config
);
```

## Runtime Overrides

Options passed to `createOverlay()` override config values:

```php
$overlayPath = OMRSimulator::createOverlay(
    $filledPng,
    $results,
    $coordinates,
    [
        'scenario' => 'normal',
        'show_legend' => false,         // Override config
        'questionnaire' => $data,       // Override config
        'output_path' => 'custom.png',
    ]
);
```

## Testing Different Configurations

You can test overlay appearance quickly:

```bash
# Test with larger fonts
OMR_OVERLAY_FONT_VALID=50 OMR_OVERLAY_FONT_OTHER=45 php artisan test --filter=appreciation

# Test with custom colors
OMR_OVERLAY_COLOR_VALID=green php artisan test --filter=appreciation

# Test without legend
OMR_OVERLAY_LEGEND=false php artisan test --filter=appreciation
```

## Best Practices

### Font Sizing
- **40pt+**: Good for verification and auditing
- **20-30pt**: Suitable for documentation
- **10-15pt**: Compact, detailed analysis

### Color Selection
- Use high contrast colors for accessibility
- Consider color-blind friendly palettes
- Lime/red/orange work well for most users

### Legend Positioning
- Top-right is default (doesn't obscure ballot content)
- Adjust margins if ballot has content in corners
- Can disable legend if space is limited

### Performance
- Larger fonts increase image size slightly
- Legend adds ~2KB to file size
- Config reads are cached by Laravel

## Troubleshooting

### Fonts Too Small
```bash
OMR_OVERLAY_FONT_VALID=50
OMR_OVERLAY_FONT_OTHER=45
```

### Colors Not Showing
Check color format:
```php
'valid' => 'lime',      // ✓ CSS name
'valid' => '#00FF00',   // ✓ Hex code
'valid' => 'green',     // ✗ May not work - use 'lime' instead
```

### Legend Cut Off
Increase margins:
```php
'legend' => [
    'margin_x' => 300,  // Move further from edge
],
```

### Custom Font Not Working
Check file path and permissions:
```bash
ls -l /System/Library/Fonts/Supplemental/Arial.ttf
```

## Migration from Hardcoded Values

If you have existing code with hardcoded values, config takes precedence unless explicitly overridden:

**Before:**
```php
// Hardcoded in OMRSimulator
$fontSize = 14;
$color = 'lime';
```

**After:**
```php
// From config
$fontSize = $config['fonts']['valid_marks'] ?? 40;
$color = $config['colors']['valid'] ?? 'lime';
```

Tests will automatically use config values.

## Related Documentation

- [OMR Overlay Visualization Guide](OMR_OVERLAY_VISUALIZATION.md)
- [OMR Testing Guide](../tests/Feature/OMRAppreciationTest.php)
- Main config: `config/omr-template.php`

---

*Last updated: 2025-10-28*
