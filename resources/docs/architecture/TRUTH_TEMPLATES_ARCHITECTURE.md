# Truth Templates Architecture

## System Overview

The **Truth Templates** initiative is a template-based document generation and OMR (Optical Mark Recognition) specification system that bridges human-readable ballot designs with machine-readable election data for computer vision and augmented reality processing.

## Core Entities & Relationships

```
┌─────────────────────────────────────────────────────────────────────────┐
│                        TRUTH TEMPLATES SYSTEM                           │
└─────────────────────────────────────────────────────────────────────────┘

┌──────────────────────┐
│  Template Family     │  "A blueprint lineage with versioned variants"
│  ──────────────────  │
│  - id                │
│  - slug              │  Example: "election-ballot", "survey-form"
│  - name              │
│  - description       │
│  - category          │
│  - repo_url          │  Optional: Git repository for distributed templates
│  - version           │
│  - is_public         │
└──────────┬───────────┘
           │
           │ has many (1:N)
           │
           ▼
┌──────────────────────┐
│  Template            │  "A reusable design with variable placeholders"
│  ──────────────────  │
│  - id                │
│  - family_id         │  FK: Links to Template Family
│  - layout_variant    │  Example: "standard", "wide", "compact"
│  - name              │
│  - description       │
│  - category          │
│  - handlebars_template│ The actual Handlebars HBS template code
│  - sample_data       │  JSON: Example data for preview
│  - is_public         │
│  - storage_type      │  "local" | "remote" | "hybrid"
│  - template_uri      │  Optional: URI for remote templates
└──────────┬───────────┘
           │
           │ referenced by (N:M via template_ref)
           │
           ▼
┌──────────────────────┐
│  Template Data       │  "Structured content that populates a template"
│  ──────────────────  │
│  - id                │
│  - name              │
│  - description       │
│  - category          │
│  - template_ref      │  String: "local:family-slug/variant" or "local:id"
│  - data              │  JSON: The actual data payload
│  - is_public         │
└──────────┬───────────┘
           │
           │ compiles to (on-demand)
           │
           ▼
┌──────────────────────┐
│  OMR Specification   │  "Machine-readable document structure for CV/AR"
│  ──────────────────  │
│  - document          │  Metadata: title, unique_id, layout, locale
│  - sections[]        │  Array of mark recognition zones
│    - type            │  "single-choice" | "multiple-choice" | "ranking" | "freetext"
│    - code            │  Unique identifier for the section
│    - title           │
│    - layout          │
│    - maxSelections   │
│    - choices[]       │  Array of {code, label}
└──────────┬───────────┘
           │
           │ renders to
           │
           ▼
┌──────────────────────┐
│  PDF + Coordinates   │  "Print-ready ballot with CV scan coordinates"
│  ──────────────────  │
│  - PDF file          │  Human-readable ballot for printing
│  - Coords JSON       │  Machine-readable coordinate map for webcam scanning
└──────────────────────┘
           │
           │ scanned & processed by
           │
           ▼
┌──────────────────────┐
│  Election Return     │  "Blockchain-ready election data"
│  ──────────────────  │
│  (via truth-election-php packages)
└──────────────────────┘
```

## Nomenclature & Definitions

### 1. Template Family
**What it is:** A logical grouping of related template variants that serve the same purpose but with different layouts or configurations.

**Purpose:** 
- Version control for template evolution
- Semantic grouping (e.g., all "election-ballot" templates)
- Enables family-level operations (export, import, distribution)
- Supports distributed template packages via `repo_url`

**Naming Convention:** `kebab-case` slug (e.g., `election-ballot`, `survey-form`, `ranking-sheet`)

**Example:**
```
Family: "election-ballot"
├── Variant: "standard"    (2-column, compact)
├── Variant: "wide"        (3-column, expanded)
└── Variant: "accessible"  (large text, high contrast)
```

### 2. Template
**What it is:** A Handlebars (`.hbs`) template file that defines the structure and layout of a document with variable placeholders.

**Purpose:**
- Reusable design patterns
- Compile-time data binding via Handlebars syntax
- Generates OMR Specifications when combined with Template Data

