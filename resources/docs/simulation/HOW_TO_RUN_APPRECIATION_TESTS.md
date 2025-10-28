# 🧪 How to Run OMR Appreciation Tests

This guide walks you through running the complete OMR appreciation testing pipeline, from generating ballots to verifying mark detection.

---

## 📋 Prerequisites

### 1. Check PHP Imagick Extension

```bash
php -m | grep imagick
```

**Expected output:** `imagick`

If not installed:
```bash
# macOS
brew install imagemagick
pecl install imagick

# Ubuntu/Debian
sudo apt-get install php-imagick
```

### 2. Check Python & OpenCV

```bash
python3 --version
python3 -c "import cv2; print('OpenCV:', cv2.__version__)"
```

**Expected:** Python 3.x and OpenCV version number

If OpenCV not installed:
```bash
cd packages/omr-appreciation/omr-python
pip3 install --break-system-packages opencv-python-headless numpy
```

### 3. Verify Database is Seeded

```bash
php artisan db:seed --class=TemplateSeeder
php artisan db:seed --class=InstructionalDataSeeder
```

---

## 🚀 Quick Start (Full Pipeline)

Run all tests in one command:

```bash
php artisan test tests/Feature/OMRAppreciationTest.php
```

This will:
1. ✅ Generate Philippine ballot PDF with coordinates
2. ✅ Convert PDF to PNG (300 DPI)
3. ✅ Simulate filled bubbles
4. ✅ Run Python appreciation script
5. ✅ Generate visual overlays
6. ✅ Save JSON reports

**Artifacts location:**
```bash
ls -lh storage/app/tests/artifacts/appreciation/
```

---

## 📝 Step-by-Step Manual Execution

### Step 1: Clean Old Files (Optional)

```bash
# Remove old PDFs and coordinates
rm -f storage/omr-output/*.pdf
rm -f storage/omr-output/*.png
rm -f storage/app/omr/coords/*.json

# Remove old test artifacts
rm -rf storage/app/tests/artifacts/appreciation
mkdir -p storage/app/tests/artifacts/appreciation
```

### Step 2: Generate Philippine Ballot

```bash
php artisan tinker --execute="
use App\Models\Template;
use App\Models\TemplateData;
use App\Actions\TruthTemplates\Compilation\CompileHandlebarsTemplate;
use App\Actions\TruthTemplates\Rendering\RenderTemplateSpec;

\$template = Template::find(4);  // Answer sheet template
\$data = TemplateData::find(4);  // Philippine ballot data

\$spec = CompileHandlebarsTemplate::run(\$template->handlebars_template, \$data->json_data);
\$result = RenderTemplateSpec::run(\$spec);

echo '✓ PDF: ' . \$result['pdf'] . PHP_EOL;
echo '✓ Coords: ' . \$result['coords'] . PHP_EOL;
"
```

**Expected output:**
```
✓ PDF: /Users/.../storage/omr-output/CURRIMAO-001-ballot-2025-05-12.pdf
✓ Coords: /Users/.../storage/app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json
```

### Step 3: View Generated Ballot

```bash
# Open the PDF
open storage/omr-output/CURRIMAO-001-ballot-2025-05-12.pdf

# Check coordinates file
cat storage/app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json | head -50
```

### Step 4: Convert PDF to PNG

```bash
php artisan tinker --execute="
use Tests\Helpers\OMRSimulator;

\$pdfPath = storage_path('omr-output/CURRIMAO-001-ballot-2025-05-12.pdf');
\$pngPath = OMRSimulator::pdfToPng(\$pdfPath, 300);

echo '✓ PNG created: ' . \$pngPath . PHP_EOL;
echo '✓ File size: ' . filesize(\$pngPath) . ' bytes' . PHP_EOL;
"
```

**View the PNG:**
```bash
open storage/omr-output/CURRIMAO-001-ballot-2025-05-12.png
```

### Step 5: Simulate Filled Bubbles

```bash
php artisan tinker --execute="
use Tests\Helpers\OMRSimulator;

\$blankPng = storage_path('omr-output/CURRIMAO-001-ballot-2025-05-12.png');
\$coordsPath = storage_path('app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json');
\$coordinates = json_decode(file_get_contents(\$coordsPath), true);

// Select bubbles to fill (vote for President #1, VP #2, Senators #1,2,3)
\$selectedBubbles = [
    'PRESIDENT_LD_001',
    'VICE-PRESIDENT_VD_002',
    'SENATOR_JD_001',
    'SENATOR_ES_002',
    'SENATOR_MF_003',
];

\$filledPng = OMRSimulator::fillBubbles(\$blankPng, \$selectedBubbles, \$coordinates);
echo '✓ Filled PNG: ' . \$filledPng . PHP_EOL;
"
```

**View simulated ballot:**
```bash
open storage/omr-output/CURRIMAO-001-ballot-2025-05-12_filled.png
```

### Step 6: Run Appreciation Script

