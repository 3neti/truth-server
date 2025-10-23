# Manual Printed Ballot Testing Guide

## Quick Start

Run the automated test script:

```bash
./test-omr-printed-manual.sh
```

The script will guide you through each step.

---

## Manual Steps (if not using script)

### Step 1: Generate Ballot

```bash
php artisan omr:generate ballot-v1 MY-TEST --data=test-data.json
```

This creates:
- `storage/omr-output/MY-TEST.pdf` (print this)
- `storage/omr-output/MY-TEST.json` (template for detection)

---

### Step 2: Print the PDF

**⚠️ CRITICAL: Print at 100% scale!**

#### macOS:
1. Open PDF in Preview or Adobe Reader
2. File → Print
3. Click "Show Details"
4. **Set Scale to 100%**
5. Uncheck "Scale to fit" or "Fit to page"
6. Print

#### Common Mistakes:
- ❌ Using "Fit to Page" - destroys alignment
- ❌ Scale set to "Auto" - changes dimensions
- ❌ Two-sided printing with scaling
- ✅ Use "Actual Size" or "100% Scale"

---

### Step 3: Mark the Ballot

Use a **dark pen** or **#2 pencil**:

✓ Fill circles completely  
✓ Stay inside the circle  
✓ Make marks as dark as possible  
✓ Don't use X or checkmarks

Example:
```
○ Red      ← Don't mark
● Blue     ← Mark this (filled circle)
○ Green    ← Don't mark
```

---

### Step 4: Scan the Ballot

#### Scanner Settings:
- Resolution: **300 DPI** (minimum 200 DPI)
- Color: Grayscale or Color
- Format: JPG or PNG
- Save to: `storage/omr-scans/MY-TEST-filled.jpg`

#### Smartphone Photo:
- Well-lit area
- Camera directly above (not angled)
- Entire page visible including all 4 corners
- Clear and in-focus
- Transfer to computer

---

### Step 5: Run Detection

```bash
php artisan omr:appreciate-python \
    storage/omr-scans/MY-TEST-filled.jpg \
    storage/omr-output/MY-TEST.json \
    --output=storage/omr-scans/MY-TEST-results.json \
    --threshold=0.25 \
    --debug
```

---

### Step 6: Check Results

```bash
# View results
cat storage/omr-scans/MY-TEST-results.json | jq

# Count detected marks
jq '[.results[] | select(.filled == true)] | length' storage/omr-scans/MY-TEST-results.json

# Show detected marks only
jq '.results[] | select(.filled == true)' storage/omr-scans/MY-TEST-results.json

# View debug image
open storage/omr-scans/MY-TEST-filled-debug.jpg
```

---

## Troubleshooting

### No Marks Detected

**Problem**: Fill ratio too low

**Solutions**:
1. Check debug image (`*-debug.jpg`) to see zone alignment
2. Use darker pen/pencil
3. Fill circles more completely
4. Lower threshold: `--threshold=0.20`
5. Rescan at higher DPI (300+)

---

### Wrong Marks Detected

**Problem**: Smudges or stray marks

**Solutions**:
1. Clean ballot (no smudges)
2. Erase stray marks
3. Increase threshold: `--threshold=0.30`
4. Check `warnings` field in results

---

### Zones Misaligned

**Problem**: Print scaling or scan quality

**Solutions**:
1. ⚠️ **Print must be at 100% scale**
2. Rescan at 300 DPI
3. Ensure camera is directly above (not angled)
4. Check fiducials visible in debug image

---

### Fiducials Not Detected

**Problem**: Image quality or cropping

**Solutions**:
1. Scan entire page including margins
2. Increase scan resolution
3. Ensure good lighting (no shadows on corners)
4. Check all 4 black squares visible in image

---

## Expected Results

### Success Output:
```
🎉 SUCCESS! Both marks detected correctly!
   Accuracy: 100%
```

### Typical Fill Ratios:
- Well-filled mark: 0.40 - 0.55
- Light mark: 0.25 - 0.40
- Unfilled: < 0.15

### Typical Confidence:
- High confidence: 0.70 - 0.90
- Medium confidence: 0.50 - 0.70
- Low confidence: < 0.50

---

## Files Generated

| File | Description |
|------|-------------|
| `*-filled.jpg` | Your scanned ballot |
| `*-results.json` | Detection results with metrics |
| `*-debug.jpg` | Visualization showing zones |
| `*-debug_original.jpg` | Shows detected fiducials |

---

## Configuration Options

### Adjust Mark Detection Sensitivity

Edit `.env` or config:

```env
# Lower = more sensitive (may get false positives)
# Higher = less sensitive (may miss light marks)
OMR_MARK_BOX_STYLE=circle  # circle, square, or rounded
OMR_MARK_BOXES_ENABLED=true
```

### Adjust Detection Threshold

Via command line:
```bash
--threshold=0.20  # More sensitive
--threshold=0.30  # Less sensitive (default)
--threshold=0.40  # Much less sensitive
```

---

## Next Steps After Testing

1. **If successful**: Great! Mark boxes are working correctly
2. **If partially successful**: Adjust threshold or marking technique
3. **If failed**: Check print scale, scan quality, and debug images

---

## Support

Check these files for more info:
- `resources/docs/OMR_METRICS_GUIDE.md` - Understanding metrics
- `resources/docs/PHASE_3_IMPLEMENTATION_ROADMAP.md` - Phase 3 details
- Debug images - Visual alignment check

Run test again with different settings to find optimal configuration.
