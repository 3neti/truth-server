#!/bin/bash

# Simple OMR Test - Generate, Mark, and Appreciate
# This test uses the scanned image directly without perspective transform issues

set -e

echo "🧪 Simple OMR Workflow Test"
echo "============================"
echo ""

TEST_ID="SIMPLE-$(date +%s)"
OUTPUT_DIR="storage/omr-test"
JSON_FILE="${OUTPUT_DIR}/template.json"
BLANK_BALLOT="${OUTPUT_DIR}/ballot.jpg"
MARKED_BALLOT="${OUTPUT_DIR}/ballot-marked.jpg"
RESULT_FILE="${OUTPUT_DIR}/results.json"

mkdir -p "$OUTPUT_DIR"

echo "📝 Step 1: Creating simple template JSON..."
cat > "$JSON_FILE" << 'EOF'
{
  "template_id": "simple-test-v1",
  "document_id": "SIMPLE-TEST-001",
  "size": "A4",
  "dpi": 300,
  "fiducials": [
    {"id": "top_left", "x": 100, "y": 100, "width": 50, "height": 50},
    {"id": "top_right", "x": 2380, "y": 100, "width": 50, "height": 50},
    {"id": "bottom_left", "x": 100, "y": 3408, "width": 50, "height": 50},
    {"id": "bottom_right", "x": 2380, "y": 3408, "width": 50, "height": 50}
  ],
  "zones": [
    {"id": "Q1_A", "x": 300, "y": 800, "width": 100, "height": 100, "contest": "Question 1", "candidate": "Option A"},
    {"id": "Q1_B", "x": 300, "y": 950, "width": 100, "height": 100, "contest": "Question 1", "candidate": "Option B"},
    {"id": "Q2_A", "x": 300, "y": 1200, "width": 100, "height": 100, "contest": "Question 2", "candidate": "Option A"},
    {"id": "Q2_B", "x": 300, "y": 1350, "width": 100, "height": 100, "contest": "Question 2", "candidate": "Option B"}
  ]
}
EOF

echo "✅ Template created"
echo ""

echo "🎨 Step 2: Creating blank ballot image..."
# Create a white A4 image at 300 DPI (2480x3508 pixels)
packages/omr-appreciation/omr-python/venv/bin/python3 << 'PYTHON_EOF'
import cv2
import numpy as np

# A4 at 300 DPI
width, height = 2480, 3508
image = np.ones((height, width, 3), dtype=np.uint8) * 255

# Draw fiducial markers (black squares)
fiducials = [(100, 100), (2380, 100), (100, 3408), (2380, 3408)]
for x, y in fiducials:
    cv2.rectangle(image, (x, y), (x+50, y+50), (0, 0, 0), -1)

# Draw zone outlines (gray circles)
zones = [(300, 800), (300, 950), (300, 1200), (300, 1350)]
for x, y in zones:
    cv2.circle(image, (x+50, y+50), 45, (200, 200, 200), 2)

cv2.imwrite('storage/omr-test/ballot.jpg', image)
print("✅ Blank ballot saved as: ballot.jpg", flush=True)
PYTHON_EOF

echo ""

echo "✏️  Step 3: Simulating filled marks (Q1_A and Q2_B)..."
# Copy blank ballot before marking
cp "$BLANK_BALLOT" "$MARKED_BALLOT"
packages/omr-appreciation/omr-python/venv/bin/python \
    packages/omr-appreciation/omr-python/simulate_marks.py \
    "$MARKED_BALLOT" \
    "$JSON_FILE" \
    --mark-zones "0,3" \
    --fill 0.95

echo "  📄 Unmarked ballot: $BLANK_BALLOT"
echo "  ✏️  Marked ballot: $MARKED_BALLOT"

echo ""

echo "🔍 Step 4: Appreciating the marked ballot..."
php artisan omr:appreciate-python \
    "$MARKED_BALLOT" \
    "$JSON_FILE" \
    --output="$RESULT_FILE" \
    --threshold=0.25

echo ""

echo "📊 Results:"
echo "==========="
cat "$RESULT_FILE" | python3 -m json.tool 2>/dev/null || cat "$RESULT_FILE"

echo ""
echo "📈 Summary:"
if command -v jq &> /dev/null; then
    FILLED=$(jq '[.results[] | select(.filled == true)] | length' "$RESULT_FILE")
    TOTAL=$(jq '.results | length' "$RESULT_FILE")
    echo "  Filled: $FILLED / $TOTAL"
    echo ""
    echo "✅ Detected marks:"
    jq -r '.results[] | select(.filled == true) | "  ✓ \(.id) - \(.candidate)\n    Fill: \(.fill_ratio) | Confidence: \(.confidence) | Warnings: \(.warnings // "none")"' "$RESULT_FILE"
    echo ""
    
    # Show quality summary
    LOW_CONF=$(jq '[.results[] | select(.confidence < 0.5)] | length' "$RESULT_FILE")
    HAS_WARNINGS=$(jq '[.results[] | select(.warnings != null)] | length' "$RESULT_FILE")
    if [ "$LOW_CONF" -gt 0 ] || [ "$HAS_WARNINGS" -gt 0 ]; then
        echo "⚠️  Quality Issues:"
        [ "$LOW_CONF" -gt 0 ] && echo "  - $LOW_CONF zone(s) with low confidence"
        [ "$HAS_WARNINGS" -gt 0 ] && echo "  - $HAS_WARNINGS zone(s) with warnings"
        echo ""
    fi
fi

echo ""
echo "📁 Generated files:"
echo "  - Blank ballot: $BLANK_BALLOT"
echo "  - Marked ballot: $MARKED_BALLOT"
echo "  - Template: $JSON_FILE"
echo "  - Results: $RESULT_FILE"
echo ""
if [ "$FILLED" == "2" ]; then
    echo "🎉 SUCCESS! Both marks detected correctly!"
else
    echo "⚠️  Expected 2 filled marks, got $FILLED"
fi
