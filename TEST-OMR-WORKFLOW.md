# OMR Workflow Testing Scripts

Two test scripts are provided to test the complete OMR workflow from PDF generation to mark appreciation.

## 🚀 Automated Test Script

**File:** `test-omr-workflow.sh`

This script fully automates the testing process using ImageMagick.

### Prerequisites

```bash
# Install ImageMagick (required for image manipulation)
brew install imagemagick

# Install Ghostscript (required for PDF to image conversion)
brew install ghostscript

# Optional: Install jq for better JSON output formatting
brew install jq
```

### Usage

```bash
cd /Users/rli/PhpstormProjects/truth
./test-omr-workflow.sh
```

### What it does

1. ✅ Creates sample ballot data
2. ✅ Generates OMR PDF with `omr:generate`
3. ✅ Converts PDF to image
4. ✅ Simulates voter marks (fills specific zones)
5. ✅ Appreciates the marked ballot with `omr:appreciate`
6. ✅ Displays results with summary

### Output

All files are saved to `storage/omr-test/`:
- `ballot-data.json` - Template data
- PDF in `storage/omr-output/TEST-*.pdf`
- Template JSON in `storage/omr-output/TEST-*.json`
- Scanned image
- Appreciation results JSON

---

## 📝 Manual Test Script

**File:** `test-omr-manual.sh`

This script generates a PDF for you to physically print, fill, and scan.

### Usage

```bash
cd /Users/rli/PhpstormProjects/truth
./test-omr-manual.sh
```

### What it does

1. ✅ Creates sample ballot data
2. ✅ Generates OMR PDF
3. ✅ Provides step-by-step instructions for manual testing

### Manual Testing Steps

After running the script:

1. **Print** the generated PDF:
   ```bash
   open storage/omr-output/MANUAL-TEST-001.pdf
   ```

2. **Fill** the ballot with pen/pencil
   - Make dark, clear marks in the boxes
   - Try filling 2-3 different boxes

3. **Scan** or photograph the filled ballot
   - Save as: `storage/omr-test/filled-ballot.jpg`
   - Ensure 300+ DPI if scanning
   - All 4 corner fiducial markers must be visible

4. **Appreciate** the ballot:
   ```bash
   php artisan omr:appreciate \
       storage/omr-test/filled-ballot.jpg \
       storage/omr-output/MANUAL-TEST-001.json \
       --output=storage/omr-test/results.json
   ```

5. **View** results:
   ```bash
   cat storage/omr-test/results.json | python3 -m json.tool
   
   # Or with jq:
   jq '.marks[] | select(.filled == true)' storage/omr-test/results.json
   ```

---

## 🧪 Direct Artisan Commands

You can also test each command individually:

### Generate a template

```bash
php artisan omr:generate ballot-v1 MY-BALLOT-001 --data=my-data.json
```

### Appreciate a scanned ballot

```bash
php artisan omr:appreciate \
    path/to/scanned.jpg \
    storage/omr-output/MY-BALLOT-001.json \
    --output=results.json \
    --threshold=0.3
```

---

## 📊 Understanding Results

The appreciation output includes:

```json
{
  "document_id": "BALLOT-ABC-001-PDF-147",
  "template_id": "ballot-v1",
  "fiducials_detected": {
    "top_left": {"x": 20, "y": 20, "size": 40},
    "top_right": {...},
    "bottom_left": {...},
    "bottom_right": {...}
  },
  "marks": [
    {
      "id": "PRES_A",
      "x": 100,
      "y": 200,
      "width": 25,
      "height": 25,
      "filled": true,
      "confidence": 0.85,
      "fill_ratio": 0.62
    }
  ],
  "summary": {
    "total_zones": 5,
    "filled_count": 2,
    "unfilled_count": 3,
    "average_confidence": 0.78
  }
}
```

### Key Fields

- **filled**: Boolean indicating if the zone is marked
- **confidence**: 0-1 score (higher = more certain)
- **fill_ratio**: Percentage of dark pixels (0-1)
- **summary**: Overall statistics

### Confidence Levels

- **> 0.8**: High confidence - Clear mark or clear empty
- **0.5-0.8**: Medium confidence
- **< 0.5**: Low confidence - May need manual review

---

## 🔧 Troubleshooting

### "ImageMagick not found"

Install ImageMagick:
```bash
brew install imagemagick
```

### "Ghostscript not found" or "gs: command not found"

Ghostscript is required for ImageMagick to convert PDFs to images:
```bash
brew install ghostscript
```

### "Could not detect all 4 fiducial markers"

- Ensure all 4 corner markers are visible in the scanned image
- Check that the scan quality is good (300+ DPI)
- Verify the image isn't cropped

### Low confidence scores

- Increase scan quality/resolution
- Use darker marks (pen instead of light pencil)
- Adjust threshold: `--threshold=0.25` (lower = more sensitive)

### "Failed to load image"

- Verify image file exists
- Supported formats: JPG, PNG, GIF
- Check file permissions

---

## 📁 Generated Files Location

All test outputs are in:
- `storage/omr-test/` - Test data and scans
- `storage/omr-output/` - Generated PDFs and templates

---

## 🎯 Quick Start

```bash
# Full automated test
./test-omr-workflow.sh

# Manual test (print & scan)
./test-omr-manual.sh
```

Both scripts are located in the project root directory.
