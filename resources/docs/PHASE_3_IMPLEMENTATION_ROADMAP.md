# Phase 3: Template Enhancement - Implementation Roadmap

## 🎯 Goal
Make PDF generation produce actual visible mark boxes that users can fill, ensuring perfect alignment with Python OpenCV detection zones.

---

## Current State

### What We Have
✅ Python OpenCV mark detection working
✅ Confidence metrics implemented
✅ Debug visualization  
✅ Test workflows with synthetic images

### What's Missing
❌ PDF templates don't show visible mark boxes
❌ No calibration verification system
❌ No end-to-end test with printed/scanned forms

---

## Phase 3 Tasks

### Task 1: Add Visible Mark Boxes to Templates

**File**: `packages/omr-template/src/Services/ZoneGenerator.php`

**Current Behavior**: Generates zone coordinates but doesn't render visible marks

**Needed**: Update template rendering to include visible circles/boxes

**Approach**:
1. Modify ZoneGenerator to include mark box rendering data
2. Update Handlebars template to render circles at zone positions
3. Use CSS/HTML to draw circles that match zone dimensions exactly

**Implementation**:
```php
// In ZoneGenerator.php
public function generateZones(array $contests, string $layout, int $dpi): array
{
    $zones = [];
    // ... existing logic ...
    
    foreach ($contests as $contest) {
        foreach ($contest['candidates'] as $candidate) {
            $zones[] = [
                'id' => $this->generateZoneId($contest, $candidate),
                'x' => $x,
                'y' => $y,
                'width' => $this->markBoxSize,
                'height' => $this->markBoxSize,
                'contest' => $contest['title'],
                'candidate' => $candidate['name'],
                // NEW: Add render properties
                'render' => [
                    'type' => 'circle', // or 'square', 'rounded-square'
                    'border_width' => 2,
                    'border_color' => '#000000',
                    'background' => '#FFFFFF',
                ]
            ];
            $y += $this->verticalSpacing;
        }
    }
    
    return $zones;
}
```

**Template Update** (`resources/views/templates/ballot.blade.php` or `.hbs`):
```html
{{#each zones}}
<div class="mark-zone" style="
    position: absolute;
    left: {{x}}px;
    top: {{y}}px;
    width: {{width}}px;
    height: {{height}}px;
">
    <div class="mark-box" style="
        width: 100%;
        height: 100%;
        border: {{render.border_width}}px solid {{render.border_color}};
        border-radius: {{#if (eq render.type 'circle')}}50%{{else}}0{{/if}};
        background: {{render.background}};
        box-sizing: border-box;
    "></div>
</div>
{{/each}}
```

---

### Task 2: Create Calibration Sheet Generator

**New Command**: `packages/omr-template/src/Commands/GenerateCalibrationCommand.php`

**Purpose**: Generate a special calibration sheet with:
- All mark boxes visible and numbered
- Grid overlay showing coordinates
- Large fiducial markers
- Reference measurements

**Usage**:
```bash
php artisan omr:generate-calibration --output=storage/calibration.pdf
```

**Features**:
```php
class GenerateCalibrationCommand extends Command
{
    protected $signature = 'omr:generate-calibration 
                            {--output= : Output path for calibration sheet}
                            {--dpi=300 : Resolution}
                            {--layout=A4 : Page layout}';
    
    public function handle()
    {
        // Generate calibration template with:
        // - Numbered zones (1, 2, 3, etc.)
        // - Coordinate labels
        // - Measurement rulers
        // - Test patterns
        
        $calibrationData = [
            'document_type' => 'Calibration Sheet',
            'contests_or_sections' => [
                [
                    'title' => 'Calibration Zones',
                    'instruction' => 'DO NOT MARK - For testing only',
                    'candidates' => array_map(
                        fn($i) => ['name' => "Zone {$i}"],
                        range(1, 12) // 12 test zones
                    )
                ]
            ],
            'metadata' => [
                'purpose' => 'OMR System Calibration',
                'generated_at' => now(),
                'instructions' => 'Print at 100% scale, no fit-to-page',
            ]
        ];
        
        // Generate with special styling
        $this->call('omr:generate', [
            'template' => 'calibration-v1',
            'identifier' => 'CALIBRATION-' . date('Ymd-His'),
            '--data' => $this->saveTemp($calibrationData),
        ]);
    }
}
```