```bash
python3 packages/omr-appreciation/omr-python/appreciate.py \
  storage/omr-output/CURRIMAO-001-ballot-2025-05-12_filled.png \
  storage/app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json \
  --threshold 0.3
```

**Expected output (JSON):**
```json
{
  "document_id": "CURRIMAO-001-ballot-2025-05-12",
  "results": [
    {
      "bubble_id": "PRESIDENT_LD_001",
      "confidence": 0.95
    },
    ...
  ]
}
```

### Step 7: Generate Visual Overlay

```bash
php artisan tinker --execute="
use Tests\Helpers\OMRSimulator;

\$filledPng = storage_path('omr-output/CURRIMAO-001-ballot-2025-05-12_filled.png');
\$coordsPath = storage_path('app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json');
\$coordinates = json_decode(file_get_contents(\$coordsPath), true);

// Mock detected marks (replace with actual appreciation results)
\$detectedMarks = [
    ['bubble_id' => 'PRESIDENT_LD_001', 'confidence' => 0.95],
    ['bubble_id' => 'VICE-PRESIDENT_VD_002', 'confidence' => 0.92],
    ['bubble_id' => 'SENATOR_JD_001', 'confidence' => 0.88],
    ['bubble_id' => 'SENATOR_ES_002', 'confidence' => 0.91],
    ['bubble_id' => 'SENATOR_MF_003', 'confidence' => 0.89],
];

\$overlayPath = OMRSimulator::createOverlay(\$filledPng, \$detectedMarks, \$coordinates);
echo '✓ Overlay created: ' . \$overlayPath . PHP_EOL;
"
```

**View overlay (green circles on detected bubbles):**
```bash
open storage/omr-output/CURRIMAO-001-ballot-2025-05-12_filled_overlay.png
```

---

## 🧪 Run Individual Test Scenarios

### Test 1: Normal Voting (5 marks)

```bash
php artisan test tests/Feature/OMRAppreciationTest.php --filter="appreciates simulated Philippine ballot correctly"
```

### Test 2: Overvote Scenario (2 marks for President)

```bash
php artisan test tests/Feature/OMRAppreciationTest.php --filter="overvote"
```

### Test 3: Faint Marks (50% intensity)

```bash
php artisan test tests/Feature/OMRAppreciationTest.php --filter="faint"
```

---

## 📊 View Test Artifacts

After running tests, view generated artifacts:

```bash
# List all artifacts
ls -lh storage/app/tests/artifacts/appreciation/

# View blank ballot
open storage/app/tests/artifacts/appreciation/blank_sheet.png

# View filled ballot
open storage/app/tests/artifacts/appreciation/filled_sheet.png

# View detection overlay
open storage/app/tests/artifacts/appreciation/appreciation_overlay.png

# View JSON report
cat storage/app/tests/artifacts/appreciation/appreciation_report.json | jq
```

---

## 🔧 Troubleshooting

### Issue: "ModuleNotFoundError: No module named 'cv2'"

**Fix:**
```bash
cd packages/omr-appreciation/omr-python
pip3 install --break-system-packages opencv-python-headless numpy
```

### Issue: "Could not detect 4 fiducial markers"

**Possible causes:**
- Fiducials too faint in PNG conversion
- DPI mismatch
- Need to adjust detection threshold

**Debug:**
```bash
# Use debug version of script
python3 packages/omr-appreciation/omr-python/appreciate_debug.py \
  storage/omr-output/CURRIMAO-001-ballot-2025-05-12_filled.png \
  storage/app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json
```

### Issue: "Template with layout_variant 'answer-sheet' not found"

**Fix:**
```bash
# Re-run seeders
php artisan db:seed --class=TemplateSeeder
php artisan db:seed --class=InstructionalDataSeeder

# Verify templates exist
php artisan tinker --execute="
echo 'Answer sheet templates:' . PHP_EOL;
App\Models\Template::where('layout_variant', 'answer-sheet')
  ->get(['id', 'name'])
  ->each(fn(\$t) => print('  ' . \$t->id . ': ' . \$t->name . PHP_EOL));
"
```

### Issue: "Call to undefined method: info()"

This is fixed in the test file. If you see this error, the test file has `$this->info()` calls that need to be commented out or removed.

---

## 🎯 Success Criteria

Your test passes when:

✅ **PDF Generated:** ~66KB ballot with all positions  
✅ **Coordinates Exported:** ~68KB JSON with all bubbles  
✅ **PNG Created:** ~139KB image at 300 DPI  
✅ **Bubbles Simulated:** Dark marks visible in filled PNG  
✅ **Python Executes:** No import errors  
✅ **Fiducials Detected:** 4 corner markers found  
✅ **Marks Detected:** All 5 selected bubbles identified  
✅ **Confidence High:** > 0.8 for all marks  

---

## 📖 Understanding the Output

### Coordinates JSON Structure

```json
{
  "fiducial": {
    "tl": { "x": 8.5, "y": 8.5, "width": 14.2, "height": 14.2 },
    "tr": { "x": 187.3, "y": 8.5, "width": 14.2, "height": 14.2 },
    ...
  },
  "bubble": {
    "PRESIDENT_LD_001": {
      "x": 18,
      "y": 53,
      "center_x": 20.83,
      "center_y": 55.83,
      "radius": 2.83,
      "diameter": 5.67
    },
    ...
  }
}
```

