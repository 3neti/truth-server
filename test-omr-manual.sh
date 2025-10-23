#!/bin/bash

# Simple OMR Manual Test Script
# Generates a PDF that you can print, fill, scan, and appreciate manually

set -e

echo "📝 OMR Manual Testing Workflow"
echo "==============================="
echo ""

TEST_ID="MANUAL-TEST-001"

echo "Step 1: Creating sample ballot data..."
mkdir -p storage/omr-test

cat > storage/omr-test/sample-ballot.json << 'EOF'
{
  "document_type": "Sample Test Ballot",
  "metadata": {
    "precinct": "Test Precinct",
    "election_date": "2025-10-22",
    "generated_at": "2025-10-22T23:00:00Z"
  },
  "contests_or_sections": [
    {
      "title": "President",
      "instruction": "Vote for ONE (1)",
      "candidates": [
        { "name": "Alice Johnson", "party": "Party A" },
        { "name": "Bob Smith", "party": "Party B" },
        { "name": "Carol White", "party": "Independent" }
      ]
    },
    {
      "title": "Senator",
      "instruction": "Vote for up to TWO (2)",
      "candidates": [
        { "name": "David Brown", "party": "Party A" },
        { "name": "Emma Davis", "party": "Party B" },
        { "name": "Frank Miller", "party": "Party C" }
      ]
    }
  ]
}
EOF

echo "✅ Sample data created"
echo ""

echo "Step 2: Generating PDF..."
php artisan omr:generate ballot-v1 "$TEST_ID" --data=storage/omr-test/sample-ballot.json

PDF_FILE="storage/omr-output/${TEST_ID}.pdf"
JSON_FILE="storage/omr-output/${TEST_ID}.json"

echo "✅ PDF generated: $PDF_FILE"
echo "✅ Template JSON: $JSON_FILE"
echo ""

echo "📋 Manual Testing Instructions:"
echo "================================"
echo ""
echo "1. 🖨️  PRINT the ballot:"
echo "   open $PDF_FILE"
echo ""
echo "2. ✏️  FILL the ballot:"
echo "   - Use a pen or pencil to fill in some boxes"
echo "   - Make clear, dark marks"
echo ""
echo "3. 📸 SCAN or PHOTOGRAPH the ballot:"
echo "   - Save as: storage/omr-test/filled-ballot.jpg"
echo "   - Use 300 DPI or higher if scanning"
echo "   - Make sure all 4 corner markers (fiducials) are visible"
echo ""
echo "4. 🔍 APPRECIATE the ballot:"
echo "   php artisan omr:appreciate \\"
echo "       storage/omr-test/filled-ballot.jpg \\"
echo "       $JSON_FILE \\"
echo "       --output=storage/omr-test/results.json"
echo ""
echo "5. 📊 VIEW the results:"
echo "   cat storage/omr-test/results.json | python3 -m json.tool"
echo ""
echo "   Or if you have jq installed:"
echo "   jq '.marks[] | select(.filled == true) | .id' storage/omr-test/results.json"
echo ""

echo "💡 Quick Test (if you have ImageMagick):"
echo "   # Convert PDF to image"
echo "   convert -density 300 $PDF_FILE storage/omr-test/test-scan.jpg"
echo ""
echo "   # Appreciate directly"
echo "   php artisan omr:appreciate storage/omr-test/test-scan.jpg $JSON_FILE"
echo ""
