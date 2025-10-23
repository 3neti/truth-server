#!/bin/bash

# OMR Confidence Test - Demonstrate Different Confidence Levels
# Shows high, medium, and low confidence marks

set -e

echo "🎯 OMR Confidence Level Test"
echo "=============================="
echo ""
echo "This test demonstrates different mark qualities:"
echo "  • High confidence: Clean, dark, well-filled marks"
echo "  • Medium confidence: Moderate fill marks"
echo "  • Low confidence: Light or partial marks"
echo ""

TEST_ID="CONFIDENCE-$(date +%s)"
OUTPUT_DIR="storage/omr-test-confidence"
JSON_FILE="${OUTPUT_DIR}/template.json"
BLANK_BALLOT="${OUTPUT_DIR}/ballot.jpg"
MARKED_BALLOT="${OUTPUT_DIR}/ballot-marked.jpg"
RESULT_FILE="${OUTPUT_DIR}/results.json"

mkdir -p "$OUTPUT_DIR"

echo "📝 Step 1: Creating template with 6 zones..."
cat > "$JSON_FILE" << 'EOF'
{
  "template_id": "confidence-test-v1",
  "document_id": "CONFIDENCE-TEST-001",
  "size": "A4",
  "dpi": 300,
  "fiducials": [
    {"id": "top_left", "x": 100, "y": 100, "width": 50, "height": 50},
    {"id": "top_right", "x": 2380, "y": 100, "width": 50, "height": 50},
    {"id": "bottom_left", "x": 100, "y": 3408, "width": 50, "height": 50},
    {"id": "bottom_right", "x": 2380, "y": 3408, "width": 50, "height": 50}
  ],
  "zones": [
    {"id": "HIGH_CONF_1", "x": 300, "y": 600, "width": 100, "height": 100, "contest": "High Confidence", "candidate": "Dark Mark 1"},
    {"id": "HIGH_CONF_2", "x": 300, "y": 750, "width": 100, "height": 100, "contest": "High Confidence", "candidate": "Dark Mark 2"},
    {"id": "MEDIUM_CONF_1", "x": 300, "y": 1000, "width": 100, "height": 100, "contest": "Medium Confidence", "candidate": "Moderate Mark 1"},
    {"id": "MEDIUM_CONF_2", "x": 300, "y": 1150, "width": 100, "height": 100, "contest": "Medium Confidence", "candidate": "Moderate Mark 2"},
    {"id": "LOW_CONF_1", "x": 300, "y": 1400, "width": 100, "height": 100, "contest": "Low Confidence", "candidate": "Light Mark 1"},
    {"id": "UNFILLED", "x": 300, "y": 1550, "width": 100, "height": 100, "contest": "Control", "candidate": "Unfilled Mark"}
  ]
}
EOF

echo "✅ Template created with 6 test zones"
echo ""

echo "🎨 Step 2: Creating blank ballot..."
packages/omr-appreciation/omr-python/venv/bin/python3 << 'PYTHON_EOF'
import cv2
import numpy as np

# A4 at 300 DPI
width, height = 2480, 3508
image = np.ones((height, width, 3), dtype=np.uint8) * 255

# Draw fiducial markers
fiducials = [(100, 100), (2380, 100), (100, 3408), (2380, 3408)]
for x, y in fiducials:
    cv2.rectangle(image, (x, y), (x+50, y+50), (0, 0, 0), -1)

# Draw zone outlines with labels
zones = [
    (300, 600, "HIGH"),
    (300, 750, "HIGH"),
    (300, 1000, "MED"),
    (300, 1150, "MED"),
    (300, 1400, "LOW"),
    (300, 1550, "NONE")
]
for x, y, label in zones:
    cv2.circle(image, (x+50, y+50), 45, (200, 200, 200), 2)
    cv2.putText(image, label, (x+120, y+60), cv2.FONT_HERSHEY_SIMPLEX, 0.5, (100, 100, 100), 1)

cv2.imwrite('storage/omr-test-confidence/ballot.jpg', image)
print("✅ Blank ballot created")
PYTHON_EOF

echo ""

echo "✏️  Step 3: Simulating marks with varying intensities..."
cp "$BLANK_BALLOT" "$MARKED_BALLOT"

# High confidence marks (95% fill, very dark)
echo "  Drawing HIGH confidence marks (95% fill)..."
packages/omr-appreciation/omr-python/venv/bin/python \
    packages/omr-appreciation/omr-python/simulate_marks.py \
    "$MARKED_BALLOT" \
    "$JSON_FILE" \
    --mark-zones "0,1" \
    --fill 0.95 \
    > /dev/null 2>&1