### Appreciation Result JSON

```json
{
  "document_id": "CURRIMAO-001-ballot-2025-05-12",
  "template_id": "",
  "results": [
    {
      "bubble_id": "PRESIDENT_LD_001",
      "zone": "PRESIDENT",
      "confidence": 0.95,
      "x": 20.83,
      "y": 55.83
    }
  ]
}
```

---

## 🎨 Customize Overlay Appearance

### Change Font Sizes

```bash
# Larger fonts for presentations
OMR_OVERLAY_FONT_VALID=60 OMR_OVERLAY_FONT_OTHER=50 php artisan test --filter=appreciation

# Smaller fonts for documentation
OMR_OVERLAY_FONT_VALID=25 OMR_OVERLAY_FONT_OTHER=20 php artisan test --filter=appreciation
```

### Change Colors

```bash
# Custom color scheme
OMR_OVERLAY_COLOR_VALID=green OMR_OVERLAY_COLOR_OVERVOTE=darkred php artisan test --filter=appreciation

# Blue theme
OMR_OVERLAY_COLOR_VALID='#00FF00' OMR_OVERLAY_COLOR_UNFILLED='#808080' php artisan test --filter=appreciation
```

### Hide Legend

```bash
# Minimal overlay without legend box
OMR_OVERLAY_LEGEND=false php artisan test --filter=appreciation

# Or run the full test suite
OMR_OVERLAY_LEGEND=false ./scripts/test-omr-appreciation.sh
```

### Hide Candidate Names

```bash
# Show only marks without names
OMR_OVERLAY_SHOW_NAMES=false php artisan test --filter=appreciation
```

### Permanent Configuration

Edit `config/omr-template.php` under the `overlay` section:

```php
'overlay' => [
    'fonts' => [
        'valid_marks' => 40,     // Adjust as needed
        'other_marks' => 35,
    ],
    'colors' => [
        'valid' => 'lime',       // Change colors
        'overvote' => 'red',
    ],
    'legend' => [
        'enabled' => true,       // Toggle legend
        'width' => 260,
        'height' => 140,
    ],
],
```

**See also:** [OMR Overlay Configuration Guide](../../../docs/OMR_OVERLAY_CONFIGURATION.md)

---

## 🚀 Advanced Usage

### Simulate Noise

```bash
php artisan tinker --execute="
use Tests\Helpers\OMRSimulator;

\$filledPng = storage_path('omr-output/CURRIMAO-001-ballot-2025-05-12_filled.png');
\$noisyPng = OMRSimulator::addNoise(\$filledPng, 100); // 100 random dots

echo '✓ Noisy image: ' . \$noisyPng . PHP_EOL;
"
```

### Test with Different Fill Intensities

```bash
php artisan tinker --execute="
use Tests\Helpers\OMRSimulator;

\$blankPng = storage_path('omr-output/CURRIMAO-001-ballot-2025-05-12.png');
\$coordsPath = storage_path('app/omr/coords/CURRIMAO-001-ballot-2025-05-12.json');
\$coordinates = json_decode(file_get_contents(\$coordsPath), true);

\$bubbles = ['PRESIDENT_LD_001'];

// 100% fill (black)
\$filled100 = OMRSimulator::fillBubbles(\$blankPng, \$bubbles, \$coordinates, 300, 1.0);
echo '✓ 100% filled: ' . \$filled100 . PHP_EOL;

// 50% fill (gray)
\$filled50 = str_replace('.png', '_50pct.png', \$blankPng);
copy(\$blankPng, \$filled50);
\$filled50 = OMRSimulator::fillBubbles(\$filled50, \$bubbles, \$coordinates, 300, 0.5);
echo '✓ 50% filled: ' . \$filled50 . PHP_EOL;
"
```

---

## 📚 Related Documentation

- [OMR Appreciation Test Plan (Original)](./OMR_APPREICATION_TEST_PLAN.md)
- [OMR Appreciation Test Plan (Revised)](./OMR_APPRECIATION_TEST_PLAN_REVISED.md)
- Python Script: `packages/omr-appreciation/omr-python/appreciate.py`
- Test File: `tests/Feature/OMRAppreciationTest.php`
- Helper Class: `tests/Helpers/OMRSimulator.php`

---

## 🎓 What You've Built

This testing pipeline validates:

1. ✅ **Template Rendering** - PDF generation with TCPDF
2. ✅ **Coordinate Export** - Bubble positions for CV
3. ✅ **Image Conversion** - High-quality PDF→PNG
4. ✅ **Mark Simulation** - Programmatic bubble filling
5. ✅ **Appreciation Engine** - Python OpenCV mark detection
6. ✅ **Result Validation** - Automated assertions
7. ✅ **Visual Verification** - Overlay generation

**This is production-ready OMR testing infrastructure!** 🎉
