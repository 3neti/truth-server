# Truth OMR Template System - Complete User Manual

**Version**: 1.0  
**Date**: October 24, 2025  
**System Status**: Phases 1-6 Implemented

---

## Quick Start

**What is this system?**  
A comprehensive template management system for creating, organizing, and deploying OMR (Optical Mark Recognition) ballots and forms.

**Key Features**:
- 📁 Organize templates into families with variants
- 🔄 Version control with rollback
- 📤 Export/import for sharing
- ✅ Data validation with JSON Schema
- 🔒 Template signing for integrity verification
- ☁️ Remote template storage (GitHub, HTTP)

**5-Minute Start**:
1. Navigate to Templates → Advanced Template Editor
2. Click "Template Families" button
3. Browse available families or create your own
4. Load a template, modify data, preview
5. Export to share with others

---

## Table of Contents

- [Core Concepts](#core-concepts)
- [Template Families](#template-families)
- [Creating & Editing Templates](#creating--editing-templates)
- [Version Control](#version-control)
- [Sharing Templates](#sharing-templates)
- [Validation & Security](#validation--security)
- [Remote Templates](#remote-templates)
- [Common Workflows](#common-workflows)
- [API Guide](#api-guide)
- [Troubleshooting](#troubleshooting)

---

## Core Concepts

### What is a Template?

A **template** is a Handlebars file that defines the layout and structure of a ballot or form:

```handlebars
<div class="ballot">
  <h1>{{election_name}}</h1>
  <div class="precinct">Precinct: {{precinct}}</div>
  
  {{#each candidates}}
  <div class="candidate-row">
    <span class="position">{{this.position}}</span>
    <span class="name">{{this.name}}</span>
    <div class="bubble"></div>
  </div>
  {{/each}}
</div>
```

### What is a Template Family?

A **family** groups related templates that serve the same purpose but have different layouts:

```
"Ballot 2025" Family
├─ single-column    (for narrow paper)
├─ two-column       (for standard  paper)
└─ three-column     (for wide paper)
```

**Why families?**
- Organize related templates
- Switch layouts easily
- Share as a unit
- Version together

### What are Layout Variants?

**Variants** are different arrangements of the same content:

| Variant | Use Case |
|---------|----------|
| `single-column` | Narrow ballots, mobile-friendly |
| `two-column` | Standard 8.5x11" paper |
| `three-column` | Wide formats, more candidates |
| `compact` | Space-saving design |
| `large-print` | Accessibility |

### Storage Types

Templates can be stored three ways:

1. **Local**: Template content in database (default)
2. **Remote**: Reference to external source (GitHub, URL)
3. **Hybrid**: Mix of local and remote variants in one family

---

## Template Families

### Browsing Families

1. Open **Advanced Template Editor**
2. Click **"Template Families"** button (toolbar)
3. **Family Browser** opens as a modal

**What you see**:
- Grid of family cards
- Search box (filter by name/description)
- Category tabs (All, Ballots, Surveys, Tests, Questionnaires)
- Count of families shown

**Family Card shows**:
- Family name
- Category badge
- Number of variants
- Actions: Load, Export, Delete

### Creating a Family

**Method 1: Via UI** (Coming soon)

**Method 2: Via API**:
```bash
curl -X POST http://truth.test/api/template-families \
  -H "Content-Type: application/json" \
  -d '{
    "slug": "ballot-2025-national",
    "name": "2025 National Election Ballot",
    "category": "ballot",
    "description": "Official ballot for national elections",
    "version": "1.0.0",
    "is_public": true
  }'
```

**Required Fields**:
- `slug`: Unique identifier (lowercase, hyphens)
- `name`: Display name
- `category`: ballot | survey | test | questionnaire

**Optional Fields**:
- `description`: Helpful context
- `version`: Semantic version (default: 1.0.0)
- `is_public`: Share with others (default: false)

### Adding Variants to a Family

After creating a family, add variants:

```bash
curl -X POST http://truth.test/api/templates/library \
  -H "Content-Type: application/json" \
  -d '{
    "name": "2025 National Ballot (Single Column)",
    "category": "ballot",
    "family_id": 1,
    "layout_variant": "single-column",
    "handlebars_template": "<div>...</div>",
    "sample_data": {"candidates": [...]},
    "version": "1.0.0"
  }'
```

**Repeat** for each variant (two-column, three-column, etc.)

### Loading a Family

1. In Family Browser, find your family
2. Click **"Load"** button
3. If multiple variants:
   - **Variant Selector** modal appears
   - Choose a variant
   - Click "Load Template"
4. If single variant:
   - Loads automatically
5. Template appears in editor with sample data

---

## Creating & Editing Templates

### Creating from Scratch

1. **Open Advanced Template Editor**
2. **Write Handlebars Template**:
   ```handlebars
   <div class="ballot">
     <h1>{{title}}</h1>
     {{#each items}}
       <div>{{this.name}}</div>
     {{/each}}
   </div>
   ```

3. **Add Sample Data** (JSON):
   ```json
   {
     "title": "Test Ballot",
     "items": [
       {"name": "Item 1"},
       {"name": "Item 2"}
     ]
   }
   ```

4. **Preview** (click Preview button)
5. **Save**:
   - Click "Save as New Template"
   - Fill in name, category
   - Optionally assign to family
   - Click Save

### Editing Existing Template

1. **Load template** (from library or family)
2. **Modify** template or data
3. **Preview** changes
4. **Save**:
   - Click "Save"
   - New version is created automatically
   - Patch number increments

### Template Tips

**Handlebars Helpers**:
```handlebars
{{! Comments }}
{{variable}}                    {{! Output variable }}
{{#if condition}}...{{/if}}     {{! Conditional }}
{{#each items}}...{{/each}}     {{! Loop }}
{{#unless condition}}...{{/unless}}  {{! Inverse if }}
```

**Best Practices**:
- ✅ Use semantic class names (`.candidate-row` not `.row1`)
- ✅ Test with realistic data
- ✅ Keep templates focused (one purpose)
- ✅ Comment complex logic
- ✅ Use consistent naming

---

## Version Control

### How Versioning Works

Every template has a **semantic version**: `MAJOR.MINOR.PATCH`

**Example**: `1.2.3`
- **MAJOR** (1): Breaking changes
- **MINOR** (2): New features, backward-compatible
- **PATCH** (3): Bug fixes

**Automatic Versioning**:
- When you save a template, patch increments automatically
- `1.0.0` → `1.0.1` → `1.0.2`

### Viewing History

**Via API**:
```bash
GET /api/templates/library/1/versions
```

Response:
```json
{
  "versions": [
    {
      "id": 3,
      "version": "1.0.2",
      "changelog": "Fixed spacing in candidate rows",
      "created_by": 1,
      "created_at": "2025-10-24T10:00:00Z"
    },
    {
      "id": 2,
      "version": "1.0.1",
      "changelog": "Updated header style",
      "created_by": 1,
      "created_at": "2025-10-23T15:00:00Z"
    }
  ]
}
```

### Rolling Back

**When to rollback**:
- Bug introduced in latest version
- Need to revert to stable version
- Experimental changes didn't work

**How to rollback**:
```bash
POST /api/templates/library/1/rollback/2
```

**What happens**:
1. Current version backed up
2. Content restored from version 2
3. Template updated
4. Version number restored

### Manual Version Bumping

To increment major or minor version:

```php
$template = Template::find(1);
$template->incrementVersion('minor'); // 1.0.2 → 1.1.0
$template->save();

$template->incrementVersion('major'); // 1.1.0 → 2.0.0
$template->save();
```

---

## Sharing Templates

### Export a Family

**Via UI**:
1. Open Template Families browser
2. Find family to export
3. Click **"📤 Export"** button
4. JSON file downloads: `{slug}-family.json`

**Via API**:
```bash
GET /api/template-families/1/export > ballot-2025.json
```

**What's exported**:
- Family metadata (slug, name, category, version)
- All variants with their templates
- Sample data for each variant
- Export timestamp

**File size**:
- Local templates: ~50KB (includes full content)
- Remote templates: ~2KB (just references)

### Import a Family

**Via UI**:
1. Open Template Families browser
2. Click **"⬆️ Import"** button (header)
3. Select JSON file
4. Family imports automatically
5. Success message appears

**Via API**:
```bash
curl -X POST http://truth.test/api/template-families/import \
  -H "Content-Type: application/json" \
  -d @ballot-2025.json
```

**Import Behavior**:
- **Slug conflict?** Appends `-imported-1`, `-imported-2`, etc.
- **Ownership**: You become the owner
- **Privacy**: All imports default to `is_public: false`
- **IDs**: New IDs generated (no conflicts)

### Sharing Workflows

**Email/Slack**:
```
1. Export family → 2. Send JSON file → 3. Colleague imports
```

**Git Repository**:
```bash
# In your project repo
mkdir templates
cd templates
git add ballot-2025-family.json
git commit -m "Add ballot template"
git push

# Colleague pulls and imports
git pull
# Import via API or UI
```

**Cloud Storage**:
```
1. Export family
2. Upload to Dropbox/Google Drive
3. Share link
4. Download and import
```

---

## Validation & Security

### Data Validation with JSON Schema

**Purpose**: Ensure data conforms to expected structure.

**Adding Schema to Template**:

```json
{
  "type": "object",
  "required": ["election_name", "precinct", "candidates"],
  "properties": {
    "election_name": {
      "type": "string",
      "minLength": 5,
      "maxLength": 200
    },
    "precinct": {
      "type": "string",
      "pattern": "^[0-9]{3}-[A-Z]$"
    },
    "candidates": {
      "type": "array",
      "minItems": 1,
      "maxItems": 50,
      "items": {
        "type": "object",
        "required": ["name", "position"],
        "properties": {
          "name": {"type": "string"},
          "position": {"type": "integer", "minimum": 1}
        }
      }
    }
  }
}
```

**Validating Data**:

```bash
curl -X POST http://truth.test/api/templates/library/1/validate-data \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "election_name": "National Election 2025",
      "precinct": "001-A",
      "candidates": [
        {"name": "John Doe", "position": 1}
      ]
    }
  }'
```

**Response (Valid)**:
```json
{
  "success": true,
  "valid": true,
  "errors": [],
  "has_schema": true
}
```

**Response (Invalid)**:
```json
{
  "success": true,
  "valid": false,
  "errors": [
    "Field 'election_name' must be at least 5 characters",
    "Field 'precinct' does not match pattern"
  ],
  "has_schema": true
}
```

**Supported Validations**:
- **Required fields**: `required: ["field1", "field2"]`
- **Type checking**: `type: "string|integer|number|boolean|array|object"`
- **String length**: `minLength`, `maxLength`
- **Number range**: `minimum`, `maximum`
- **Array size**: `minItems`, `maxItems`
- **Patterns**: `pattern: "regex"`

### Template Signing

**Purpose**: Verify template hasn't been tampered with.

**Signing a Template**:
```bash
POST /api/templates/library/1/sign
```

Response:
```json
{
  "success": true,
  "checksum": "a3d4e5f67890abcdef...",
  "verified_at": "2025-10-24T10:00:00Z",
  "message": "Template signed successfully"
}
```

**What happens**:
1. SHA256 checksum generated from template + data
2. Checksum stored in database
3. Timestamp and user ID recorded

**Verifying a Template**:
```bash
GET /api/templates/library/1/verify
```

Response:
```json
{
  "success": true,
  "is_signed": true,
  "is_valid": true,
  "is_modified": false,
  "checksum": "a3d4e5f67890abcdef...",
  "verified_at": "2025-10-24T10:00:00Z",
  "verified_by": 1
}
```

**Modification Detection**:
If template is modified after signing:
```json
{
  "is_signed": true,
  "is_valid": false,
  "is_modified": true  // ⚠️ Template was changed!
}
```

**When to Sign**:
- ✅ Before distributing official templates
- ✅ Before deploying to production
- ✅ After thorough testing
- ✅ When freezing a version

**When to Re-sign**:
- After intentional modifications
- After version bump
- After bug fixes

---

## Remote Templates

### The Concept

**Traditional (Local)**:
```
JSON Data File (large)
├─ template: "<div>...full template content...</div>"  (50KB)
└─ data: {...}
```

**New (Remote)**:
```
JSON Data File (tiny)
├─ template_ref: "github:org/repo/template.hbs@v1.0.0"  (100 bytes)
└─ data: {...}
```

**Benefits**:
- 📦 **Tiny data files** (~2KB vs ~50KB)
- ☁️ **Centralized templates** (single source of truth)
- 🔄 **Auto-updates** (change version reference)
- ✅ **Verified** (checksums from source)
- 📤 **Portable** (works anywhere with internet)

### Template URI Format

**Syntax**: `provider:location@version`

**GitHub**:
```
github:org/repo/path/to/template.hbs@version

Examples:
- github:lbhurtado/omr-templates/ballot-2025/single-column.hbs@v1.0.0
- github:comelec/official-ballots/national.hbs@v2.1.0
- github:myorg/templates/survey.hbs@main
```

**HTTP/HTTPS**:
```
https://example.com/templates/ballot.hbs
http://templates.myorg.com/survey-2025.hbs
```

**Local**:
```
local:family-slug/variant
local:ballot-2025/single-column

local:template-id
local:123
```

### Creating a Remote Family

**Step 1: Publish Templates to GitHub**

Create repository structure:
```
omr-templates/
├── README.md
├── ballot-2025/
│   ├── single-column.hbs
│   ├── two-column.hbs
│   └── three-column.hbs
└── survey-2025/
    └── default.hbs
```

Commit and tag:
```bash
git add .
git commit -m "Add ballot templates"
git tag v1.0.0
git push origin main --tags
```

**Step 2: Create Remote Family**

```bash
curl -X POST http://truth.test/api/template-families \
  -H "Content-Type: application/json" \
  -d '{
    "slug": "ballot-2025-official",
    "name": "Official Ballot 2025",
    "category": "ballot",
    "storage_type": "remote",
    "repo_url": "https://github.com/myorg/omr-templates",
    "repo_provider": "github",
    "repo_path": "ballot-2025",
    "version": "v1.0.0"
  }'
```

**Step 3: Add Remote Variants**

```bash
curl -X POST http://truth.test/api/templates/library \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Official Ballot (Single Column)",
    "category": "ballot",
    "family_id": 1,
    "layout_variant": "single-column",
    "storage_type": "remote",
    "template_uri": "github:myorg/omr-templates/ballot-2025/single-column.hbs@v1.0.0"
  }'
```

### Using Standalone Data Files

**Create portable data file**:
```json
{
  "document": {
    "title": "Precinct 001-A Ballot",
    "unique_id": "BALLOT-001-A-2025",
    "template_ref": "github:myorg/omr-templates/ballot-2025/single-column.hbs@v1.0.0",
    "template_checksum": "sha256:abc123...",
    "created_at": "2025-10-24T10:00:00Z"
  },
  "data": {
    "precinct": "001-A",
    "election_name": "National Elections 2025",
    "candidates": [
      {"name": "John Doe", "position": 1, "party": "Independent"},
      {"name": "Jane Smith", "position": 2, "party": "Reform"}
    ]
  }
}
```

**Compile anywhere**:
```bash
curl -X POST http://truth.test/api/templates/compile-standalone \
  -H "Content-Type: application/json" \
  -d @precinct-001-a-data.json
```

**Result**:
1. System resolves `template_ref` to GitHub
2. Fetches template content
3. Verifies checksum (if provided)
4. Compiles with data
5. Returns rendered HTML/PDF

### Caching

**Automatic caching**:
- First request: Fetches from remote, caches locally
- Subsequent requests: Uses cache (fast!)
- Cache TTL: 24 hours default
- Fallback: If remote fails, uses cached version

**Cache status**:
```php
$template = Template::find(1);

echo $template->last_fetched_at;  // When last fetched
echo $template->isCacheStale();   // true/false
```

**Force refresh**:
```php
$content = $template->getTemplateContent(forceRefresh: true);
```

**Clear cache**:
```php
$template->clearCache();
```

---

## Common Workflows

### Workflow 1: Government Agency Publishing

**Scenario**: COMELEC publishes official election templates

```
1. COMELEC IT Team:
   - Create ballot templates
   - Test thoroughly
   - Sign templates (checksum)
   - Publish to GitHub: github.com/comelec/ballots
   - Tag release: v1.0.0

2. Regional Offices:
   - Create remote family pointing to COMELEC repo
   - Reference templates: github:comelec/ballots/2025/single-column.hbs@v1.0.0
   - Templates cached locally
   - No need to store full content

3. Field Workers:
   - Collect data with JSON files
   - Files contain only template_ref + data
   - Files are small (can email/SMS)
   - Verify with checksum

4. Central Server:
   - Receives data files
   - Fetches templates from GitHub (or cache)
   - Compiles and generates PDFs
   - All verified and consistent
```

**Benefits**:
- ✅ Single source of truth (COMELEC GitHub)
- ✅ Instant updates (change version tag)
- ✅ Small data files (portable)
- ✅ Verified templates (checksums)
- ✅ No manual distribution needed

### Workflow 2: Development → Staging → Production

**Scenario**: Software team deploying templates

```
Development:
1. Create/modify templates
2. Test in local instance
3. Export families as JSON
4. Commit to git: templates/ballot-2025.json
5. Push to repository

Staging:
1. Pull latest from git
2. Import families via API
3. Run integration tests
4. Verify rendering
5. Sign templates (if approved)

Production:
1. Pull latest from git
2. Import families via API
3. Verify signatures
4. Deploy
5. Monitor
```

**Git workflow**:
```bash
# Development
cd templates/
php artisan families:export ballot-2025
git add ballot-2025-family.json
git commit -m "Update ballot template v1.2.0"
git push

# Staging/Production
git pull
curl -X POST http://truth.test/api/template-families/import \
  -d @templates/ballot-2025-family.json
```

### Workflow 3: Hybrid Local + Remote

**Scenario**: Use official templates + add custom variants

```
1. Subscribe to Official Templates:
   - Import family: "COMELEC Ballot 2025"
   - Variants: single-column, two-column (remote)

2. Add Custom Variant:
   - Create: three-column-compact (local)
   - For special high-density precincts
   - Family becomes "hybrid"

3. Export Family:
   - Includes remote references (small)
   - Includes local variant (full content)
   - Others can import and use

4. Update Official Templates:
   - COMELEC releases v1.1.0
   - Update version tag in remote variants
   - Custom variant unchanged
```

### Workflow 4: Offline Data Collection

**Scenario**: Field workers in areas without internet

```
Preparation (Online):
1. Pre-download templates:
   - Load families in browser
   - Templates cached automatically
2. Give workers data collection forms

In the Field (Offline):
1. Workers fill forms
2. Data entered into JSON files
3. Files reference cached templates
4. No internet needed

Back Online:
1. Upload data files
2. System uses cached templates
3. Compile and generate PDFs
4. Submit results
```

---

## API Guide

### Authentication

APIs require authentication:
- Session-based (web app)
- CSRF token for POST/PUT/DELETE

**Get CSRF token**:
```bash
curl http://truth.test/sanctum/csrf-cookie
```

### Template Families

**List all families**:
```bash
GET /api/template-families
Query params: category, search, is_public

Response:
{
  "data": [
    {
      "id": 1,
      "slug": "ballot-2025",
      "name": "Election Ballot 2025",
      "category": "ballot",
      "version": "1.0.0",
      "variants_count": 3,
      "storage_type": "local"
    }
  ]
}
```

**Get single family**:
```bash
GET /api/template-families/1

Response:
{
  "id": 1,
  "slug": "ballot-2025",
  "name": "Election Ballot 2025",
  "templates": [
    {
      "id": 1,
      "layout_variant": "single-column",
      "name": "Ballot (Single Column)"
    }
  ]
}
```

**Create family**:
```bash
POST /api/template-families
{
  "slug": "survey-2025",
  "name": "Customer Survey 2025",
  "category": "survey",
  "version": "1.0.0",
  "is_public": false
}
```

**Update family**:
```bash
PUT /api/template-families/1
{
  "name": "Election Ballot 2025 (Updated)",
  "version": "1.1.0"
}
```

**Delete family**:
```bash
DELETE /api/template-families/1
```

**Export family**:
```bash
GET /api/template-families/1/export

Response: JSON manifest (see Export section)
```

**Import family**:
```bash
POST /api/template-families/import
Body: JSON manifest
```

### Templates

**List templates**:
```bash
GET /api/templates/library?category=ballot

Response:
{
  "success": true,
  "templates": [...]
}
```

**Get template**:
```bash
GET /api/templates/library/1
```

**Create template**:
```bash
POST /api/templates/library
{
  "name": "Survey Template",
  "category": "survey",
  "handlebars_template": "<div>...</div>",
  "sample_data": {...},
  "family_id": 1,
  "layout_variant": "default",
  "storage_type": "local"
}
```

**Update template**:
```bash
PUT /api/templates/library/1
{
  "handlebars_template": "<div>Updated...</div>"
}
```

**Delete template**:
```bash
DELETE /api/templates/library/1
```

### Versioning

**Get version history**:
```bash
GET /api/templates/library/1/versions

Response:
{
  "success": true,
  "versions": [
    {
      "id": 5,
      "version": "1.0.2",
      "changelog": "Bug fix",
      "created_at": "...",
      "created_by": 1
    }
  ]
}
```

**Rollback to version**:
```bash
POST /api/templates/library/1/rollback/3
```

### Validation & Signing

**Validate data**:
```bash
POST /api/templates/library/1/validate-data
{
  "data": {
    "field1": "value1",
    "field2": 123
  }
}
```

**Sign template**:
```bash
POST /api/templates/library/1/sign
```

**Verify template**:
```bash
GET /api/templates/library/1/verify
```

### Compilation

**Compile with inline template**:
```bash
POST /api/templates/compile
{
  "template": "<div>{{name}}</div>",
  "data": {"name": "John"}
}
```

**Compile standalone (with template_ref)**:
```bash
POST /api/templates/compile-standalone
{
  "document": {
    "template_ref": "github:org/repo/template.hbs@v1.0.0",
    "template_checksum": "sha256:abc..."
  },
  "data": {
    "field1": "value1"
  }
}
```

---

## Troubleshooting

### Problem: Can't find template in library

**Symptoms**: Template doesn't appear in list

**Check**:
1. Is it public or do you own it?
2. Correct category filter applied?
3. Template actually saved?

**Solution**:
```bash
# Check all your templates
GET /api/templates/library

# Check specific template
GET /api/templates/library/123
```

### Problem: Import creates duplicate slug

**Symptoms**: Imported family gets `-imported-1` suffix

**Cause**: Slug already exists

**Solutions**:
1. Delete existing family first
2. Edit JSON file to change slug before import
3. Accept auto-generated slug and rename after

### Problem: Remote template won't load

**Symptoms**: `Failed to fetch template from GitHub`

**Check**:
1. ✅ Internet connection working?
2. ✅ GitHub repository public?
3. ✅ File path correct?
4. ✅ Version tag exists?
5. ✅ Template URI format correct?

**Test manually**:
```bash
# Try fetching directly
curl https://raw.githubusercontent.com/org/repo/v1.0.0/path/to/template.hbs
```

**Fallback**: System uses cached version if available

### Problem: Validation fails unexpectedly

**Symptoms**: Data looks correct but validation fails

**Debug**:
1. Check exact field types (string vs number)
2. Check field names (case-sensitive)
3. Check required fields present
4. Review JSON schema carefully

**Test with minimal data**:
```json
{
  "data": {
    "required_field_only": "test"
  }
}
```

### Problem: Checksum mismatch

**Symptoms**: `Template checksum verification failed`

**Cause**: Template was modified after signing

**Check who modified**:
```bash
GET /api/templates/library/123
# Look at: updated_at, user_id
```

**Solutions**:
1. If intentional: Re-sign template
2. If unauthorized: Rollback to previous version
3. If uncertain: Check version history

### Problem: Cache showing old content

**Symptoms**: Remote template shows outdated content

**Solutions**:

**Force refresh**:
```php
$template->getTemplateContent(forceRefresh: true);
```

**Clear cache**:
```php
$template->clearCache();
```

**Wait for TTL**: Cache expires after 24 hours

### Problem: Can't delete family

**Symptoms**: Delete button disabled or fails

**Reasons**:
1. Family is public (can't delete public families)
2. You don't own it
3. Family has templates in use

**Solution**:
1. Make private first
2. Contact owner
3. Delete templates first, then family

---

## Best Practices

### Naming Conventions

**Families**:
```
✅ ballot-2025-national
✅ survey-customer-satisfaction-2025
✅ test-entrance-exam-v2

❌ template1
❌ MyTemplate
❌ ballot (too generic)
```

**Variants**:
```
✅ single-column
✅ two-column-landscape
✅ compact-high-density

❌ variant1
❌ v1
❌ new
```

### Template Structure

```handlebars
{{!-- Header: Document identification --}}
<div class="document-header">
  <h1>{{document_title}}</h1>
  <div class="metadata">
    ID: {{unique_id}} | Date: {{date}}
  </div>
</div>

{{!-- Content: Main ballot/form content --}}
<div class="content">
  {{#each sections}}
    <section class="{{this.type}}">
      <h2>{{this.title}}</h2>
      {{#each this.items}}
        <div class="item">{{this.text}}</div>
      {{/each}}
    </section>
  {{/each}}
</div>

{{!-- Footer: Instructions or signatures --}}
<div class="footer">
  <p>{{instructions}}</p>
</div>
```

### Version Management

**When to increment**:
- **Patch** (`1.0.0` → `1.0.1`): Bug fixes, typos
- **Minor** (`1.0.0` → `1.1.0`): New features, backward-compatible
- **Major** (`1.0.0` → `2.0.0`): Breaking changes

**Changelog tips**:
- ✅ "Fixed spacing in candidate rows"
- ✅ "Added support for write-in candidates"
- ✅ "BREAKING: Changed data structure for precincts"
- ❌ "Updated"
- ❌ "Changes"

### Security

**Signing workflow**:
1. Create template
2. Test thoroughly
3. Get approval
4. Sign template
5. Distribute signed template
6. Recipients verify checksum

**Access control**:
- Keep templates private by default
- Only make public if intended for sharing
- Review who has access to families
- Use signed templates for official use

### Performance

**Local vs Remote decision**:

Use **Local** when:
- ✅ Template changes frequently
- ✅ Full control needed
- ✅ No internet dependency desired
- ✅ Small number of templates

Use **Remote** when:
- ✅ Template stable (changes infrequently)
- ✅ Multiple instances need same template
- ✅ Centralized updates important
- ✅ Distribution at scale

### Collaboration

**Team workflow**:
1. **Designate template owners**
2. **Use git for template JSON files**
3. **Document changes in changelog**
4. **Review before signing**
5. **Communicate updates to team**

**Git repository structure**:
```
project/
├── src/
├── templates/
│   ├── ballot-2025-family.json
│   ├── survey-2025-family.json
│   └── README.md
└── docs/
```

---

## Appendix

### Storage Type Decision Matrix

| Factor | Local | Remote | Hybrid |
|--------|-------|--------|--------|
| **Control** | Full | Shared | Mixed |
| **Updates** | Manual | Auto | Mixed |
| **Internet** | Not needed | Required | Required |
| **File size** | Large | Small | Medium |
| **Best for** | Custom | Official | Custom + Official |

### Supported JSON Schema Types

| Type | Example | Constraints |
|------|---------|-------------|
| `string` | `"text"` | minLength, maxLength, pattern |
| `integer` | `42` | minimum, maximum |
| `number` | `3.14` | minimum, maximum |
| `boolean` | `true` | - |
| `array` | `[1,2,3]` | minItems, maxItems |
| `object` | `{...}` | properties, required |

### Template URI Examples

```
# GitHub with version tag
github:lbhurtado/omr-templates/ballot-2025/single-column.hbs@v1.0.0

# GitHub with branch
github:myorg/templates/survey.hbs@main
github:myorg/templates/test.hbs@develop

# HTTP/HTTPS
https://example.com/templates/ballot.hbs
http://templates.org/survey-2025.hbs

# Local by family/variant
local:ballot-2025/single-column
local:survey-2025/default

# Local by ID
local:123
local:456
```

### HTTP Status Codes

| Code | Meaning | Common Causes |
|------|---------|---------------|
| 200 | Success | Request completed |
| 201 | Created | Resource created successfully |
| 400 | Bad Request | Invalid data format |
| 401 | Unauthorized | Not logged in |
| 403 | Forbidden | Don't have permission |
| 404 | Not Found | Resource doesn't exist |
| 419 | CSRF Error | Missing/invalid CSRF token |
| 422 | Validation Error | Data doesn't meet requirements |
| 500 | Server Error | Something went wrong |

---

## Resources

### Documentation

- **Technical Specification**: `TEMPLATE_REGISTRY_AND_COMPOSITION_SYSTEM.md`
- **Integration Roadmap**: `TEMPLATE_REGISTRY_INTEGRATION_ROADMAP.md`
- **Phase 3: Versioning**: `PHASE_3_IMPLEMENTATION_ROADMAP.md`
- **Phase 4: Sharing**: `TEMPLATE_FAMILY_SHARING.md`
- **Phase 5: Validation**: `PHASE_5_ADVANCED_FEATURES.md`
- **Implementation Summary**: `IMPLEMENTATION_SUMMARY.md`

### Quick Reference

**Key URLs**:
- Template Families API: `/api/template-families`
- Templates API: `/api/templates/library`
- Compilation: `/api/templates/compile-standalone`

**Common Operations**:
```bash
# List families
GET /api/template-families

# Export family
GET /api/template-families/{id}/export

# Import family
POST /api/template-families/import

# Compile standalone
POST /api/templates/compile-standalone

# Sign template
POST /api/templates/library/{id}/sign

# Validate data
POST /api/templates/library/{id}/validate-data
```

---

**Version**: 1.0  
**Last Updated**: October 24, 2025  
**Next Review**: December 2025

For support: support@truth.app  
For bugs: github.com/truth/truth/issues
