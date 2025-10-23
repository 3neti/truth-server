# 🎯 OpenCV-Aware OMR Template Generation & Calibration Plan

## Problem Statement

The current workflow has a disconnect:
1. **PDF Generation** (omr-template) creates PDFs with zone definitions
2. **Mark Simulation** (test script) draws rectangles at zone positions
3. **Python OpenCV Appreciation** can't detect the simulated marks as "filled"

**Root Cause**: The marks drawn by ImageMagick don't properly simulate how a real pen/pencil fills an OMR bubble, and the perspective transform may shift positions slightly.

## Solution Architecture

### 🎯 Strategy: OpenCV-First Template Design

Instead of treating OpenCV as an afterthought, we design the entire template generation process with OpenCV detection in mind.

---

## Phase 1: Enhanced Template Generation with OpenCV Calibration

### 1.1 Add Visible Mark Boxes to PDF Templates

**Update**: `omr-template` package to render actual mark boxes/circles

```handlebars
{{!-- In ballot template --}}
{{#each contests}}
  <div class="contest">
    <h3>{{title}}</h3>
    {{#each candidates}}
      <div class="candidate-row">
        {{!-- RENDER ACTUAL MARK BOX --}}
        <div class="mark-box" style="
          position: absolute;
          left: {{zone.x}}px;
          top: {{zone.y}}px;
          width: {{zone.width}}px;
          height: {{zone.height}}px;
          border: 3px solid black;
          border-radius: 50%; /* circle */
          background: white;
        "></div>
        <span class="candidate-name">{{name}}</span>
      </div>
    {{/each}}
  </div>
{{/each}}
```

**Benefits**:
- Mark boxes are visible in the PDF
- Positions are guaranteed to match zone definitions
- Users can see where to fill marks
- Test scripts can fill these exact positions

---

### 1.2 Create OpenCV-Based Mark Box Renderer

**New Utility**: `packages/omr-template/src/Services/MarkBoxRenderer.php`

This service would:
1. Take zone definitions from template
2. Use Python OpenCV to render perfect mark boxes
3. Overlay them on the PDF at exact pixel positions
4. Generate a "calibration sheet" for testing

**Why?** This ensures pixel-perfect alignment between what's printed and what OpenCV expects.

---

## Phase 2: Improve Test Workflow Mark Simulation

### 2.1 Fix ImageMagick Mark Drawing

**Current Issue**: Marks are drawn BEFORE knowing the actual scanned image alignment.

**Solution**: Draw marks that fill 70-80% of the zone area (simulating realistic pen marks)

```bash
# Instead of just rectangles, draw filled circles
# Calculate center of zone
CENTER_X=$((PRES_X + PRES_W / 2))
CENTER_Y=$((PRES_Y + PRES_H / 2))
RADIUS=$((PRES_W * 35 / 100))  # 70% diameter (35% radius)

magick "$SCAN_IMAGE" \
    -fill black \
    -draw "circle $CENTER_X,$CENTER_Y $((CENTER_X + RADIUS)),$CENTER_Y" \
    "$SCAN_IMAGE"
```

**Better Approach**: Use Python OpenCV to draw marks directly

---

### 2.2 Create Python Mark Simulator

**New Script**: `omr-python/simulate_marks.py`

```python
# This reads the template JSON and draws realistic marks
# at the exact zone positions, accounting for any needed offsets

def simulate_filled_marks(image, template, mark_indices):
    """Draw realistic pen marks at specified zone indices."""
    for idx in mark_indices:
        zone = template['zones'][idx]
        x, y, w, h = zone['x'], zone['y'], zone['width'], zone['height']
        
        # Draw filled circle (70-80% of zone size)
        center = (x + w // 2, y + h // 2)
        radius = int(min(w, h) * 0.35)
        cv2.circle(image, center, radius, (0, 0, 0), -1)  # filled
    
    return image
```

**Usage in test script**:
```bash
# After PDF → image conversion
python omr-python/simulate_marks.py \
    storage/omr-test/scanned-ballot.jpg \
    storage/omr-output/TEST-XXX.json \
    --mark-zones 0,3 \
    --output storage/omr-test/filled-ballot.jpg
```

---

## Phase 3: Calibration & Verification Loop

### 3.1 Create Calibration Template

**New Command**: `php artisan omr:generate-calibration`

This generates a special calibration ballot with:
- All mark boxes rendered and numbered
- Grid overlay showing pixel coordinates
- Fiducial markers in all 4 corners
- QR code with exact template metadata

**Purpose**: Print this once, scan it, verify OpenCV detects all positions correctly.

---

### 3.2 Add Calibration Verification

**New Command**: `php artisan omr:verify-calibration <scanned_calibration_image> <template>`

This command:
1. Runs OpenCV appreciation on calibration sheet
2. Checks if detected fiducials match expected positions (within tolerance)
3. Validates zone positions after perspective transform
4. Reports any misalignment issues
5. Suggests adjustments (DPI, scale factors, etc.)

