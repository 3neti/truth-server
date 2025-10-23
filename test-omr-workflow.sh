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
echo "  4. Python OpenCV fiducial detection for alignment"
echo "  5. Python OpenCV mark detection and appreciation"
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
    echo "      php artisan omr:appreciate-python ${OUTPUT_DIR}/filled-ballot.jpg $JSON_FILE --output=${OUTPUT_DIR}/results.json"
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
    echo "      php artisan omr:appreciate-python ${OUTPUT_DIR}/filled-ballot.jpg $JSON_FILE --output=${OUTPUT_DIR}/results.json"
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
echo "   Using Python OpenCV to draw realistic marks"
echo "   Marking: President - Alice Johnson (zone 0), Vice President - Emma Davis (zone 3)"

# Use Python script to draw realistic marks at exact zone positions
PYTHON_VENV="packages/omr-appreciation/omr-python/venv/bin/python"
MARK_SCRIPT="packages/omr-appreciation/omr-python/simulate_marks.py"

if [ ! -f "$PYTHON_VENV" ]; then
    echo "❌ Error: Python virtual environment not found. Run: cd packages/omr-appreciation/omr-python && python3 -m venv venv"
    exit 1
fi

if [ ! -f "$MARK_SCRIPT" ]; then
    echo "❌ Error: Mark simulation script not found: $MARK_SCRIPT"
    exit 1
fi

# Draw marks at zones 0 (President - Alice Johnson) and 3 (Vice President - Emma Davis)
$PYTHON_VENV $MARK_SCRIPT "$SCAN_IMAGE" "$JSON_FILE" --mark-zones "0,3" --fill 0.75

if [ $? -ne 0 ]; then
    echo "❌ Error: Failed to draw marks"
    exit 1
fi

echo "✅ Marked: President - Alice Johnson (zone 0), Vice President - Emma Davis (zone 3)"
echo ""

echo "🔍 Step 5: Appreciating the filled ballot (Python OpenCV)..."
RESULT_FILE="${OUTPUT_DIR}/appreciation-results.json"
# Use lower threshold (0.25) to be more sensitive to marks
php artisan omr:appreciate-python "$SCAN_IMAGE" "$JSON_FILE" --output="$RESULT_FILE" --threshold=0.25

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
    echo "   Template ID: $(jq -r '.template_id' "$RESULT_FILE")"
    TOTAL_ZONES=$(jq '.results | length' "$RESULT_FILE")
    FILLED_COUNT=$(jq '[.results[] | select(.filled == true)] | length' "$RESULT_FILE")
    UNFILLED_COUNT=$((TOTAL_ZONES - FILLED_COUNT))
    echo "   Total Zones: $TOTAL_ZONES"
    echo "   Filled Count: $FILLED_COUNT"
    echo "   Unfilled Count: $UNFILLED_COUNT"
    echo ""
    echo "🗳️  Detected Marks:"
    jq -r '.results[] | select(.filled == true) | "   ✓ \(.contest): \(.code) (fill ratio: \(.fill_ratio))"' "$RESULT_FILE"
fi

echo ""
echo "🎉 Test completed successfully!"
