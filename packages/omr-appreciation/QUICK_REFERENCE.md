# Ballot Cast Format - Quick Reference

## Basic Workflow

```bash
# Appreciate ballot → Generate compact format → Cast to Laravel
python appreciate.py ballot.png template.json --config-path config/ | \
  python extract_ballot_cast.py /dev/stdin --pipe-mode | bash
```

## Format

```
BALLOT-ID|POSITION:CODE1,CODE2;POSITION2:CODE3
```

## Commands

### Appreciation
```bash
# Basic
python appreciate.py ballot.png template.json > votes.json

# With config (for metadata lookup)
python appreciate.py ballot.png template.json --config-path config/ > votes.json

# Skip alignment (for test images)
python appreciate.py ballot.png template.json --no-align > votes.json

# Custom threshold
python appreciate.py ballot.png template.json --threshold 0.4 > votes.json
```

### Extraction

```bash
# Default: election:cast-ballot format
python extract_ballot_cast.py votes.json

# Pipe mode (recommended)
python extract_ballot_cast.py votes.json --pipe-mode

# Raw string only
python extract_ballot_cast.py votes.json --command-only

# Save to file
python extract_ballot_cast.py votes.json --output ballot.sh --pipe-mode
```

### Laravel Integration

```bash
# Direct command
php artisan election:cast-ballot "BAL-001|PRESIDENT:AJ_006;SENATOR:ES_002"

# Piped (recommended)
echo "BAL-001|PRESIDENT:AJ_006;SENATOR:ES_002" | php artisan election:cast
```

## Batch Processing

```bash
# Process all ballots in directory
for ballot in ballots/*.png; do
  python appreciate.py "$ballot" template.json --config-path config/ | \
    python extract_ballot_cast.py /dev/stdin --pipe-mode | bash
done
```

## Output Fields

**votes.json structure:**
```json
{
  "document_id": "BAL-001",
  "ballot_cast_format": "BAL-001|PRESIDENT:AJ_006;SENATOR:ES_002,LN_048",
  "results": [...],
  "barcode": {...},
  "fiducials": {...},
  "quality": {...}
}
```

## Extract Just the Format

```bash
# Using jq
cat votes.json | jq -r '.ballot_cast_format'

# Using Python utility
python extract_ballot_cast.py votes.json --command-only
```

## Common Patterns

### Test Single Ballot
```bash
python appreciate.py test-ballot.png template.json --config-path config/ > /tmp/test.json
python extract_ballot_cast.py /tmp/test.json --pipe-mode
```

### Batch with Error Handling
```bash
for ballot in ballots/*.png; do
  echo "Processing $ballot..."
  python appreciate.py "$ballot" template.json --config-path config/ | \
    python extract_ballot_cast.py /dev/stdin --pipe-mode | bash || \
    echo "ERROR: Failed to process $ballot"
done
```

### Generate Script for Review
```bash
# Create batch script without executing
for ballot in ballots/*.png; do
  python appreciate.py "$ballot" template.json --config-path config/ | \
    python extract_ballot_cast.py /dev/stdin --pipe-mode
done > batch-cast.sh

# Review and execute
cat batch-cast.sh
bash batch-cast.sh
```

## Troubleshooting

### No ballot_cast_format in output?
```bash
# Check if appreciate.py has the latest code
grep "ballot_cast_format" packages/omr-appreciation/omr-python/appreciate.py
```

### Empty ballot string (BALLOT-ID|)?
- No marks were detected as filled
- Check threshold: `--threshold 0.2` (lower = more sensitive)
- Verify marks are visible in image

### Missing ballot ID?
- Barcode decode failed
- Add document_id to template JSON
- Check barcode region in image

## File Locations

```
packages/omr-appreciation/
├── omr-python/
│   ├── appreciate.py          # Main appreciation script
│   └── extract_ballot_cast.py # Format extraction utility
├── README.md                   # Package overview
├── BALLOT_CAST_FORMAT.md      # Complete integration guide
├── BALLOT_CAST_IMPLEMENTATION.md # Technical summary
└── QUICK_REFERENCE.md         # This file
```

## See Also

- [Complete Integration Guide](BALLOT_CAST_FORMAT.md)
- [Implementation Summary](BALLOT_CAST_IMPLEMENTATION.md)
- [Package README](README.md)