---

## Phase 4: Dynamic Threshold Adjustment

### 4.1 Make Fill Threshold Configurable

**Update**: `appreciate.py` to accept threshold parameter

```python
parser.add_argument('--threshold', type=float, default=0.3)
```

**Update**: `OMRAppreciator.php` to pass threshold option

```php
public function run(string $imagePath, string $templatePath, float $threshold = 0.3): array
{
    $command = sprintf(
        '%s %s %s %s --threshold=%s 2>&1',
        escapeshellarg($python),
        escapeshellarg($script),
        escapeshellarg($imagePath),
        escapeshellarg($templatePath),
        $threshold
    );
    // ...
}
```

---

## Phase 5: Enhanced Debug Visualization

### 5.1 Add Debug Mode to Appreciation

**Update**: `AppreciatePythonCommand.php`

```php
protected $signature = 'omr:appreciate-python 
                        {image}
                        {template}
                        {--output=}
                        {--threshold=0.3}
                        {--debug : Save debug visualization images}';

if ($this->option('debug')) {
    // Call appreciate_debug.py instead
    // Save visualization showing:
    // - Detected fiducials
    // - Zone overlays
    // - Fill ratios per zone
}
```

---

## Phase 6: Regression Testing Suite

### 6.1 Create Test Fixtures

**Directory**: `tests/fixtures/omr/`

```
tests/fixtures/omr/
├── calibration-sheet.pdf
├── calibration-sheet.json
├── filled-ballot-sample-1.jpg    # Hand-marked sample
├── filled-ballot-sample-1.json   # Expected results
├── filled-ballot-sample-2.jpg
└── filled-ballot-sample-2.json
```

### 6.2 Automated Appreciation Tests

**New Test**: `packages/omr-appreciation/tests/AppreciationAccuracyTest.php`

```php
test('detects filled marks with 95% accuracy', function () {
    $image = base_path('tests/fixtures/omr/filled-ballot-sample-1.jpg');
    $template = base_path('tests/fixtures/omr/filled-ballot-sample-1-template.json');
    $expected = json_decode(file_get_contents(
        base_path('tests/fixtures/omr/filled-ballot-sample-1.json')
    ), true);
    
    $result = app(OMRAppreciator::class)->run($image, $template);
    
    // Compare results
    $accuracy = calculateAccuracy($result['results'], $expected['results']);
    expect($accuracy)->toBeGreaterThan(0.95);
});
```

---

## Implementation Priority

### 🚀 Phase 1: Quick Wins (1-2 hours)
1. ✅ Fix test script to draw filled circles instead of rectangles
2. ✅ Add `--threshold` parameter to Python script
3. ✅ Update test script to use lower threshold (0.25 instead of 0.3)

### 🎯 Phase 2: Core Fix (2-4 hours)
4. Create `simulate_marks.py` Python script
5. Update test workflow to use Python mark simulator
6. Add debug mode to appreciation command

### 🏗️ Phase 3: Template Enhancement (4-6 hours)
7. Update omr-template to render visible mark boxes
8. Create calibration template generator
9. Add calibration verification command

### 🧪 Phase 4: Testing & Validation (2-3 hours)
10. Create test fixtures with real scanned samples
11. Write automated accuracy tests
12. Document calibration procedure

---

## Expected Outcomes

After implementation:

✅ **Test script shows**:
```
Filled Count: 2
Unfilled Count: 2

🗳️  Detected Marks:
   ✓ President: PRESIDENT_ALICE_JOHNSON (fill ratio: 0.65)
   ✓ Vice President: VICE_PRESIDENT_EMMA_DAVIS (fill ratio: 0.72)
```

✅ **Calibration verification passes** with <5px position error

✅ **Real-world testing** shows >95% mark detection accuracy

---

## Next Steps

**Option A - Quick Fix** (Recommended for immediate testing):
```bash
# Run the fixed test workflow with Python mark simulator
./test-omr-workflow-fixed.sh
```

**Option B - Full Calibration** (Recommended for production):
```bash
# Generate calibration sheet
php artisan omr:generate-calibration

# Print, scan, verify
php artisan omr:verify-calibration storage/scans/calibration.jpg

# Adjust template based on verification results
```

**Option C - Gradual Enhancement** (Recommended for iterative improvement):
- Implement Phase 1 now
- Test with real printed/scanned ballots
- Collect accuracy metrics
- Implement Phases 2-4 based on findings

---

## Questions to Consider

1. **Mark Box Style**: Circles vs. Rectangles vs. Rounded rectangles?
2. **Fill Threshold**: Should it be position-specific? (edges vs. center)
3. **Multiple Pages**: How to handle multi-page ballots?
4. **Rotation**: Should we handle 90/180/270° rotations?
5. **Lighting**: Should we normalize for varying scan brightness?

Would you like me to implement Phase 1 (Quick Wins) first to get the test working?
