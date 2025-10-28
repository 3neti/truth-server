# OMR Appreciation Testing Guide

## Overview

The OMR (Optical Mark Recognition) appreciation testing system validates ballot mark detection accuracy using simulated ballots. All test artifacts are organized in timestamped directories for easy comparison and historical tracking.

## Quick Start

### Run All Tests
```bash
./scripts/test-omr-appreciation.sh
```

This single command will:
- Create a timestamped run directory
- Execute all 3 test scenarios
- Generate comprehensive documentation
- Save all artifacts (images, overlays, results)
- Create a symlink to `latest` run

### View Latest Results
```bash
# Navigate to latest run
cd storage/app/tests/omr-appreciation/latest

# Read the documentation
cat README.md

# View overlays
open scenario-1-normal/overlay.png
open scenario-2-overvote/overlay.png
open scenario-3-faint/overlay.png

# Inspect JSON results
cat scenario-1-normal/results.json | jq
cat test-results.json | jq
```

## Directory Structure

```
storage/app/tests/omr-appreciation/
├── latest -> runs/2025-10-28_161706          # Symlink to latest run
└── runs/
    └── 2025-10-28_161706/                    # Timestamped run directory
        ├── README.md                         # Auto-generated documentation
        ├── test-results.json                 # Machine-readable summary
        ├── test-output.txt                   # Raw test output
        ├── environment.json                  # Environment info
        ├── template/                         # Source files
        │   ├── ballot.pdf
        │   └── coordinates.json
        ├── scenario-1-normal/                # Normal ballot test
        │   ├── blank.png                     # Original ballot
        │   ├── blank_filled.png              # Filled ballot
        │   ├── overlay.png                   # Visual verification
        │   ├── results.json                  # Appreciation results
        │   └── metadata.json                 # Test metadata
        ├── scenario-2-overvote/              # Overvote detection
        │   └── ...
        └── scenario-3-faint/                 # Faint marks
            └── ...
```

## Test Scenarios

### Scenario 1: Normal Ballot
Tests standard mark detection with 5 deliberately filled bubbles.

**Bubbles Filled:**
- `PRESIDENT_LD_001` - Leonardo DiCaprio
- `VICE-PRESIDENT_VD_002` - Viola Davis  
- `SENATOR_JD_001` - Johnny Depp
- `SENATOR_ES_002` - Emma Stone
- `SENATOR_MF_003` - Morgan Freeman

**Expected Result:** All 5 bubbles detected with fill_ratio ≥ 0.95

### Scenario 2: Overvote Detection
Tests detection of multiple marks for a single-choice position.

**Bubbles Filled:**
- `PRESIDENT_LD_001` - Leonardo DiCaprio
- `PRESIDENT_SJ_002` - Scarlett Johansson (overvote!)

**Expected Result:** Both President marks detected

### Scenario 3: Faint Marks
Tests sensitivity to partially filled marks.

**Configuration:**
- Fill intensity: 70% (vs 100% normal)
- Detection threshold: 0.25 (vs 0.30 normal)

**Expected Result:** Mark detected but demonstrates threshold tuning challenges

## Key Files in Each Scenario

### blank.png
Original ballot template rendered from PDF at 300 DPI.

### blank_filled.png
Simulated filled ballot with black circles drawn at bubble coordinates.

### overlay.png
Visual overlay showing detected marks (green circles) with confidence percentages.

### results.json
Complete appreciation results including:
- Bubble ID
- Fill ratio (0.0 to 1.0)
- Confidence score
- Quality metrics
- Warnings

### metadata.json
Test configuration:
- Scenario description
- Bubbles that should be filled
- Bubbles that were detected
- Timestamp

## Running Individual Tests

```bash
# Run specific scenario
php artisan test tests/Feature/OMRAppreciationTest.php --filter="appreciates simulated Philippine ballot correctly"

# Run overvote test only
php artisan test tests/Feature/OMRAppreciationTest.php --filter="handles overvote"

# Run faint marks test only
php artisan test tests/Feature/OMRAppreciationTest.php --filter="handles faint"
```

