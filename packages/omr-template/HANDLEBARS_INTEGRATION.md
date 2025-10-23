# Handlebars + TCPDF Integration

## Overview

This package integrates **Handlebars** templating with **TCPDF** PDF generation to create flexible, data-driven ballot and survey layouts. Handlebars templates define the structure as JSON, which is then rendered to pixel-perfect PDFs using TCPDF.

## Architecture

```
Handlebars Template (.hbs)
          ↓
    LayoutCompiler (LightnCandy)
          ↓
    JSON Layout (Array)
          ↓
OMRTemplateGenerator (TCPDF)
          ↓
    PDF Output
```

## Components

### 1. LayoutCompiler

**Location**: `src/Services/LayoutCompiler.php`

Compiles Handlebars templates to JSON layouts using LightnCandy (Handlebars-compatible engine for PHP).

**Key Methods**:
- `compile(string $template, array $data): array` - Compile template file to layout array
- `compileString(string $template, array $data): array` - Compile inline template string
- `compileToJson(string $template, array $data): string` - Compile and return JSON string
- `validate(array $layout, array $requiredFields): bool` - Validate layout structure
- `setBasePath(string $path): self` - Set custom template directory

### 2. Handlebars Template

**Location**: `resources/templates/ballot.hbs`

Defines ballot layout structure as JSON with Handlebars placeholders.

### 3. OMRTemplateGenerator

**Location**: `src/Services/OMRTemplateGenerator.php`

Takes compiled JSON layout and generates PDF with TCPDF.

## Usage

### Basic Example

```php
use LBHurtado\OMRTemplate\Services\LayoutCompiler;
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

// 1. Prepare data
$data = [
    'identifier' => 'BALLOT-2024-001',
    'title' => 'Presidential Election',
    'candidates' => [
        ['x' => 30, 'y' => 60, 'label' => 'John Doe'],
        ['x' => 30, 'y' => 75, 'label' => 'Jane Smith'],
        ['x' => 30, 'y' => 90, 'label' => 'Bob Johnson'],
    ]
];

// 2. Compile Handlebars template to layout
$compiler = new LayoutCompiler();
$layout = $compiler->compile('ballot', $data);

// 3. Generate PDF from layout
$generator = new OMRTemplateGenerator();
$pdfPath = $generator->generateWithConfig($layout);

echo "PDF generated: {$pdfPath}";
```

### Laravel Integration

```php
use LBHurtado\OMRTemplate\Services\LayoutCompiler;
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

Route::get('/ballot/{id}', function ($id) {
    $compiler = app(LayoutCompiler::class);
    $generator = app(OMRTemplateGenerator::class);
    
    $layout = $compiler->compile('ballot', [
        'identifier' => $id,
        'title' => 'Sample Ballot',
        'candidates' => [
            ['x' => 30, 'y' => 60, 'label' => 'Option A'],
            ['x' => 30, 'y' => 70, 'label' => 'Option B'],
        ]
    ]);
    
    $path = $generator->generateWithConfig($layout);
    
    return response()->download($path);
});
```

## Handlebars Template Structure

### Sample Template (`resources/templates/ballot.hbs`)

