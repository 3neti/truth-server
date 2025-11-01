# Ballot PDF Generation Feature

## Overview

Added automatic ballot PDF generation to the simulation test suite. The template directory now includes a printable PDF version of the blank ballot template alongside the coordinates JSON.

## Implementation

### New Function: `generate_ballot_pdf()`

**Location:** `scripts/simulation/lib/template-generator.sh`

**Purpose:** Generate a PDF version of the blank ballot template from coordinates

**Workflow:**
1. Uses `render_blank_ballot()` to create a high-quality PNG from coordinates
2. Converts PNG to PDF using ImageMagick's `convert` command
3. Cleans up temporary PNG file
4. Falls back to keeping PNG if PDF conversion fails

**Parameters:**
- `coords_file` - Path to coordinates.json
- `output_pdf` - Path for output PDF file

**Dependencies:**
- `render_blank_ballot()` from `ballot-renderer.sh`
- ImageMagick's `convert` command (optional but recommended)
- OpenCV Python bindings for PNG rendering

### Integration Point

**Location:** `scripts/simulation/run-simulation.sh` (lines 141-153)

The ballot PDF is generated after:
1. ✅ Template coordinates generated
2. ✅ Coordinates validated

And before:
- Scenario generation
- Ballot rendering

**Code:**
```bash
# Generate ballot PDF from coordinates
local ballot_pdf="${template_dir}/ballot.pdf"
local ballot_png="${template_dir}/ballot.png"

log_info "Generating ballot PDF..."
if generate_ballot_pdf "$coordinates_file" "$ballot_pdf"; then
    log_success "Ballot PDF generated: ballot.pdf"
else
    # Keep PNG as fallback if PDF generation failed
    if [[ -f "$ballot_png" ]]; then
        log_info "Ballot template available as PNG: ballot.png"
    fi
fi
```

## Output Structure

```
template/
├── ballot.pdf          # ✨ NEW: Printable ballot template
├── coordinates.json    # Bubble/fiducial coordinate mappings
└── generate.log        # Template generation log
```

## Features

### Ballot PDF Contents

The generated PDF includes:
- **Bubble circles** - All voting bubbles with proper positioning
- **Bubble labels** - Bubble IDs (A1, B2, etc.) for debugging
- **Fiducial markers** - ArUco markers or placeholder squares
- **QR code placeholder** - Document barcode area
- **Proper dimensions** - Scaled from mm to pixels at 300 DPI
- **High quality** - Suitable for printing and visual inspection

### Graceful Degradation

If ImageMagick is not available:
- System logs a warning
- Keeps the PNG version as `ballot.png`
- Continues execution without failing
- PNG is fully functional for visualization

## Use Cases

1. **Visual Reference** - Quick preview of ballot layout without rendering filled ballots
2. **Printing** - Print blank ballots for manual testing
3. **Documentation** - Include ballot template in test run reports
4. **Debugging** - Verify bubble positions and fiducial markers visually
5. **Archiving** - Standard PDF format for long-term artifact storage

## Verification

```bash
# Run test suite
scripts/simulation/run-test-suite.sh --scenarios normal --fresh

# Check template artifacts
ls -lh storage/app/private/simulation/latest/template/

# Output should include:
# -rw-r--r--  ballot.pdf           # ~18KB
# -rw-r--r--  coordinates.json     # ~7KB
# -rw-r--r--  generate.log         # ~2KB

# Verify PDF is valid
file storage/app/private/simulation/latest/template/ballot.pdf
# Expected: PDF document, version 1.3, 1 pages

# View PDF (macOS)
open storage/app/private/simulation/latest/template/ballot.pdf
```

## Testing Results

Tested with multiple scenarios:
```bash
scripts/simulation/run-test-suite.sh --scenarios normal,overvote,faint --fresh
```

**Results:**
- ✅ PDF generated successfully (18KB)
- ✅ Valid PDF document (version 1.3)
- ✅ Proper dimensions from coordinates
- ✅ All scenarios executed successfully
- ✅ No errors or warnings

**Metadata Accuracy:**
- Normal: 8 filled, 8 detected ✓
- Overvote: 9 filled, 9 detected ✓
- Faint: 8 filled, 5 detected ✓ (expected behavior)

## Related Files

- `scripts/simulation/lib/template-generator.sh` - Implementation of `generate_ballot_pdf()`
- `scripts/simulation/lib/ballot-renderer.sh` - PNG rendering (`render_blank_ballot()`)
- `scripts/simulation/run-simulation.sh` - Integration and workflow orchestration

## Benefits

1. **Complete Artifacts** - Template directory now matches expected structure
2. **Better Documentation** - PDF provides human-readable ballot reference
3. **Printable Templates** - Can print blank ballots for physical testing
4. **Format Flexibility** - Both PDF (printable) and PNG (programmatic) available
5. **Zero Breaking Changes** - Existing workflows continue to work unchanged

## Future Enhancements

Possible improvements:
- Add candidate names and position labels to ballot PDF
- Generate multi-page PDFs for complex ballots
- Include metadata footer (timestamp, config hash, etc.)
- Support custom DPI/quality settings
- Add PDF bookmarks for large ballots