---

### Task 3: Calibration Verification Command

**New Command**: `packages/omr-appreciation/src/Commands/VerifyCalibrationCommand.php`

**Purpose**: Verify that scanned calibration sheet aligns correctly

**Usage**:
```bash
php artisan omr:verify-calibration storage/scans/calibration.jpg storage/calibration.json
```

**Implementation**:
```php
class VerifyCalibrationCommand extends Command
{
    protected $signature = 'omr:verify-calibration 
                            {scan : Path to scanned calibration image}
                            {template : Path to calibration template JSON}
                            {--tolerance=10 : Maximum pixel error tolerance}';
    
    public function handle(OMRAppreciator $appreciator)
    {
        $scan = $this->argument('scan');
        $template = $this->argument('template');
        $tolerance = (int) $this->option('tolerance');
        
        // Run appreciation with debug mode
        $result = $appreciator->runDebug($scan, $template, 0.25);
        
        // Load expected positions
        $expected = json_decode(file_get_contents($template), true);
        
        // Check fiducial alignment
        $this->info("Checking fiducial alignment...");
        $fiducialErrors = [];
        
        // Compare detected vs expected positions
        // (This requires enhancing Python script to return detected positions)
        
        // Check zone positions after alignment
        $this->info("Checking zone alignment...");
        $zoneErrors = [];
        
        foreach ($expected['zones'] as $i => $zone) {
            // Calculate position error
            // (Would need Python to return actual detected zone centers)
            $error = $this->calculatePositionError($zone, $result['results'][$i]);
            
            if ($error > $tolerance) {
                $zoneErrors[] = [
                    'zone' => $zone['id'],
                    'error' => $error,
                ];
            }
        }
        
        // Report results
        if (empty($zoneErrors)) {
            $this->info("✅ Calibration verified! All zones within {$tolerance}px tolerance.");
            return self::SUCCESS;
        } else {
            $this->error("❌ Calibration failed! {count($zoneErrors)} zone(s) exceed tolerance:");
            $this->table(
                ['Zone', 'Error (px)'],
                array_map(fn($e) => [$e['zone'], $e['error']], $zoneErrors)
            );
            return self::FAILURE;
        }
    }
}
```

---

### Task 4: Enhanced Python Script for Calibration

**New Script**: `packages/omr-appreciation/omr-python/calibrate.py`

**Purpose**: Return detected positions for verification

**Output**:
```json
{
  "fiducials_detected": [
    {"id": "top_left", "x": 118, "y": 118, "expected_x": 118, "expected_y": 118, "error": 0},
    {"id": "top_right", "x": 2293, "y": 119, "expected_x": 2291, "expected_y": 118, "error": 2.2}
  ],
  "zones_checked": [
    {"id": "ZONE_1", "center_x": 275, "center_y": 650, "expected_x": 275, "expected_y": 650, "error": 0},
    {"id": "ZONE_2", "center_x": 276, "center_y": 750, "expected_x": 275, "expected_y": 750, "error": 1.0}
  ],
  "summary": {
    "max_fiducial_error": 2.2,
    "max_zone_error": 1.0,
    "avg_fiducial_error": 1.1,
    "avg_zone_error": 0.5,
    "within_tolerance": true,
    "tolerance": 10
  }
}
```

---

### Task 5: End-to-End Test Workflow

**New Script**: `test-omr-printed.sh`

**Purpose**: Test complete printed→scanned workflow

**Steps**:
1. Generate PDF with visible mark boxes
2. Instruct user to print and mark manually
3. Scan or photograph
4. Run appreciation
5. Verify results

