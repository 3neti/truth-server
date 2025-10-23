# Test Script for TCPDF PDF Generation

## Quick Start

Run the test script to generate sample ballots:

```bash
php test-tcpdf-generation.php
```

## What It Does

The script generates 4 different test ballots to verify TCPDF functionality:

### Test 1: Basic Ballot
- **File**: `TEST-BALLOT-[timestamp].pdf`
- **Contains**:
  - 4 fiducial markers (corners)
  - PDF417 barcode
  - 3 OMR bubbles
  - Text labels

### Test 2: Sample Layout
- **File**: `SAMPLE-[timestamp].pdf`
- **Contains**: Full sample from `resources/templates/sample_layout.json`
  - Fiducial markers
  - PDF417 barcode
  - 8 OMR bubbles (2 questions, 4 options each)
  - Question text and labels

### Test 3: Minimal Ballot
- **File**: `MINIMAL-[timestamp].pdf`
- **Contains**: Only fiducial markers (no content)
- **Purpose**: Test marker positioning accuracy

### Test 4: Full Election Ballot
- **File**: `ELECTION-[timestamp].pdf`
- **Contains**:
  - Fiducial markers
  - PDF417 barcode
  - Title: "ELECTION BALLOT 2024"
  - 2 contests (President, Vice President)
  - 5 total candidates with OMR bubbles

## Output Location

Generated PDFs are saved to:
```
storage/app/ballots/
```

## Verification Steps

1. **Run the test**:
   ```bash
   php test-tcpdf-generation.php
   ```

2. **Check output**:
   ```bash
   ls -lh storage/app/ballots/
   ```

3. **Open PDFs** in a PDF viewer to verify:
   - Fiducial markers at corners (10,10), (190,10), (10,277), (190,277)
   - PDF417 barcode at bottom-left
   - OMR bubbles (hollow circles) at specified positions
   - Text elements properly positioned

4. **Print test** (optional):
   - Print at 300 DPI
   - Verify fiducial markers are exactly 10mm × 10mm squares
   - Verify bubbles are 5mm diameter (2.5mm radius)

## Coordinate System

- **Unit**: Millimeters (mm)
- **Origin**: Top-left corner (0, 0)
- **Page Size**: A4 (210mm × 297mm)
- **DPI**: 300 (for scanning)
- **Conversion**: 1 mm ≈ 11.811 pixels @ 300 DPI

### Fiducial Marker Positions

| Corner        | PDF (mm)   | OpenCV (px @ 300dpi) |
|---------------|------------|----------------------|
| Top-left      | (10, 10)   | (118, 118)           |
| Top-right     | (190, 10)  | (2244, 118)          |
| Bottom-left   | (10, 277)  | (118, 3272)          |
| Bottom-right  | (190, 277) | (2244, 3272)         |

## Troubleshooting

### Permission Error
If you get a permission error:
```bash
chmod +x test-tcpdf-generation.php
```

### Directory Not Found
The script automatically creates `storage/app/ballots/` if it doesn't exist.

### PDF417 Barcode Not Showing
TCPDF's PDF417 support is built-in. If it doesn't appear:
- Check TCPDF version: `composer show tecnickcom/tcpdf`
- Verify barcode content is not empty

## Next Steps

After verifying PDFs:

1. **Test with Scanner**:
   - Print a ballot at 300 DPI
   - Scan it at 300 DPI
   - Verify fiducial markers are detected

2. **Test with Python**:
   ```bash
   python appreciate.py --ballot=SAMPLE-[timestamp].pdf
   ```

3. **Generate Real Ballots**:
   - Use the Laravel command: `php artisan omr:generate`
   - Or use `OMRTemplateGenerator` in your code

## Related Files

- **Service**: `src/Services/OMRTemplateGenerator.php`
- **Tests**: `tests/Feature/OMRTemplateGenerationTest.php`
- **Sample Layout**: `resources/templates/sample_layout.json`
- **Migration Guide**: `TCPDF_MIGRATION.md`
- **Summary**: `MIGRATION_SUMMARY.md`
