#!/bin/bash

# Simulated Calibration Test
# Generates a calibration sheet, simulates marks, and verifies detection

set -e

echo "🔧 Automated Calibration Test"
echo "============================="
echo ""
echo "This test generates a calibration sheet, simulates marks, and verifies detection."
echo ""

# Configuration
CAL_ID="CAL-TEST-$(date +%s)"
OUTPUT_DIR="storage/omr-output"
ZONES=12
PATTERN="grid"

# Generate calibration sheet
echo "📝 Step 1: Generating calibration sheet..."
echo "   ID: $CAL_ID"
echo "   Zones: $ZONES"
echo "   Pattern: $PATTERN"
echo ""

php -d memory_limit=512M artisan omr:generate-calibration "$CAL_ID" \
    --zones=$ZONES \
    --pattern=$PATTERN

CAL_PDF="${OUTPUT_DIR}/${CAL_ID}.pdf"
CAL_JSON="${OUTPUT_DIR}/${CAL_ID}.json"
CAL_FILLED="${OUTPUT_DIR}/${CAL_ID}-filled.jpg"

echo ""
echo "✅ Calibration sheet generated!"
echo ""

# Convert PDF to image for simulation
echo "📊 Step 2: Converting PDF to image..."

if ! command -v convert &> /dev/null; then
    echo "⚠️  ImageMagick not found. Install with: brew install imagemagick"
    echo ""
    echo "Alternative: Manually print, mark, and scan the calibration sheet:"
    echo "   PDF: $CAL_PDF"
    echo "   Then run: php artisan omr:verify-calibration <scan> $CAL_JSON"
    exit 0
fi

convert -density 300 -colorspace Gray "${CAL_PDF}[0]" "$CAL_FILLED"
echo "   Image saved: $CAL_FILLED"
echo ""

# Simulate marks on ALL zones
echo "✏️  Step 3: Simulating marks on all zones..."

# Get zone positions from JSON
ZONES_JSON=$(jq -c '.zones[]' "$CAL_JSON")

# Use Python to draw marks
python3 - "$CAL_FILLED" "$CAL_JSON" <<'PYTHON'
import sys
import json
import cv2
import numpy as np

# Load image
image_path = sys.argv[1]
template_path = sys.argv[2]

img = cv2.imread(image_path)
if img is None:
    print(f"Error: Could not load image {image_path}", file=sys.stderr)
    sys.exit(1)

# Load zones
with open(template_path, 'r') as f:
    template = json.load(f)

zones = template['zones']

# Draw marks on all zones
for zone in zones:
    cx = zone['x']
    cy = zone['y']
    w = zone['width']
    h = zone['height']
    
    # Calculate circle parameters
    radius = min(w, h) // 2 - 2  # Slightly smaller than zone
    
    # Draw filled circle (dark)
    cv2.circle(img, (cx, cy), radius, (50, 50, 50), -1)  # Dark gray fill
    
    print(f"Marked zone {zone['id']} at ({cx}, {cy})", file=sys.stderr)

# Save marked image
cv2.imwrite(image_path, img)
print(f"✅ Saved marked image to {image_path}", file=sys.stderr)
PYTHON

echo "✅ All zones marked!"
echo ""

# Run verification
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔍 Step 4: Running calibration verification..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

RESULT_JSON="${OUTPUT_DIR}/${CAL_ID}-results.json"

php -d memory_limit=512M artisan omr:verify-calibration \
    "$CAL_FILLED" \
    "$CAL_JSON" \
    --threshold=0.25 \
    --output="$RESULT_JSON" \
    --debug \
    -v

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📁 Generated Files:"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "   PDF:        $CAL_PDF"
echo "   Template:   $CAL_JSON"
echo "   Filled:     $CAL_FILLED"
echo "   Results:    $RESULT_JSON"
if [ -f "${CAL_FILLED%.jpg}-debug.jpg" ]; then
    echo "   Debug:      ${CAL_FILLED%.jpg}-debug.jpg"
fi
echo ""

echo "✅ Calibration test complete!"
echo ""
echo "💡 To view the debug visualization:"
echo "   open ${CAL_FILLED%.jpg}-debug.jpg"
echo ""
