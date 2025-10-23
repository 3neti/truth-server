# Handlebars → JSON → PDF Workflow Test

## Quick Start

Run the complete workflow demonstration:

```bash
php test-handlebars-workflow.php
```

## What It Does

This script demonstrates the **entire Handlebars → JSON → PDF workflow**:

1. **Load Handlebars Template** (`ballot.hbs`)
2. **Compile with Data** (candidates, title, etc.)
3. **Show Generated JSON Layout** (fiducials, barcode, bubbles)
4. **Generate PDF** using TCPDF
5. **Display Results** (file size, bubble count, etc.)

## Test Cases

### Test 1: Simple Yes/No Ballot
- **Purpose**: Basic referendum question
- **Candidates**: 2 (Yes, No)
- **Output**: Simple ballot with minimal bubbles

### Test 2: Presidential Election
- **Purpose**: Multi-candidate election
- **Candidates**: 4 (Alice, Bob, Carol, David)
- **Output**: Full election ballot with party labels

### Test 3: Large Survey
- **Purpose**: Stress test with many questions
- **Questions**: 10
- **Output**: Survey form with 10 bubbles

### Test 4: Validation
- **Purpose**: Test layout validation
- **Validates**: identifier, title, fiducials, barcode, bubbles

## Sample Output

```
🗳️  HANDLEBARS → JSON → PDF WORKFLOW TEST
============================================================

📋 Test 1: Simple Yes/No Ballot
------------------------------------------------------------
📝 Input Data:
{
    "identifier": "BALLOT-SIMPLE-075301",
    "title": "Referendum Question",
    "candidates": [
        {"x": 30, "y": 60, "label": "Yes"},
        {"x": 30, "y": 75, "label": "No"}
    ]
}

🔄 Compiling Handlebars template...
📄 Generated JSON Layout:
{
    "identifier": "BALLOT-SIMPLE-075301",
    "fiducials": [...]  # 4 corner markers
    "barcode": {...}     # PDF417
    "bubbles": [...]     # 2 OMR circles
    "text_elements": [...] # Labels
}

🖨️  Generating PDF...
✅ PDF Generated: storage/app/ballots/BALLOT-SIMPLE-075301.pdf
   Size: 17,064 bytes
   Fiducials: 4
   Bubbles: 2
   Text Elements: 3
```

## Features Demonstrated

### ✅ Handlebars Features
- Variable substitution: `{{identifier}}`, `{{title}}`
- Loop iteration: `{{#each candidates}}`
- Conditionals: `{{#if candidates}}`
- Helper functions: `{{add x 5}}`
- Array handling: `{{#unless @last}},{{/unless}}`

### ✅ Layout Generation
- Automatic fiducial markers (4 corners)
- PDF417 barcode with identifier
- OMR bubble positioning
- Text element placement
- Coordinate calculations

### ✅ PDF Generation
- TCPDF pixel-perfect rendering
- Fiducial markers at exact positions
- Barcode embedding
- Circle drawing for bubbles
- Text rendering with fonts

### ✅ Validation
- Required field checking
- Structure validation
- Error handling

## Workflow Chain

```
Input Data (PHP Array)
         ↓
Handlebars Template (ballot.hbs)
         ↓ [LayoutCompiler]
JSON Layout (Array)
         ↓ [validate]
Validated Layout
         ↓ [OMRTemplateGenerator]
TCPDF Rendering
         ↓
PDF File Output
```

## Generated PDFs

After running the script, you'll have 3 PDFs:

```bash
ls -lh storage/app/ballots/

BALLOT-SIMPLE-*.pdf    # Simple yes/no (2 bubbles)
ELECTION-2024-*.pdf    # Election (4 candidates)
SURVEY-LARGE-*.pdf     # Survey (10 questions)
```

## Viewing PDFs

### On macOS:
```bash
open storage/app/ballots/BALLOT-SIMPLE-*.pdf
```

### On Linux:
```bash
xdg-open storage/app/ballots/BALLOT-SIMPLE-*.pdf
```

