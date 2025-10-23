#!/bin/bash

# Manual Printed Ballot Test
# Tests the complete workflow: Generate PDF → Print → Mark → Scan → Detect

set -e

echo "🖨️  Manual Printed Ballot Test"
echo "=============================="
echo ""
echo "This test will guide you through printing, marking, and scanning a real ballot."
echo ""

# Generate test ballot
TEST_ID="PRINT-$(date +%Y%m%d-%H%M%S)"
OUTPUT_DIR="storage/omr-output"
SCAN_DIR="storage/omr-scans"

mkdir -p "$SCAN_DIR"

echo "📝 Step 1: Generating test ballot..."
cat > /tmp/print-test-ballot.json << 'EOF'
{
  "document_type": "Test Ballot - Print & Scan",
  "metadata": {
    "precinct": "Test Precinct",
    "election_date": "2025-10-23",
    "test_purpose": "Verify printed ballot detection"
  },
  "contests_or_sections": [
    {
      "title": "Question 1: Favorite Color",
      "instruction": "Vote for ONE (1)",
      "candidates": [
        {"name": "Red"},
        {"name": "Blue"},
        {"name": "Green"}
      ]
    },
    {
      "title": "Question 2: Favorite Animal",
      "instruction": "Vote for ONE (1)",
      "candidates": [
        {"name": "Cat"},
        {"name": "Dog"},
        {"name": "Bird"}
      ]
    }
  ]
}
EOF

echo "Running: php artisan omr:generate ballot-v1 \"$TEST_ID\" --data=/tmp/print-test-ballot.json"
if ! php artisan omr:generate ballot-v1 "$TEST_ID" --data=/tmp/print-test-ballot.json 2>&1; then
    echo "❌ Failed to generate ballot!"
    echo "Check the error above and Laravel logs at: storage/logs/laravel.log"
    exit 1
fi

PDF_FILE="${OUTPUT_DIR}/${TEST_ID}.pdf"
JSON_FILE="${OUTPUT_DIR}/${TEST_ID}.json"

# Verify files were created
if [ ! -f "$PDF_FILE" ]; then
    echo "❌ PDF file not created: $PDF_FILE"
    echo "Files in output directory:"
    ls -la "$OUTPUT_DIR"
    exit 1
fi

if [ ! -f "$JSON_FILE" ]; then
    echo "❌ JSON file not created: $JSON_FILE"
    exit 1
fi

echo ""
echo "✅ Ballot generated!"
echo "   PDF: $PDF_FILE"
echo "   Template: $JSON_FILE"
echo ""

# Show zone information
echo "📊 Ballot contains $(jq '.zones | length' "$JSON_FILE") mark zones:"
jq -r '.zones[] | "   - \(.id): \(.contest) - \(.candidate)"' "$JSON_FILE"
echo ""

# Print instructions
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📋 PRINTING INSTRUCTIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "1. Open this PDF file:"
echo "   $PDF_FILE"
echo ""
echo "2. Print Settings (VERY IMPORTANT!):"
echo "   ⚠️  Scale: 100% (DO NOT use 'Fit to Page' or 'Scale to Fit')"
echo "   ⚠️  Page Scaling: None"
echo "   ⚠️  Actual Size: Yes"
echo "   📄 Paper: A4 or Letter"
echo "   🖨️  Quality: Normal or Best"
echo ""
echo "3. On macOS:"
echo "   - Click 'Show Details' in print dialog"
echo "   - Ensure 'Scale' is set to 100%"
echo "   - Uncheck any 'Fit to page' options"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

read -p "Press ENTER when you have printed the ballot..."

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "✏️  MARKING INSTRUCTIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Using a DARK PEN or PENCIL, fill in these circles:"
echo ""
echo "   Question 1: Mark 'Blue' (2nd option)"
echo "   Question 2: Mark 'Dog' (2nd option)"
echo ""
echo "Tips for best results:"
echo "   ✓ Fill the circle completely (don't just check or X)"
echo "   ✓ Use dark pen (black or blue) or #2 pencil"
echo "   ✓ Stay inside the circle"
echo "   ✓ Make mark as dark as possible"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

read -p "Press ENTER when you have marked the ballot..."

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📸 SCANNING INSTRUCTIONS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "Scan or photograph the marked ballot:"
echo ""
echo "Option A - Scanner:"
echo "   • Resolution: 300 DPI (recommended) or 200-600 DPI"
echo "   • Color: Grayscale or Color"
echo "   • Format: JPG or PNG"
echo "   • File: ${SCAN_DIR}/${TEST_ID}-filled.jpg"
echo ""
echo "Option B - Smartphone Camera:"
echo "   • Well-lit area (avoid shadows)"
echo "   • Camera directly above ballot (not angled)"
echo "   • Entire page visible including corners"
echo "   • Clear and in-focus"
echo "   • Transfer to: ${SCAN_DIR}/${TEST_ID}-filled.jpg"
echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