# Medium confidence marks (75% fill)
echo "  Drawing MEDIUM confidence marks (75% fill)..."
packages/omr-appreciation/omr-python/venv/bin/python \
    packages/omr-appreciation/omr-python/simulate_marks.py \
    "$MARKED_BALLOT" \
    "$JSON_FILE" \
    --mark-zones "2,3" \
    --fill 0.75 \
    > /dev/null 2>&1

# Low confidence marks (50% fill, lighter)
echo "  Drawing LOW confidence marks (50% fill)..."
packages/omr-appreciation/omr-python/venv/bin/python \
    packages/omr-appreciation/omr-python/simulate_marks.py \
    "$MARKED_BALLOT" \
    "$JSON_FILE" \
    --mark-zones "4" \
    --fill 0.50 \
    > /dev/null 2>&1

echo "  📄 Unmarked ballot: $BLANK_BALLOT"
echo "  ✏️  Marked ballot: $MARKED_BALLOT"
echo ""

echo "🔍 Step 4: Appreciating the ballot..."
php artisan omr:appreciate-python \
    "$MARKED_BALLOT" \
    "$JSON_FILE" \
    --output="$RESULT_FILE" \
    --threshold=0.25 \
    > /dev/null 2>&1

echo "✅ Appreciation complete"
echo ""

echo "📊 Results by Confidence Level:"
echo "================================"

if command -v jq &> /dev/null; then
    echo ""
    echo "🟢 HIGH CONFIDENCE MARKS:"
    jq -r '.results[0:2][] | "  ✓ \(.id)\n    Fill: \(.fill_ratio) | Confidence: \(.confidence) | Warnings: \(.warnings // "none")"' "$RESULT_FILE"
    
    echo ""
    echo "🟡 MEDIUM CONFIDENCE MARKS:"
    jq -r '.results[2:4][] | "  ✓ \(.id)\n    Fill: \(.fill_ratio) | Confidence: \(.confidence) | Warnings: \(.warnings // "none")"' "$RESULT_FILE"
    
    echo ""
    echo "🟠 LOW CONFIDENCE MARKS:"
    jq -r '.results[4:5][] | "  ✓ \(.id)\n    Fill: \(.fill_ratio) | Confidence: \(.confidence) | Warnings: \(.warnings // "none")"' "$RESULT_FILE"
    
    echo ""
    echo "⚪ UNFILLED (Control):"
    jq -r '.results[5:6][] | "  ○ \(.id)\n    Fill: \(.fill_ratio) | Confidence: \(.confidence) | Warnings: \(.warnings // "none")"' "$RESULT_FILE"
    
    echo ""
    echo "📈 Overall Statistics:"
    echo "  Total zones: $(jq '.results | length' "$RESULT_FILE")"
    echo "  Filled: $(jq '[.results[] | select(.filled == true)] | length' "$RESULT_FILE")"
    echo "  Average confidence: $(jq '[.results[].confidence] | add / length | . * 100 | round / 100' "$RESULT_FILE")"
    echo "  With warnings: $(jq '[.results[] | select(.warnings != null)] | length' "$RESULT_FILE")"
    
    echo ""
    echo "🎯 Confidence Distribution:"
    HIGH_CONF=$(jq '[.results[] | select(.confidence >= 0.7)] | length' "$RESULT_FILE")
    MED_CONF=$(jq '[.results[] | select(.confidence >= 0.5 and .confidence < 0.7)] | length' "$RESULT_FILE")
    LOW_CONF=$(jq '[.results[] | select(.confidence < 0.5)] | length' "$RESULT_FILE")
    echo "  High (≥0.70): $HIGH_CONF zones"
    echo "  Medium (0.50-0.69): $MED_CONF zones"
    echo "  Low (<0.50): $LOW_CONF zones"
fi

echo ""
echo "📁 Generated files:"
echo "  - Blank ballot: $BLANK_BALLOT"
echo "  - Marked ballot: $MARKED_BALLOT"
echo "  - Template: $JSON_FILE"
echo "  - Results: $RESULT_FILE"
echo ""

echo "💡 Tip: Compare ballot.jpg and ballot-marked.jpg to see visual mark quality"
echo "    vs. computed confidence levels"
echo ""
echo "✅ Test complete!"
