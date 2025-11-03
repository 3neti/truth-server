# Ballot Cast Format Integration

The OMR appreciation script now generates compact ballot strings compatible with Laravel's `election:cast-ballot` command.

## Overview

When processing a ballot, the script outputs a `ballot_cast_format` field in the JSON that can be directly used with Laravel election commands.

## Format Specification

```
BALLOT-ID|POSITION1:CODE1,CODE2;POSITION2:CODE3;POSITION3:CODE4,CODE5,CODE6
```

**Example:**
```
BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048,AA_018;REPRESENTATIVE-PARTY-LIST:THE_MATRIX_008
```

### Components

- **BALLOT-ID**: Document ID from barcode or template (e.g., `BAL-001`)
- **Positions**: Separated by semicolons (`;`)
- **Each Position**: `POSITION_CODE:CANDIDATE_CODE1,CANDIDATE_CODE2,...`
- **Multiple Candidates**: Separated by commas (`,`)

## Usage

### 1. Basic Appreciation with Ballot Cast Format

```bash
cd packages/omr-appreciation/omr-python

python appreciate.py \
  /path/to/ballot.png \
  /path/to/template.json \
  --config-path config/ \
  > votes.json
```

**Output (`votes.json`):**
```json
{
  "document_id": "BAL-001",
  "template_id": "template-v1",
  "ballot_cast_format": "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001;SENATOR:ES_002,LN_048",
  "results": [
    {
      "id": "1",
      "contest": "PRESIDENT",
      "code": "AJ_006",
      "filled": true,
      "fill_ratio": 0.456
    }
  ]
}
```

### 2. Extract and Execute Ballot Cast Command

#### Option A: Extract to Shell Script

```bash
python extract_ballot_cast.py votes.json --output ballot-cast.sh
bash ballot-cast.sh
```

#### Option B: Direct Command Output

```bash
python extract_ballot_cast.py votes.json
# Output: php artisan election:cast-ballot "BAL-001|PRESIDENT:AJ_006;..."
```

#### Option C: Pipe Mode (Recommended)

```bash
python extract_ballot_cast.py votes.json --pipe-mode
# Output: echo "BAL-001|PRESIDENT:AJ_006;..." | php artisan election:cast
```

#### Option D: Command String Only

```bash
python extract_ballot_cast.py votes.json --command-only
# Output: BAL-001|PRESIDENT:AJ_006;...
```

### 3. End-to-End Workflow

```bash
# 1. Process ballot image
python appreciate.py ballot.png template.json --config-path config/ > votes.json

# 2. Extract and cast ballot
python extract_ballot_cast.py votes.json --pipe-mode | bash

# Or in one line:
python appreciate.py ballot.png template.json --config-path config/ | \
  python extract_ballot_cast.py /dev/stdin --pipe-mode | bash
```

### 4. Batch Processing Multiple Ballots

```bash
#!/bin/bash
# Process all ballots in a directory

for ballot_image in ballots/*.png; do
  ballot_id=$(basename "$ballot_image" .png)
  
  echo "Processing $ballot_id..."
  
  # Generate votes.json
  python appreciate.py "$ballot_image" template.json \
    --config-path config/ \
    > "output/${ballot_id}-votes.json"
  
  # Cast ballot
  python extract_ballot_cast.py "output/${ballot_id}-votes.json" --pipe-mode | bash
  
  echo "✓ Ballot $ballot_id cast successfully"
done
```

## Integration with Laravel Commands

The generated format is compatible with these Laravel election commands:

### `election:cast-ballot` (Direct Format)
```bash
php artisan election:cast-ballot "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001"
```

### `election:cast` (Piped Format - Recommended)
```bash
echo "BAL-001|PRESIDENT:AJ_006;VICE-PRESIDENT:TH_001" | php artisan election:cast
```

## Position Ordering

Positions in the ballot cast format are **sorted alphabetically** for consistency:

```
BAL-001|GOVERNOR-ILN:EN_001;PRESIDENT:AJ_006;SENATOR:ES_002,LN_048;VICE-PRESIDENT:TH_001
```

This ensures predictable output regardless of the order marks were detected.

## Error Handling

### Empty Ballots
If no marks are filled, the format will be:
```
BAL-001|
```

### Missing Document ID
If barcode decode fails and no template ID is available:
```
|PRESIDENT:AJ_006;SENATOR:ES_002
```

**Note:** Laravel commands will reject ballots without a valid ID.

## Advantages Over Vote-by-Vote Reading

| Feature | `election:read-vote` (Per Mark) | `election:cast-ballot` (Compact) |
|---------|--------------------------------|----------------------------------|
| **Speed** | Slower (multiple DB writes) | Faster (single transaction) |
| **Atomicity** | Partial ballots possible | All-or-nothing |
| **Validation** | Per-mark | Complete ballot |
| **Debugging** | Granular errors | Single error for entire ballot |
| **Output** | Verbose | Compact |
| **Best For** | Development/debugging | Production batch processing |

## Example Output Comparison

### votes.json (Full Output)
```json
{
  "document_id": "BAL-001",
  "template_id": "template-v1",
  "ballot_cast_format": "BAL-001|PRESIDENT:AJ_006;SENATOR:ES_002,LN_048,AA_018",
  "barcode": {
    "decoded": true,
    "decoder": "pyzbar",
    "barcode_type": "QRCODE"
  },
  "results": [
    {
      "id": "1",
      "contest": "PRESIDENT",
      "code": "AJ_006",
      "filled": true,
      "fill_ratio": 0.456,
      "confidence": 0.892
    },
    {
      "id": "2",
      "contest": "SENATOR",
      "code": "ES_002",
      "filled": true,
      "fill_ratio": 0.523,
      "confidence": 0.901
    }
  ]
}
```

### ballot-cast.sh (Extracted Command)
```bash
echo "BAL-001|PRESIDENT:AJ_006;SENATOR:ES_002,LN_048,AA_018" | php artisan election:cast
```

## Testing

Test the format generation with a sample ballot:

```bash
# Generate test ballot with known votes
cd packages/omr-appreciation/omr-python

python appreciate.py \
  ../../tests/fixtures/filled-ballot.png \
  ../../tests/fixtures/template.json \
  --config-path ../../../config \
  > /tmp/test-votes.json

# Verify format
cat /tmp/test-votes.json | jq -r '.ballot_cast_format'

# Test extraction
python extract_ballot_cast.py /tmp/test-votes.json

# Expected output:
# php artisan election:cast-ballot "BAL-XXX|POSITION:CODE1,CODE2;..."
```

## See Also

- [OMR Appreciation README](README.md)
- [Barcode Decoder Guide](../../../resources/docs/BARCODE_DECODER_GUIDE.md)
- [Laravel Election Commands](../../../WARP.md#ballot-processing)