## Understanding Results

### Fill Ratio
Percentage of dark pixels in the bubble area:
- **0.95-1.0**: Strong deliberate mark
- **0.5-0.9**: Moderate mark or artifact
- **0.3-0.5**: Faint mark or noise
- **0.0-0.3**: Essentially blank

### Confidence Score
Overall confidence in the detection (0.0 to 1.0):
- **> 0.8**: High confidence
- **0.5-0.8**: Moderate confidence  
- **< 0.5**: Low confidence (warning issued)

### Warnings
- `ambiguous`: Fill ratio in uncertain range (0.15-0.45)
- `low_confidence`: Confidence < 0.5
- `non_uniform`: Inconsistent marking
- `overfilled`: Fill ratio > 0.7 (possible stray mark)

## Technical Details

### Coordinate System
- All coordinates stored in millimeters
- Converted to pixels at 300 DPI (11.811 px/mm)
- Bubble centers used for filling
- Top-left corners used for ROI extraction

### Alignment
- `--no-align` flag used for synthetic test images
- Skips fiducial-based perspective transformation
- Real scanned ballots should use alignment

### Filtering
- High-confidence filter: `fill_ratio >= 0.95`
- Excludes template artifacts and noise
- Adjustable based on scanner characteristics

## Troubleshooting

### Tests Fail
Check `test-output.txt` for detailed error messages:
```bash
cat storage/app/tests/omr-appreciation/latest/test-output.txt
```

### Wrong Bubbles Detected
1. Check overlay.png to visually verify
2. Review fill_ratio values in results.json
3. Adjust threshold if needed
4. Verify coordinate mappings in template/coordinates.json

### Missing Dependencies
Run environment check:
```bash
cat storage/app/tests/omr-appreciation/latest/environment.json | jq
```

Required:
- PHP with Imagick extension
- Python 3 with OpenCV (cv2)
- ImageMagick

## Integration with CI/CD

```bash
# Run tests and check exit code
if ./scripts/test-omr-appreciation.sh; then
    echo "✓ OMR tests passed"
    # Archive artifacts
    tar -czf omr-test-results.tar.gz storage/app/tests/omr-appreciation/latest/
else
    echo "✗ OMR tests failed"
    cat storage/app/tests/omr-appreciation/latest/test-output.txt
    exit 1
fi
```

## Comparing Runs

```bash
# List all runs
ls -lt storage/app/tests/omr-appreciation/runs/

# Compare two runs
diff \
  storage/app/tests/omr-appreciation/runs/2025-10-28_101530/scenario-1-normal/metadata.json \
  storage/app/tests/omr-appreciation/runs/2025-10-28_161706/scenario-1-normal/metadata.json

# View historical results
jq '.summary' storage/app/tests/omr-appreciation/runs/*/test-results.json
```

## Best Practices

1. **Run tests after code changes** affecting:
   - Ballot rendering
   - Coordinate generation
   - Mark detection algorithms

2. **Keep historical runs** for regression testing:
   ```bash
   # Archive old runs (keep last 10)
   cd storage/app/tests/omr-appreciation/runs
   ls -t | tail -n +11 | xargs rm -rf
   ```

3. **Visual verification**: Always check overlay images for unexpected behavior

4. **Threshold tuning**: Adjust based on your scanner characteristics

5. **Document changes**: Update test scenarios when ballot format changes

## Contributing

To add new test scenarios:

1. Add a new test in `tests/Feature/OMRAppreciationTest.php`
2. Create scenario directory: `scenario-N-description/`
3. Follow existing naming conventions
4. Include metadata.json with test parameters
5. Update this documentation

## Support

For issues or questions:
- Check `README.md` in the latest run directory
- Review test output and error messages
- Examine overlay images for visual debugging
- Consult coordinate mappings in template/coordinates.json

---

*Last updated: $(date '+%Y-%m-%d')*
