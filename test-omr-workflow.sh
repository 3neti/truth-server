#!/bin/bash

# OMR Workflow Test Script
# This script generates an OMR PDF and then appreciates it

set -e  # Exit on error

echo "🧱 OMR Workflow Test Script"
echo "=============================="
echo ""
echo "This test demonstrates the complete end-to-end OMR workflow:"
echo "  1. PDF Generation with fiducial markers and auto-generated zones"
echo "  2. PDF to Image conversion"
echo "  3. Mark simulation at actual zone positions"
echo "  4. Fiducial detection for alignment"
echo "  5. Mark detection and appreciation"
echo ""
echo "Zones are automatically generated from candidate data!"
echo ""

# Configuration
TEST_ID="TEST-$(date +%s)"
OUTPUT_DIR="storage/omr-test"
DATA_FILE="${OUTPUT_DIR}/ballot-data.json"
TEMPLATE="ballot-v1"

# Create output directory
mkdir -p "$OUTPUT_DIR"

echo "📝 Step 1: Creating test ballot data..."
cat > "$DATA_FILE" << 'EOF'
{
  "document_type": "Sample Ballot",
  "metadata": {
    "precinct": "Precinct 001",
    "election_date": "2025-10-22",
    "generated_at": "2025-10-22T23:00:00Z"
  },
  "contests_or_sections": [
    {
      "title": "President",
      "instruction": "Vote for one (1)",
      "candidates": [
        {
          "name": "Alice Johnson",
          "party": "Progressive Party"
        },
        {
          "name": "Bob Smith",
          "party": "Conservative Party"
        }
      ]
    },
    {
      "title": "Vice President",
      "instruction": "Vote for one (1)",
      "candidates": [
        {
          "name": "David Brown",
          "party": "Progressive Party"
        },
        {
          "name": "Emma Davis",
          "party": "Conservative Party"
        }
      ]
    }
  ]
}
EOF

echo "✅ Test data created: $DATA_FILE"
echo ""

echo "📄 Step 2: Generating OMR template PDF..."
php artisan omr:generate "$TEMPLATE" "$TEST_ID" --data="$DATA_FILE"

PDF_FILE="storage/omr-output/${TEST_ID}.pdf"
JSON_FILE="storage/omr-output/${TEST_ID}.json"

if [ ! -f "$PDF_FILE" ]; then
    echo "❌ Error: PDF was not generated"
    exit 1
fi

if [ ! -f "$JSON_FILE" ]; then
    echo "❌ Error: Template JSON was not generated"
    exit 1
fi

echo "✅ PDF generated: $PDF_FILE"
echo "✅ Template generated: $JSON_FILE"
echo ""

echo "🖨️  Step 3: Simulating filled ballot..."
echo "   (Converting PDF to image and marking some zones)"

# Check if ImageMagick is available
if ! command -v magick &> /dev/null && ! command -v convert &> /dev/null; then
    echo "⚠️  ImageMagick not found. Install with: brew install imagemagick"
    echo ""
    echo "📝 Manual steps to complete the test:"
    echo "   1. Print the PDF: $PDF_FILE"
    echo "   2. Fill in some ovals/boxes (mark PRES_A and VP_B for example)"
    echo "   3. Scan or photograph the filled ballot"
    echo "   4. Save as: ${OUTPUT_DIR}/filled-ballot.jpg"
    echo "   5. Run appreciation command:"
    echo "      php artisan omr:appreciate ${OUTPUT_DIR}/filled-ballot.jpg $JSON_FILE --output=${OUTPUT_DIR}/results.json"
    echo ""
    exit 0
fi

# Check if Ghostscript is available (needed for PDF conversion)
if ! command -v gs &> /dev/null; then
    echo "⚠️  Ghostscript not found. Install with: brew install ghostscript"
    echo ""
    echo "📝 Manual steps to complete the test:"
    echo "   1. Print the PDF: $PDF_FILE"
    echo "   2. Fill in some ovals/boxes (mark PRES_A and VP_B for example)"
    echo "   3. Scan or photograph the filled ballot"
    echo "   4. Save as: ${OUTPUT_DIR}/filled-ballot.jpg"
    echo "   5. Run appreciation command:"
    echo "      php artisan omr:appreciate ${OUTPUT_DIR}/filled-ballot.jpg $JSON_FILE --output=${OUTPUT_DIR}/results.json"
    echo ""
    exit 0
fi

# Convert PDF to image (use magick if available, otherwise convert)
SCAN_IMAGE="${OUTPUT_DIR}/scanned-ballot.jpg"
if command -v magick &> /dev/null; then
    magick -density 300 "$PDF_FILE" -quality 90 "$SCAN_IMAGE"
else
    convert -density 300 "$PDF_FILE" -quality 90 "$SCAN_IMAGE"
fi

if [ ! -f "$SCAN_IMAGE" ]; then
    echo "❌ Error: Failed to convert PDF to image"
    exit 1
fi

echo "✅ PDF converted to image: $SCAN_IMAGE"
echo ""

echo "✏️  Step 4: Marking zones (simulating voter marks)..."
echo "   Note: Without predefined zones, we'll mark at estimated ballot positions"
echo "   Marking: President - Alice Johnson, Vice President - Emma Davis"