### List All:
```bash
ls -lh storage/app/ballots/
```

## JSON Layout Example

The Handlebars template generates this JSON structure:

```json
{
  "identifier": "BALLOT-2024-001",
  "title": "Election Ballot",
  "fiducials": [
    {"x": 10, "y": 10, "width": 10, "height": 10},
    {"x": 190, "y": 10, "width": 10, "height": 10},
    {"x": 10, "y": 277, "width": 10, "height": 10},
    {"x": 190, "y": 277, "width": 10, "height": 10}
  ],
  "barcode": {
    "content": "BALLOT-2024-001",
    "type": "PDF417",
    "x": 10,
    "y": 260,
    "width": 80,
    "height": 20
  },
  "bubbles": [
    {"x": 30, "y": 60, "radius": 2.5, "label": "Yes"},
    {"x": 30, "y": 75, "radius": 2.5, "label": "No"}
  ],
  "text_elements": [
    {"x": 70, "y": 30, "content": "Election Ballot", "font": "helvetica", "style": "B", "size": 16},
    {"x": 35, "y": 60, "content": "Yes", "font": "helvetica", "style": "", "size": 10},
    {"x": 35, "y": 75, "content": "No", "font": "helvetica", "style": "", "size": 10}
  ]
}
```

## Creating Custom Tests

Edit the script to add your own test case:

```php
$customData = [
    'identifier' => 'MY-BALLOT-001',
    'title' => 'My Custom Ballot',
    'candidates' => [
        ['x' => 30, 'y' => 60, 'label' => 'Option 1'],
        ['x' => 30, 'y' => 75, 'label' => 'Option 2'],
        ['x' => 30, 'y' => 90, 'label' => 'Option 3'],
    ]
];

$layout = $compiler->compile('ballot', $customData);
$pdf = $generator->generateWithConfig($layout);
```

## Coordinate System

- **Unit**: Millimeters (mm)
- **Origin**: Top-left (0, 0)
- **Page**: A4 (210mm × 297mm)
- **DPI**: 300 for scanning

### Standard Positions
- Fiducials: (10,10), (190,10), (10,277), (190,277)
- Barcode: (10, 260) - bottom-left
- Bubbles: Start at y=60, spacing 15mm

## Troubleshooting

### Template Not Found
Make sure `resources/templates/ballot.hbs` exists:
```bash
ls -la resources/templates/ballot.hbs
```

### Permission Issues
Make script executable:
```bash
chmod +x test-handlebars-workflow.php
```

### Storage Directory
Script auto-creates `storage/app/ballots/` if needed.

## Integration with OpenCV

The generated PDFs are **OpenCV-ready**:

1. **Fiducial markers** at exact positions for perspective correction
2. **PDF417 barcode** for document identification
3. **OMR bubbles** at precise coordinates for mark detection
4. **300 DPI compatible** for scanning

### Python Integration

```python
import cv2
from appreciate import process_ballot

# Process the generated ballot
result = process_ballot('storage/app/ballots/BALLOT-SIMPLE-*.pdf')
print(result)
```

## Performance

Typical generation times:
- Simple ballot (2 bubbles): ~0.05s
- Election (4 candidates): ~0.06s
- Survey (10 questions): ~0.08s

## Related Scripts

- `test-tcpdf-generation.php` - Direct TCPDF generation (no Handlebars)
- `test-handlebars-workflow.php` - This script (Handlebars → PDF)

## Related Documentation

- [Handlebars Integration Guide](HANDLEBARS_INTEGRATION.md)
- [TCPDF Migration Guide](TCPDF_MIGRATION.md)
- [Test Script README](TEST_SCRIPT_README.md)

## Summary

This test script demonstrates:
✅ Handlebars template compilation  
✅ Dynamic layout generation  
✅ JSON structure validation  
✅ TCPDF PDF rendering  
✅ Complete workflow integration  

Run it to see the entire **Handlebars → JSON → PDF** pipeline in action! 🎉