**Storage Types:**
- `local`: Stored in database
- `remote`: Fetched from external URI
- `hybrid`: Fallback logic

**Template Reference Format:**
```
local:family-slug/variant     → "local:election-ballot/standard"
local:template-id             → "local:42"
remote:https://...            → Remote URI
```

**Lifecycle:**
1. Created/imported into Template Library
2. Organized under Template Family
3. Referenced by Template Data via `template_ref`
4. Compiled on-demand to generate OMR Spec

### 3. Template Data
**What it is:** A JSON document containing the actual content/data that will be injected into a template.

**Purpose:**
- Separates content from presentation
- Portable and lightweight (~2KB vs ~200KB full spec)
- Can be versioned, shared, and reused across different template variants
- Enables data-driven document generation

**Key Properties:**
- `template_ref`: Links to a specific template (family/variant or ID)
- `data`: The actual payload (candidates, positions, questions, etc.)
- `category`: Organizational grouping

**Example Data File:**
```json
{
  "name": "2025 General Election - Precinct 001",
  "template_ref": "local:election-ballot/standard",
  "category": "election",
  "data": {
    "election_name": "2025 General Election",
    "precinct": "Precinct 001",
    "positions": [
      {
        "code": "PRESIDENT",
        "title": "President",
        "candidates": [...]
      }
    ]
  }
}
```

**Lifecycle:**
1. Created in Data Editor
2. Linked to a template via `template_ref`
3. Compiled with template → OMR Specification
4. Rendered to PDF with coordinates
5. Printed and scanned by webcam
6. Processed into Election Returns

### 4. OMR Specification
**What it is:** A standardized JSON document that describes the exact structure, layout, and recognition zones of a document for computer vision processing.

**Purpose:**
- Machine-readable document structure
- Defines mark recognition zones with precise coordinates
- Input for PDF renderer and CV scanning
- Portable format for AR/CV systems

**Generation:** Compiled from Template + Template Data

**Consumption:** 
- PDF Renderer (generates print-ready ballots)
- Webcam Scanner (AR overlay and mark detection)
- Election processor (vote tallying)

### 5. Template URI / Template Reference
**What it is:** A string identifier that uniquely locates a template.

**Formats:**
```
local:election-ballot/standard    # PREFERRED: Family + Variant
local:42                          # Fallback: Direct Template ID
remote:https://cdn.../ballot.hbs  # Remote: External URI
```

**Resolution Priority:**
1. Family/Variant (semantic, portable)
2. Template ID (direct, brittle)
3. Remote URI (distributed, requires network)

## System Actions & Services

### Core Services

#### 1. Template Management Service
**Actions:**
- `createTemplate()` - Create new template with HBS code
- `updateTemplate()` - Modify existing template
- `deleteTemplate()` - Remove template
- `compileTemplate()` - Merge template + data → OMR Spec
- `validateTemplate()` - Check HBS syntax and structure

#### 2. Family Management Service
**Actions:**
- `createFamily()` - Create new template family
- `deleteFamily()` - Remove family and optionally its templates
- `exportFamily()` - Package family + variants for distribution
- `importFamily()` - Import external template family
- `getFamilyVariants()` - List all variants in a family

#### 3. Data Management Service
**Actions:**
- `createData()` - Create new template data file
- `updateData()` - Modify existing data
- `deleteData()` - Remove data file
- `validateData()` - Verify data against template schema
- `loadTemplateFromRef()` - Auto-load template when opening data

#### 4. Rendering Service
**Actions:**
- `renderPDF()` - Generate PDF from OMR Spec
- `generateCoordinates()` - Extract CV coordinates from layout
- `downloadPDF()` - Retrieve rendered document
- `previewSpec()` - Live preview of compiled specification

#### 5. Storage Service
**Actions:**
- `saveToLibrary()` - Persist template/data to database
- `loadFromLibrary()` - Retrieve by ID or family reference
- `searchTemplates()` - Query templates by category/name
- `listFamilies()` - Browse available template families

## Data Flow: Ballot Creation to Election Return

