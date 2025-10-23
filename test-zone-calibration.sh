#!/bin/bash

# Zone Calibration and Visual Test Script
# This script generates a ballot and overlays zone boundaries for visual inspection

set -e

echo "🎯 OMR Zone Calibration Tool"
echo "============================="
echo ""
echo "This tool helps visualize and calibrate zone positions:"
echo "  - Generates a ballot PDF"
echo "  - Converts to image"
echo "  - Overlays zone boundaries in RED"
echo "  - Marks detected zones in GREEN"
echo ""

# Configuration
TEST_ID="CALIB-$(date +%s)"
OUTPUT_DIR="storage/omr-calibration"
DATA_FILE="${OUTPUT_DIR}/calibration-ballot.json"
TEMPLATE="ballot-v1"

# Create output directory
mkdir -p "$OUTPUT_DIR"

echo "📝 Step 1: Creating calibration ballot data..."
cat > "$DATA_FILE" << 'EOF'
{
  "document_type": "Calibration Test Ballot",
  "metadata": {
    "precinct": "Calibration Test",
    "election_date": "2025-10-23",
    "generated_at": "2025-10-23T00:00:00Z"
  },
  "contests_or_sections": [
    {
      "title": "President",
      "instruction": "Vote for one (1)",
      "candidates": [
        {"name": "Alice Johnson", "party": "Party A"},
        {"name": "Bob Smith", "party": "Party B"}
      ]
    },
    {
      "title": "Vice President",
      "instruction": "Vote for one (1)",
      "candidates": [
        {"name": "Carol White", "party": "Party C"},
        {"name": "David Brown", "party": "Party D"}
      ]
    }
  ]
}
EOF

echo "✅ Calibration data created"
echo ""

echo "📄 Step 2: Generating ballot PDF with zones..."
php artisan omr:generate "$TEMPLATE" "$TEST_ID" --data="$DATA_FILE"

PDF_FILE="storage/omr-output/${TEST_ID}.pdf"
JSON_FILE="storage/omr-output/${TEST_ID}.json"

if [ ! -f "$PDF_FILE" ] || [ ! -f "$JSON_FILE" ]; then
    echo "❌ Error: PDF or template not generated"
    exit 1
fi

echo "✅ PDF generated: $PDF_FILE"
echo "✅ Template generated: $JSON_FILE"
echo ""

# Check for required tools
if ! command -v magick &> /dev/null && ! command -v convert &> /dev/null; then
    echo "❌ ImageMagick not found"
    exit 1
fi

if ! command -v gs &> /dev/null; then
    echo "❌ Ghostscript not found"
    exit 1
fi

echo "🖼️  Step 3: Converting PDF to image..."
BASE_IMAGE="${OUTPUT_DIR}/ballot-base.jpg"
if command -v magick &> /dev/null; then
    magick -density 300 "$PDF_FILE" -quality 90 "$BASE_IMAGE"
else
    convert -density 300 "$PDF_FILE" -quality 90 "$BASE_IMAGE"
fi

echo "✅ Base image created"
echo ""

echo "🎨 Step 4: Overlaying zone boundaries..."

# Extract zones from template
if ! command -v jq &> /dev/null; then
    echo "❌ jq required for zone extraction. Install with: brew install jq"
    exit 1
fi

ZONE_COUNT=$(jq '.zones | length' "$JSON_FILE")
echo "   Found $ZONE_COUNT zones to visualize"

# Create overlay image
OVERLAY_IMAGE="${OUTPUT_DIR}/ballot-with-zones.jpg"
cp "$BASE_IMAGE" "$OVERLAY_IMAGE"

# Build ImageMagick draw commands for each zone
DRAW_COMMANDS=""
for i in $(seq 0 $((ZONE_COUNT - 1))); do
    ZONE_X=$(jq -r ".zones[$i].x" "$JSON_FILE")
    ZONE_Y=$(jq -r ".zones[$i].y" "$JSON_FILE")
    ZONE_W=$(jq -r ".zones[$i].width" "$JSON_FILE")
    ZONE_H=$(jq -r ".zones[$i].height" "$JSON_FILE")
    ZONE_ID=$(jq -r ".zones[$i].id" "$JSON_FILE")
    
    # Calculate end coordinates
    ZONE_X2=$((ZONE_X + ZONE_W))
    ZONE_Y2=$((ZONE_Y + ZONE_H))
    
    # Add rectangle outline in RED with transparency
    DRAW_COMMANDS="${DRAW_COMMANDS} -stroke red -strokewidth 3 -fill none -draw \"rectangle $ZONE_X,$ZONE_Y $ZONE_X2,$ZONE_Y2\""
    
    # Add zone ID label
    LABEL_Y=$((ZONE_Y - 10))
    if [ $LABEL_Y -lt 20 ]; then
        LABEL_Y=$((ZONE_Y2 + 20))
    fi
    DRAW_COMMANDS="${DRAW_COMMANDS} -fill red -pointsize 20 -annotate +${ZONE_X}+${LABEL_Y} \"$ZONE_ID\""
    
    echo "   Zone $((i+1)): $ZONE_ID at ($ZONE_X,$ZONE_Y) size ${ZONE_W}x${ZONE_H}"
done

# Apply overlays
if command -v magick &> /dev/null; then
    eval "magick \"$OVERLAY_IMAGE\" $DRAW_COMMANDS \"$OVERLAY_IMAGE\""
else
    eval "convert \"$OVERLAY_IMAGE\" $DRAW_COMMANDS \"$OVERLAY_IMAGE\""
fi

echo "✅ Zone boundaries overlaid in RED"
echo ""

echo "✅ Calibration Complete!"
echo ""
echo "📁 Generated Files:"
echo "   - Original PDF: $PDF_FILE"
echo "   - Base Image: $BASE_IMAGE"
echo "   - Calibration Image: $OVERLAY_IMAGE"
echo ""
echo "📊 Zone Information:"
jq '.zones[] | {id: .id, x: .x, y: .y, width: .width, height: .height, candidate: .candidate}' "$JSON_FILE"
echo ""
echo "🔍 Visual Inspection:"
echo "   Open the calibration image to verify zone alignment:"
echo "   open $OVERLAY_IMAGE"
echo ""
echo "   RED rectangles = Zone boundaries (where marks will be detected)"
echo "   The rectangles should align with the ballot checkboxes"
echo ""
echo "💡 Adjustment Tips:"
echo "   - If zones are too high/low: Adjust 'start_y' in ZoneGenerator"
echo "   - If zones are too left/right: Adjust 'mark_x' in ZoneGenerator"
echo "   - If spacing is wrong: Adjust 'candidate_spacing' or 'title_spacing'"
echo ""
