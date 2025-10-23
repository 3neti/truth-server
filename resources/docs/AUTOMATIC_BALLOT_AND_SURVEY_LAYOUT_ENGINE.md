# Automatic Ballot & Survey Layout Engine for `lbhurtado/omr-templates`

> Make OMR-ready PDFs from **Handlebars + JSON + TCPDF** with minimal manual formatting.  
> This plan introduces a **declarative layout schema**, a **smart PHP renderer**, and **calibration/fiducials** for robust appreciation via OpenCV.

---

## 1) Goals & Non-Goals

**Goals**
- Declarative, *data-driven* PDF generation for ballots, surveys, questionnaires.
- Automatic layout: columns, line-breaking, padding, page breaks, and overflow continuations.
- OMR-ready artifacts: fixed bubble grids, fiducials, quiet zones, UDI barcode region.
- Maintainable theming via config presets (fonts, spacing, column rules).
- Pluggable section renderers (multiple-choice, rating scales, free text, checklists).
- Deterministic coordinates export (for Python appreciation).

**Non-Goals (v1)**
- No WYSIWYG editor.
- No AI/ML layout—heuristics only (we can add later).
- No HTML/CSS full fidelity—use TCPDF primitives for predictable OMR alignment.

---

## 2) High-Level Architecture

```text
JSON (Structure + Hints)
        │
        ▼
Handlebars (Semantic Composition, minimal styling)
        │
        ▼
SmartLayoutRenderer (PHP + TCPDF)
  ├─ Section renderers (MCQ, matrix, rating)
  ├─ Layout engine (columns, wrapping, page breaks)
  ├─ OMR layer (bubbles, timing marks, fiducials)
  └─ Coordinates registry (for appreciation map)
        │
        ▼
PDF + coords.json
```

Three layers
	1.	Structure Layer (JSON): defines what to render (sections, choices, layouts).
	2.	Template Layer (Handlebars): groups content semantically (no pixel nudging).
	3.	Renderer Layer (PHP/TCPDF): computes final placement, handles overflows, draws OMR marks.

## 3) High-Level Architecture

```text
packages/lbhurtado/omr-templates/
├─ config/omr-templates.php
├─ src/
│  ├─ Contracts/
│  │  ├─ SectionRenderer.php
│  │  └─ CoordinatesSink.php
│  ├─ DTO/
│  │  ├─ DocumentSpec.php
│  │  ├─ SectionSpec.php
│  │  └─ ChoiceSpec.php
│  ├─ Engine/
│  │  ├─ SmartLayoutRenderer.php
│  │  ├─ LayoutContext.php
│  │  ├─ OverflowPaginator.php
│  │  ├─ CoordinatesRegistry.php
│  │  └─ OMRDrawer.php
│  ├─ Renderers/
│  │  ├─ MultipleChoiceRenderer.php
│  │  ├─ RatingScaleRenderer.php
│  │  ├─ MatrixRenderer.php
│  │  └─ FreeTextRenderer.php
│  ├─ Services/
│  │  ├─ HandlebarsCompiler.php
│  │  └─ SpecValidator.php
│  ├─ Support/
│  │  ├─ Measure.php
│  │  └─ TextWrap.php
│  └─ Facades/OMRTemplates.php
├─ resources/
│  ├─ templates/
│  │  ├─ ballot.hbs
│  │  ├─ survey.hbs
│  │  └─ partials/
│  │     ├─ section.hbs
│  │     └─ footer.hbs
│  └─ samples/
│     ├─ sample-ballot.json
│     └─ sample-survey.json
├─ tests/
│  ├─ Unit/
│  ├─ Feature/
│  └─ GoldenFiles/
└─ artisan (console commands registered in provider)
```

## 4) Configuration (config/omr-templates.php)
```php
return [
    'page' => [
        'size' => 'A4',
        'orientation' => 'P',
        'margins' => ['l' => 18, 't' => 18, 'r' => 18, 'b' => 18],
        'dpi' => 300,
    ],

    'fonts' => [
        'header' => ['family' => 'helvetica', 'style' => 'B', 'size' => 12],
        'body'   => ['family' => 'helvetica', 'style' => '', 'size' => 10],
        'small'  => ['family' => 'helvetica', 'style' => '', 'size' => 8],
    ],

    'layouts' => [
        '1-col' => ['cols' => 1, 'gutter' => 6, 'row_gap' => 3, 'cell_pad' => 2],
        '2-col' => ['cols' => 2, 'gutter' => 10, 'row_gap' => 3, 'cell_pad' => 2],
        '3-col' => ['cols' => 3, 'gutter' => 10, 'row_gap' => 2, 'cell_pad' => 2],
    ],

    'omr' => [
        'bubble' => [
            'diameter_mm' => 4.0,
            'stroke' => 0.2,
            'fill' => false,
            'label_gap_mm' => 2.0,
        ],
        'fiducials' => [
            'enable' => true,
            'size_mm' => 5.0,
            'positions' => ['tl','tr','bl','br'],
        ],
        'timing_marks' => [
            'enable' => true,
            'edges' => ['left','bottom'],
            'pitch_mm' => 5.0,
            'size_mm'  => 1.5,
        ],
        'quiet_zone_mm' => 6.0,
        'barcode' => [
            'enable' => true,
            'type' => 'PDF417',
            'height_mm' => 10.0,
            'region' => 'footer',
        ],
    ],

    'coords' => [
        'emit_json' => true,
        'path' => storage_path('app/omr/coords'),
    ],
];
```