```bash
#!/bin/bash

echo "🖨️  End-to-End Printed Ballot Test"
echo "===================================="
echo ""

# Generate ballot with visible marks
php artisan omr:generate ballot-v1 PRINT-TEST-001 --data=test-data.json

echo "📋 Instructions:"
echo "  1. Print: storage/omr-output/PRINT-TEST-001.pdf"
echo "  2. Scale: 100% (NO fit-to-page)"
echo "  3. Mark the following zones with dark pen/pencil:"
echo "     - First option in Question 1"
echo "     - Second option in Question 2"
echo "  4. Scan or photograph at 300 DPI"
echo "  5. Save as: storage/scans/print-test-filled.jpg"
echo ""
read -p "Press ENTER when ready..."

# Run appreciation
php artisan omr:appreciate-python \
    storage/scans/print-test-filled.jpg \
    storage/omr-output/PRINT-TEST-001.json \
    --output=storage/scans/print-test-results.json \
    --debug

# Show results
cat storage/scans/print-test-results.json | jq '.results[] | select(.filled == true)'
```

---

## Implementation Order

### Week 1: Visible Mark Boxes
1. Update `ZoneGenerator` to include render properties
2. Update Handlebars template to render mark boxes
3. Test generation with `omr:generate`
4. Verify zones match OpenCV detection areas

### Week 2: Calibration System
5. Create `GenerateCalibrationCommand`
6. Enhance Python to return position data
7. Create `VerifyCalibrationCommand`
8. Test calibration workflow

### Week 3: Real-World Testing
9. Print test ballots
10. Collect scanning samples (different scanners, lighting, paper)
11. Measure detection accuracy
12. Tune confidence thresholds based on real data

---

## Expected Results

After Phase 3:

✅ **Generated PDFs show visible mark boxes**
- Users can see exactly where to mark
- Boxes align perfectly with detection zones
- Print at any DPI maintains alignment

✅ **Calibration system validates setup**
- Verify scanner accuracy
- Detect alignment issues
- Measure position errors

✅ **Real printed/scanned ballots work**
- >95% detection accuracy
- Confident handling of various mark qualities
- Production-ready for actual elections/surveys

---

## Testing Checklist

### Visual Verification
- [ ] PDF shows circles/boxes at correct positions
- [ ] Circles match zone dimensions from JSON
- [ ] Fiducial markers are clearly visible
- [ ] Document ID barcode is readable

### Print Quality
- [ ] Printed at 100% scale matches digital
- [ ] Mark boxes are appropriately sized (easy to fill)
- [ ] Boxes don't bleed or distort

### Detection Accuracy
- [ ] Filled marks detected correctly
- [ ] Unfilled marks not detected
- [ ] Partial marks handled appropriately
- [ ] Confidence scores are reasonable

### Calibration
- [ ] Calibration sheet generates correctly
- [ ] Scanned calibration verifies within 5px
- [ ] Position errors reported accurately

---

## Configuration Options

Add to `config/omr-template.php`:

```php
return [
    'mark_boxes' => [
        'enabled' => env('OMR_MARK_BOXES_ENABLED', true),
        'style' => env('OMR_MARK_BOX_STYLE', 'circle'), // circle, square, rounded
        'size' => env('OMR_MARK_BOX_SIZE', 83), // pixels at 300 DPI
        'border_width' => 2,
        'border_color' => '#000000',
        'background' => '#FFFFFF',
    ],
    
    'calibration' => [
        'tolerance_px' => 10,
        'test_zones_count' => 12,
        'include_grid' => true,
        'include_rulers' => true,
    ],
];
```

---

## Success Criteria

Phase 3 is complete when:

1. ✅ PDFs generated have visible, fillable mark boxes
2. ✅ Calibration sheet can be generated
3. ✅ Calibration verification command works
4. ✅ Real printed/scanned ballot achieves >90% accuracy
5. ✅ Documentation updated with printing/scanning guide

---

## Next Steps

Would you like me to:

**Option A**: Start with Task 1 (add visible mark boxes)
**Option B**: Create all command stubs first, then implement
**Option C**: Focus on calibration system first
**Option D**: Create a proof-of-concept with manual HTML/CSS first

Let me know which approach you prefer!