```
┌─────────────────────────────────────────────────────────────────────────┐
│                     BALLOT LIFECYCLE WORKFLOW                            │
└─────────────────────────────────────────────────────────────────────────┘

PHASE 1: DESIGN
──────────────
User creates ballot template
    → Advanced Editor (Handlebars template)
    → Define positions, candidates, layouts
    → Save to Template Library under Family
    → Template has its own lifecycle (versioned, shareable)

PHASE 2: DATA PREPARATION
──────────────────────────
User creates election data
    → Data Editor (JSON data file)
    → Specify election details, candidates, precincts
    → Link to template via template_ref
    → Data has its own lifecycle (reusable across variants)

PHASE 3: COMPILATION
────────────────────
System merges template + data
    → Handlebars compilation
    → Generates OMR Specification (JSON)
    → Preview in browser

PHASE 4: RENDERING
──────────────────
System generates printable ballot
    → OMR Spec → PDF Renderer (truth-renderer-php)
    → PDF (human-readable ballot)
    → Coordinates JSON (CV scan map)

PHASE 5: PRINTING & DISTRIBUTION
─────────────────────────────────
Ballot is printed and distributed to voters

PHASE 6: SCANNING (AR LIVE)
────────────────────────────
Voter marks ballot
    → Webcam captures ballot in real-time
    → AR overlay shows recognized marks
    → Computer Vision detects filled bubbles
    → Validates against OMR coordinates

PHASE 7: VOTE RECORDING
────────────────────────
System records voter selections
    → ReadVote action (truth-election-php)
    → FinalizeBallot action
    → CastBallot action
    → Ballots stored in precinct context

PHASE 8: TALLYING
──────────────────
Election closes, votes are tallied
    → TallyVotes action
    → AttestReturn action (BEI signatures)
    → RecordStatistics action

PHASE 9: RETURNS GENERATION
────────────────────────────
System generates election returns
    → WrapupVoting action
    → PDF election return (truth-renderer-php)
    → JSON return data (blockchain-ready)
    → QR payloads (truth-qr-php, truth-codec-php)
```

## Key Design Principles

### 1. Separation of Concerns
- **Templates** define structure (HOW to display)
- **Data** defines content (WHAT to display)
- **Specs** define recognition (WHERE to scan)

### 2. Portability
- Template Data files are lightweight and portable
- Family/Variant references over direct IDs
- Can distribute template families as packages

### 3. Reusability
- One template → many data files
- One family → multiple layout variants
- Template and data have independent lifecycles

### 4. Versioning
- Template families enable version tracking
- Data files can migrate between template versions
- OMR specs are immutable snapshots

### 5. Progressive Enhancement
- Basic: Simple template editor
- Intermediate: Handlebars with data binding
- Advanced: Distributed template packages with version control

## Recommended Naming Conventions

### Template Families
```
election-ballot
survey-form
ranking-sheet
answer-sheet
registration-form
```

### Template Variants
```
standard       (default, most common)
wide           (3-column, expanded)
compact        (condensed, more items per page)
accessible     (large text, high contrast)
bilingual      (multiple languages)
portrait       (vertical orientation)
landscape      (horizontal orientation)
```

### Template Data Categories
```
election       (ballot data)
survey         (survey responses)
test           (test/development data)
sample         (example/demo data)
production     (real election data)
```

### Template Reference Examples
```
local:election-ballot/standard          ✅ PREFERRED
local:election-ballot/accessible        ✅ PREFERRED
local:survey-form/bilingual            ✅ PREFERRED
local:42                               ⚠️  Fallback only
remote:https://cdn.truth.ph/ballot.hbs  ℹ️  For distributed systems
```

## Future Enhancements

1. **Template Marketplace** - Share and discover community templates
2. **Version Control Integration** - Git-backed template families
3. **Schema Validation** - JSON Schema for data files
4. **Live Collaboration** - Multi-user template editing
5. **Template Analytics** - Usage tracking and optimization
6. **AR Preview** - Webcam preview before printing
7. **Blockchain Anchoring** - Immutable template hashes