# Extract actual zone positions from the generated template
if command -v jq &> /dev/null && [ -f "$JSON_FILE" ]; then
    # Try to get zone info from template if zones exist
    ZONES_EXIST=$(jq '.zones | length' "$JSON_FILE" 2>/dev/null || echo "0")
    
    if [ "$ZONES_EXIST" -gt 0 ]; then
        # Get first candidate position for President (index 0)
        PRES_X=$(jq -r '.zones[0].x' "$JSON_FILE" 2>/dev/null || echo "350")
        PRES_Y=$(jq -r '.zones[0].y' "$JSON_FILE" 2>/dev/null || echo "950")
        PRES_W=$(jq -r '.zones[0].width' "$JSON_FILE" 2>/dev/null || echo "60")
        PRES_H=$(jq -r '.zones[0].height' "$JSON_FILE" 2>/dev/null || echo "60")
        
        # Get second candidate position for Vice President (index 3)
        VP_X=$(jq -r '.zones[3].x' "$JSON_FILE" 2>/dev/null || echo "350")
        VP_Y=$(jq -r '.zones[3].y' "$JSON_FILE" 2>/dev/null || echo "1650")
        VP_W=$(jq -r '.zones[3].width' "$JSON_FILE" 2>/dev/null || echo "60")
        VP_H=$(jq -r '.zones[3].height' "$JSON_FILE" 2>/dev/null || echo "60")
        
        echo "   Using zone positions from template: President($PRES_X,$PRES_Y) VP($VP_X,$VP_Y)"
    else
        # Fallback: estimated positions based on typical A4 ballot at 300 DPI
        # First checkbox is typically around 350px from left, 950px from top
        PRES_X=350
        PRES_Y=950
        PRES_W=60
        PRES_H=60
        
        # VP section typically 700px below President section
        VP_X=350
        VP_Y=1650
        VP_W=60
        VP_H=60
        
        echo "   Using estimated positions (no zones in template): President($PRES_X,$PRES_Y) VP($VP_X,$VP_Y)"
    fi
else
    # Fallback if jq not available
    PRES_X=350
    PRES_Y=950
    PRES_W=60
    PRES_H=60
    VP_X=350
    VP_Y=1650
    VP_W=60
    VP_H=60
    echo "   Using estimated positions (jq not available)"
fi

# Calculate end coordinates
PRES_X2=$((PRES_X + PRES_W))
PRES_Y2=$((PRES_Y + PRES_H))
VP_X2=$((VP_X + VP_W))
VP_Y2=$((VP_Y + VP_H))

# Draw the marks
if command -v magick &> /dev/null; then
    magick "$SCAN_IMAGE" \
        -fill black \
        -draw "rectangle $PRES_X,$PRES_Y $PRES_X2,$PRES_Y2" \
        -draw "rectangle $VP_X,$VP_Y $VP_X2,$VP_Y2" \
        "$SCAN_IMAGE"
else
    convert "$SCAN_IMAGE" \
        -fill black \
        -draw "rectangle $PRES_X,$PRES_Y $PRES_X2,$PRES_Y2" \
        -draw "rectangle $VP_X,$VP_Y $VP_X2,$VP_Y2" \
        "$SCAN_IMAGE"
fi

echo "✅ Marked: President - Alice Johnson (1st option), Vice President - Emma Davis (2nd option)"
echo ""

echo "🔍 Step 5: Appreciating the filled ballot..."
RESULT_FILE="${OUTPUT_DIR}/appreciation-results.json"
php artisan omr:appreciate "$SCAN_IMAGE" "$JSON_FILE" --output="$RESULT_FILE" --threshold=0.3

if [ ! -f "$RESULT_FILE" ]; then
    echo "❌ Error: Appreciation results were not generated"
    exit 1
fi

echo "✅ Appreciation complete: $RESULT_FILE"
echo ""

echo "📊 Step 6: Displaying results..."
echo "================================"
cat "$RESULT_FILE" | python3 -m json.tool 2>/dev/null || cat "$RESULT_FILE"
echo ""

echo "✅ Workflow test complete!"
echo ""
echo "📁 Generated files:"
echo "   - PDF: $PDF_FILE"
echo "   - Template: $JSON_FILE"
echo "   - Scanned: $SCAN_IMAGE"
echo "   - Results: $RESULT_FILE"
echo ""

# Optional: Display summary
if command -v jq &> /dev/null; then
    echo "📈 Quick Summary:"
    echo "   Document ID: $(jq -r '.document_id' "$RESULT_FILE")"
    echo "   Total Zones: $(jq -r '.summary.total_zones' "$RESULT_FILE")"
    echo "   Filled Count: $(jq -r '.summary.filled_count' "$RESULT_FILE")"
    echo "   Unfilled Count: $(jq -r '.summary.unfilled_count' "$RESULT_FILE")"
    echo "   Avg Confidence: $(jq -r '.summary.average_confidence' "$RESULT_FILE")"
    echo ""
    echo "🗳️  Detected Marks:"
    jq -r '.marks[] | select(.filled == true) | "   ✓ \(.id) (confidence: \(.confidence), fill: \(.fill_ratio))"' "$RESULT_FILE"
fi

echo ""
echo "🎉 Test completed successfully!"