```handlebars
{
  "identifier": "{{identifier}}",
  "title": "{{title}}",
  "fiducials": [
    { "x": 10, "y": 10, "width": 10, "height": 10 },
    { "x": 190, "y": 10, "width": 10, "height": 10 },
    { "x": 10, "y": 277, "width": 10, "height": 10 },
    { "x": 190, "y": 277, "width": 10, "height": 10 }
  ],
  "barcode": {
    "content": "{{identifier}}",
    "type": "PDF417",
    "x": 10,
    "y": 260,
    "width": 80,
    "height": 20
  },
  "bubbles": [
    {{#each candidates}}
    {
      "x": {{x}},
      "y": {{y}},
      "radius": 2.5,
      "label": "{{label}}"
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ],
  "text_elements": [
    {
      "x": 70,
      "y": 30,
      "content": "{{title}}",
      "font": "helvetica",
      "style": "B",
      "size": 16
    }{{#if candidates}},{{/if}}
    {{#each candidates}}
    {
      "x": {{add x 5}},
      "y": {{y}},
      "content": "{{label}}",
      "font": "helvetica",
      "style": "",
      "size": 10
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

### Template Features

1. **Variable Substitution**: `{{identifier}}`, `{{title}}`
2. **Loops**: `{{#each candidates}}...{{/each}}`
3. **Conditionals**: `{{#if candidates}}...{{/if}}`
4. **Helpers**: `{{add x 5}}` (math operations)
5. **Array Iteration**: `{{#unless @last}},{{/unless}}` (comma handling)

## Helpers

Built-in Handlebars helpers:

| Helper | Description | Example |
|--------|-------------|---------|
| `add` | Addition | `{{add 10 5}}` → 15 |
| `subtract` | Subtraction | `{{subtract 10 5}}` → 5 |
| `multiply` | Multiplication | `{{multiply 10 5}}` → 50 |
| `divide` | Division | `{{divide 10 5}}` → 2 |

### Using Helpers in Templates

```handlebars
{
  "bubbles": [
    {{#each candidates}}
    {
      "x": {{add baseX 10}},
      "y": {{multiply @index 15}},
      "label": "{{label}}"
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

## JSON Layout Structure

The compiled layout follows this structure:

```json
{
  "identifier": "BALLOT-001",
  "title": "Election Ballot",
  "fiducials": [
    {"x": 10, "y": 10, "width": 10, "height": 10},
    // ... 3 more corners
  ],
  "barcode": {
    "content": "BALLOT-001",
    "type": "PDF417",
    "x": 10,
    "y": 260,
    "width": 80,
    "height": 20
  },
  "bubbles": [
    {"x": 30, "y": 60, "radius": 2.5, "label": "Option A"},
    // ... more bubbles
  ],
  "text_elements": [
    {
      "x": 70,
      "y": 30,
      "content": "Election Ballot",
      "font": "helvetica",
      "style": "B",
      "size": 16
    },
    // ... more text
  ]
}
```

## Validation

Validate layouts before PDF generation:

```php
$layout = $compiler->compile('ballot', $data);

// Validate required fields
$compiler->validate($layout, [
    'identifier',
    'title',
    'fiducials',
    'barcode',
    'bubbles'
]);

// Now safe to generate PDF
$pdfPath = $generator->generateWithConfig($layout);
```

## Testing

Run the Handlebars integration tests:

```bash
composer test --filter=HandlebarsLayoutTest
```

### Test Coverage

- ✅ Template compilation
- ✅ Fiducial marker generation
- ✅ Barcode configuration
- ✅ PDF generation from layout
- ✅ Inline template strings
- ✅ Field validation
- ✅ Helper functions
- ✅ Full integration (Handlebars → PDF)

## Advanced: Custom Templates

### Creating Custom Templates

1. Create a new `.hbs` file in `resources/templates/`:

```bash
touch resources/templates/survey.hbs
```

2. Define the template structure:

```handlebars
{
  "identifier": "{{survey_id}}",
  "title": "{{survey_title}}",
  "fiducials": [
    // ... standard fiducials
  ],
  "questions": [
    {{#each questions}}
    {
      "text": "{{text}}",
      "bubbles": [
        {{#each options}}
        {
          "x": {{add ../baseX @index}},
          "y": {{add ../baseY @../index}},
          "label": "{{this}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

3. Use the custom template:

```php
$layout = $compiler->compile('survey', [
    'survey_id' => 'SURVEY-001',
    'survey_title' => 'Customer Satisfaction',
    'questions' => [
        [
            'text' => 'How satisfied are you?',
            'baseX' => 30,
            'baseY' => 60,
            'options' => ['Very Satisfied', 'Satisfied', 'Neutral', 'Dissatisfied']
        ]
    ]
]);
```

## Benefits

### 1. **Flexibility**
- Define layouts as data, not code
- Easy to create variations
- Template reuse across projects

### 2. **Separation of Concerns**
- Design team works on templates
- Dev team works on data structure
- Clear interface between both

### 3. **Precision**
- TCPDF ensures pixel-perfect rendering
- Consistent across devices
- Compatible with OpenCV

### 4. **Maintainability**
- Templates are easier to read than code
- Changes don't require code deployment
- Version control for layouts

## Troubleshooting

### Template Not Found

```php
// Set custom base path
$compiler->setBasePath('/path/to/templates');
$layout = $compiler->compile('ballot', $data);
```

### Invalid JSON Output

Ensure your template produces valid JSON:
- Use commas correctly with `{{#unless @last}},{{/unless}}`
- Close all brackets and braces
- Quote string values

### Helper Not Working

Check that helpers are defined in `LayoutCompiler::getHelpers()`:

```php
protected function getHelpers(): array
{
    return [
        'myHelper' => function ($arg) {
            return $arg * 2;
        },
    ];
}
```

## Related Documentation

- [TCPDF Migration Guide](TCPDF_MIGRATION.md)
- [Migration Summary](MIGRATION_SUMMARY.md)
- [Test Script README](TEST_SCRIPT_README.md)
- [OMR Template Rendering Plan](../../resources/docs/OMR_TEMPLATE_RENDERING_PLAN.md)

## Example: Complete Workflow

```php
<?php

use LBHurtado\OMRTemplate\Services\LayoutCompiler;
use LBHurtado\OMRTemplate\Services\OMRTemplateGenerator;

// 1. Prepare election data
$electionData = [
    'identifier' => 'ELECTION-2024-PRECINCT-101',
    'title' => 'General Election 2024',
    'candidates' => [
        ['x' => 35, 'y' => 70, 'label' => 'Alice Johnson - Party A'],
        ['x' => 35, 'y' => 85, 'label' => 'Bob Smith - Party B'],
        ['x' => 35, 'y' => 100, 'label' => 'Carol White - Party C'],
        ['x' => 35, 'y' => 115, 'label' => 'David Brown - Independent'],
    ]
];

// 2. Compile template
$compiler = new LayoutCompiler();
$layout = $compiler->compile('ballot', $electionData);

// 3. Validate layout
try {
    $compiler->validate($layout, ['identifier', 'fiducials', 'barcode', 'bubbles']);
    echo "✅ Layout valid\n";
} catch (\RuntimeException $e) {
    echo "❌ Layout invalid: {$e->getMessage()}\n";
    exit(1);
}

// 4. Generate PDF
$generator = new OMRTemplateGenerator();
$pdfPath = $generator->generateWithConfig($layout);

echo "📄 PDF generated: {$pdfPath}\n";
echo "📦 File size: " . filesize($pdfPath) . " bytes\n";

// 5. Output layout as JSON for reference
file_put_contents(
    str_replace('.pdf', '.json', $pdfPath),
    $compiler->compileToJson('ballot', $electionData)
);

echo "✅ Complete!\n";
```

## Summary

| Feature | Implementation |
|---------|----------------|
| **Templating** | Handlebars (via LightnCandy) |
| **PDF Generation** | TCPDF |
| **Layout Format** | JSON |
| **Template Location** | `resources/templates/*.hbs` |
| **Compiler** | `LayoutCompiler` service |
| **Generator** | `OMRTemplateGenerator` service |
| **Helpers** | Math operations (add, subtract, multiply, divide) |
| **Testing** | Full integration tests included |

This integration provides a powerful, flexible system for generating OMR ballots and surveys with precise, OpenCV-compatible output.