SCAN_FILE="${SCAN_DIR}/${TEST_ID}-filled.jpg"

echo "Save your scan/photo to:"
echo "  $SCAN_FILE"
echo ""

# Wait for scan file
while [ ! -f "$SCAN_FILE" ]; do
    echo -n "Waiting for scan file... "
    read -p "(Press ENTER when file is ready, or Ctrl+C to cancel)"
    
    if [ ! -f "$SCAN_FILE" ]; then
        echo "⚠️  File not found: $SCAN_FILE"
        echo ""
        read -p "Enter the path to your scanned image: " USER_SCAN
        if [ -f "$USER_SCAN" ]; then
            cp "$USER_SCAN" "$SCAN_FILE"
            echo "✅ File copied to $SCAN_FILE"
            break
        fi
    fi
done

echo ""
echo "✅ Scan file received: $SCAN_FILE"
echo ""

# Verify scan file
echo "📊 Checking scan file..."
if command -v file &> /dev/null; then
    file "$SCAN_FILE"
fi
if command -v identify &> /dev/null; then
    echo "Image dimensions: $(identify -format '%wx%h' "$SCAN_FILE")"
fi
echo ""

# Run appreciation
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "🔍 Step 2: Running OMR Appreciation..."
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

RESULT_FILE="${SCAN_DIR}/${TEST_ID}-results.json"

php artisan omr:appreciate-python \
    "$SCAN_FILE" \
    "$JSON_FILE" \
    --output="$RESULT_FILE" \
    --threshold=0.25 \
    --debug

echo ""
echo "✅ Appreciation complete!"
echo ""

# Show results
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 RESULTS"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

if command -v jq &> /dev/null; then
    TOTAL=$(jq '.results | length' "$RESULT_FILE")
    FILLED=$(jq '[.results[] | select(.filled == true)] | length' "$RESULT_FILE")
    
    echo "Total zones: $TOTAL"
    echo "Detected as filled: $FILLED"
    echo ""
    
    echo "Expected marks:"
    echo "  ✓ Question 1: Blue (zone 1)"
    echo "  ✓ Question 2: Dog (zone 4)"
    echo ""
    
    echo "Detected marks:"
    jq -r '.results[] | select(.filled == true) | "  ✓ \(.contest): \(.candidate) (fill: \(.fill_ratio), conf: \(.confidence))"' "$RESULT_FILE"
    
    echo ""
    echo "All zones with details:"
    jq -r '.results[] | "  [\(if .filled then "✓" else " " end)] \(.id) - fill:\(.fill_ratio) conf:\(.confidence) \(if .warnings then "⚠️" else "" end)"' "$RESULT_FILE"
    
    echo ""
    
    # Check accuracy
    BLUE_FILLED=$(jq -r '.results[] | select(.candidate == "Blue") | .filled' "$RESULT_FILE")
    DOG_FILLED=$(jq -r '.results[] | select(.candidate == "Dog") | .filled' "$RESULT_FILE")
    
    CORRECT=0
    [ "$BLUE_FILLED" == "true" ] && ((CORRECT++))
    [ "$DOG_FILLED" == "true" ] && ((CORRECT++))
    
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    if [ $CORRECT -eq 2 ]; then
        echo "🎉 SUCCESS! Both marks detected correctly!"
        echo "   Accuracy: 100%"
    elif [ $CORRECT -eq 1 ]; then
        echo "⚠️  PARTIAL SUCCESS! 1 of 2 marks detected."
        echo "   Accuracy: 50%"
    else
        echo "❌ FAILED! No marks detected correctly."
        echo "   Accuracy: 0%"
    fi
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
else
    cat "$RESULT_FILE"
fi

echo ""
echo "📁 Generated Files:"
echo "   PDF: $PDF_FILE"
echo "   Template: $JSON_FILE"
echo "   Scan: $SCAN_FILE"
echo "   Results: $RESULT_FILE"
if [ -f "${SCAN_DIR}/${TEST_ID}-filled-debug.jpg" ]; then
    echo "   Debug: ${SCAN_DIR}/${TEST_ID}-filled-debug.jpg"
fi
echo ""

echo "💡 Tips if marks weren't detected:"
echo "   1. Check debug image to see zone alignment"
echo "   2. Try darker pen or pencil"
echo "   3. Fill circles more completely"
echo "   4. Ensure scan is at least 200 DPI"
echo "   5. Check that print was at 100% scale"
echo "   6. Try lower --threshold (e.g., 0.20)"
echo ""

echo "✅ Test complete!"