## 5) JSON Structure (Document Spec)
```json
{
  "document": {
    "title": "Sample Ballot",
    "locale": "en-PH",
    "unique_id": "BAL-2025-0000123",
    "layout": "2-col"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "PRESIDENT",
      "title": "President of the Philippines",
      "maxSelections": 1,
      "layout": "2-col",
      "choices": [
        { "code": "P-A1", "label": "Candidate A" },
        { "code": "P-A2", "label": "Candidate B" },
        { "code": "P-A3", "label": "Candidate C" },
        { "code": "P-A4", "label": "Candidate D" }
      ]
    },
    {
      "type": "rating_scale",
      "code": "SAT-EXP",
      "title": "Rate your experience",
      "scale": [1,2,3,4,5],
      "layout": "1-col",
      "question": "Overall satisfaction"
    }
  ]
}
```
Validation rules are included in the original document (see previous answer’s schema section).

## 6) Handlebars Templates

resources/templates/ballot.hbs
```handlebars
<div class="document">
  <header>
    <h1>{{document.title}}</h1>
    <p class="uid">{{document.unique_id}}</p>
  </header>

  {{#each sections}}
    {{> section this}}
  {{/each}}

  <footer>
    <p>Powered by omr-templates</p>
  </footer>
</div>
```

resources/templates/partials/section.hbs
```handlebars
<section data-code="{{code}}" data-type="{{type}}" class="section {{layout}}">
  <h2>{{title}}</h2>

  {{#if (eq type "multiple_choice")}}
    <ul class="mcq">
      {{#each choices}}
        <li data-code="{{code}}">
          <span class="choice-label">{{label}}</span>
        </li>
      {{/each}}
    </ul>
  {{/if}}

  {{#if (eq type "rating_scale")}}
    <div class="rating">
      <p class="question">{{question}}</p>
      <div class="scale">
        {{#each scale}}
          <span class="tick">{{this}}</span>
        {{/each}}
      </div>
    </div>
  {{/if}}
</section>
```

## 7) Smart PHP Rendering Engine (TCPDF)

Key classes:
•	SmartLayoutRenderer
•	MultipleChoiceRenderer
•	LayoutContext
•	CoordinatesRegistry
•	OMRDrawer

(All code excerpts provided in full version — you can copy from prior answer.)

## 8) OMR Layer

Fiducials, timing marks, quiet zones, and barcode are drawn via OMRDrawer.
Each is registered in CoordinatesRegistry to produce deterministic pixel mappings for OpenCV calibration.

## 9) Coordinates Export Example

storage/app/omr/coords/BAL-2025-0000123.json
(contains fiducials, timing marks, and bubble centers — see full JSON in previous plan)

## 10) CLI Command
```shell
php artisan omr:render ballot resources/samples/sample-ballot.json --template=ballot.hbs
```
Generates:

storage/app/omr/BAL-2025-0000123.pdf
storage/app/omr/coords/BAL-2025-0000123.json

## 11) Heuristics

	•	Auto-column width, dynamic wrapping.
	•	Quiet zone and margin enforcement.
	•	Continuation titles (“(cont.)”).
	•	Auto page breaks using checkPageBreak().
	•	Title & fiducials redrawn on new pages.

## 12) Testing

	•	Unit: geometry and overflow checks.
	•	Feature: render → compare with golden file.
	•	Regression: fiducials & coordinates positions.
	•	Property-based: random choice labels (no overlap).

## 13) Printing Guidelines

	•	Print at 300 DPI or higher.
	•	Disable scaling (“Actual Size”).
	•	Avoid printer footers.
	•	Laser preferred for solid fiducial squares.

## 14) Roadmap

Version
Features
v0.1 MultipleChoice + RatingScale + fiducials + coords
v0.2 Matrix/Checklist + timing marks
v0.3 Continuation banners + RTL support
v0.4 HTML preview + AI layout suggestion
v0.5 Full visual editor integration

## 15) Summary

The updated lbhurtado/omr-templates will:
•	Accept a declarative JSON spec.
•	Compile with Handlebars.
•	Render automatically with SmartLayoutRenderer.
•	Output deterministic coordinates.json for Python/OpenCV.

This ensures professional-quality, automatically formatted ballots, surveys, and OMR sheets — perfectly aligned with the appreciation pipeline.
