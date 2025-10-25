# Template-Data Separation Plan for OMR Templates

## Overview

Decouple the template structure (Handlebars) from the actual data (JSON), allowing users to:
1. Define reusable templates for ballots/surveys
2. Fill templates with different data
3. Preview the merged result before rendering

---

## Current State

**Current Approach:**
```json
{
  "document": {
    "title": "Sample Ballot",
    "unique_id": "BAL-001"
  },
  "sections": [
    {
      "type": "multiple_choice",
      "code": "PRESIDENT",
      "title": "President",
      "choices": [
        { "code": "A", "label": "Candidate A" }
      ]
    }
  ]
}
```

Everything is in one JSON specification.

---

## Proposed State

**Template (Handlebars):**
```handlebars
{
  "document": {
    "title": "{{election.title}}",
    "unique_id": "{{election.id}}",
    "layout": "{{layout}}"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "maxSelections": {{maxSelections}},
      "layout": "{{../layout}}",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{code}}",
          "label": "{{name}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

**Data (JSON):**
```json
{
  "election": {
    "title": "2025 General Election",
    "id": "BAL-2025-001"
  },
  "layout": "2-col",
  "positions": [
    {
      "code": "PRESIDENT",
      "title": "President",
      "maxSelections": 1,
      "candidates": [
        { "code": "P-A", "name": "Candidate A" },
        { "code": "P-B", "name": "Candidate B" }
      ]
    }
  ]
}
```

**Result:** Template + Data = JSON Specification (same as current format)

---

## Architecture Changes

### 1. UI Changes (3-Pane Layout)

```
┌─────────────────────────────────────────────────────────────┐
│                    OMR Template Editor                       │
├──────────────┬──────────────────┬─────────────────────────────┤
│  Template    │   Data          │      Preview                │
│  (25%)       │   (25%)         │      (50%)                  │
│              │                 │                             │
│ Handlebars   │   JSON Data     │   ┌─────────────────────┐  │
│ Template     │   Values        │   │  Merged JSON        │  │
│              │                 │   │  Specification      │  │
│              │                 │   └─────────────────────┘  │
│              │                 │   ┌─────────────────────┐  │
│              │                 │   │  PDF Preview        │  │
│              │                 │   │                     │  │
│              │                 │   └─────────────────────┘  │
└──────────────┴──────────────────┴─────────────────────────────┘
│              Toolbar: Merge | Validate | Render PDF          │
└─────────────────────────────────────────────────────────────┘
```

### 2. Vue Component Structure

```
resources/js/pages/Templates/
├── Editor.vue (current - for simple mode)
├── AdvancedEditor.vue (new - 3-pane mode)
├── Components/
│   ├── TemplatePane.vue (Handlebars editor)
│   ├── DataPane.vue (JSON data editor)
│   ├── PreviewPane.vue (merged spec + PDF)
│   ├── ModeToggle.vue (switch between simple/advanced)
│   └── TemplateLibrary.vue (browse/save templates)
```

### 3. Pinia Store Updates

```typescript
// stores/templates.ts
export const useTemplatesStore = defineStore('templates', () => {
  // New: Template and Data separation
  const handlebarsTemplate = ref<string>('') // Handlebars template
  const templateData = ref<Record<string, any>>({}) // JSON data
  const mergedSpec = ref<TemplateSpec | null>(null) // Compiled result
  
  // Existing
  const spec = ref<TemplateSpec>({...}) // For simple mode
  const mode = ref<'simple' | 'advanced'>('simple')
  
  // New actions
  async function compileTemplate() {
    // Call backend to merge template + data
    const response = await axios.post('/api/templates/compile', {
      template: handlebarsTemplate.value,
      data: templateData.value
    })
    mergedSpec.value = response.data.spec
  }
  
  async function saveTemplate(name: string) {
    // Save template to database
  }
  
  async function loadTemplate(id: string) {
    // Load template from database
  }
  
  return {
    // Existing
    spec, mode,
    // New
    handlebarsTemplate, templateData, mergedSpec,
    compileTemplate, saveTemplate, loadTemplate
  }
})
```

### 4. Backend API Endpoints

```php
// New endpoints in TemplateController

/**
 * Compile Handlebars template with data
 * POST /api/templates/compile
 */
public function compile(Request $request): JsonResponse
{
    $validator = Validator::make($request->all(), [
        'template' => 'required|string',
        'data' => 'required|array',
    ]);

    if ($validator->fails()) {
        return response()->json([
            'success' => false,
            'errors' => $validator->errors(),
        ], 422);
    }

    try {
        $compiler = app(HandlebarsCompiler::class);
        $spec = $compiler->compile(
            $request->input('template'),
            $request->input('data')
        );
        
        return response()->json([
            'success' => true,
            'spec' => $spec,
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage(),
        ], 500);
    }
}

/**
 * Save template to database
 * POST /api/templates/library
 */
public function saveTemplate(Request $request): JsonResponse
{
    // Save to templates table
}

/**
 * List saved templates
 * GET /api/templates/library
 */
public function listTemplates(): JsonResponse
{
    // Return all templates
}

/**
 * Get specific template
 * GET /api/templates/library/{id}
 */
public function getTemplate(string $id): JsonResponse
{
    // Return template by ID
}
```

### 5. Database Schema

```php
// New migration: create_templates_table
Schema::create('templates', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->text('description')->nullable();
    $table->string('category'); // ballot, survey, test, etc.
    $table->longText('handlebars_template');
    $table->json('sample_data')->nullable();
    $table->json('schema')->nullable(); // JSON schema for validation
    $table->boolean('is_public')->default(false);
    $table->foreignId('user_id')->nullable()->constrained();
    $table->timestamps();
});

// New migration: create_template_instances_table
Schema::create('template_instances', function (Blueprint $table) {
    $table->id();
    $table->foreignId('template_id')->constrained('templates')->onDelete('cascade');
    $table->string('document_id')->unique();
    $table->json('data');
    $table->json('compiled_spec');
    $table->string('pdf_path')->nullable();
    $table->string('coords_path')->nullable();
    $table->timestamps();
});
```

### 6. Handlebars Compiler Service

```php
// packages/omr-template/src/Services/HandlebarsCompiler.php
namespace LBHurtado\OMRTemplate\Services;

use LightnCandy\LightnCandy;

class HandlebarsCompiler
{
    public function compile(string $template, array $data): array
    {
        // Compile Handlebars template
        $phpTemplate = LightnCandy::compile($template, [
            'flags' => LightnCandy::FLAG_HANDLEBARS |
                       LightnCandy::FLAG_ERROR_EXCEPTION |
                       LightnCandy::FLAG_RUNTIMEPARTIAL
        ]);
        
        // Create renderer
        $renderer = eval($phpTemplate);
        
        // Render with data
        $json = $renderer($data);
        
        // Parse and validate JSON
        $spec = json_decode($json, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception('Invalid JSON generated: ' . json_last_error_msg());
        }
        
        return $spec;
    }
    
    public function validate(string $template): bool
    {
        try {
            LightnCandy::compile($template, [
                'flags' => LightnCandy::FLAG_HANDLEBARS |
                           LightnCandy::FLAG_ERROR_EXCEPTION
            ]);
            return true;
        } catch (\Exception $e) {
            throw new \Exception('Invalid Handlebars syntax: ' . $e->getMessage());
        }
    }
}
```

---

## Implementation Steps

### Phase 1: Backend Foundation (1-2 days)

1. ✅ Install/verify `zordius/lightncandy` (already installed)
2. ✅ Create `HandlebarsCompiler` service
3. ✅ Add `/api/templates/compile` endpoint
4. ✅ Create database migrations for templates
5. ✅ Add template CRUD endpoints
6. ✅ Write unit tests for compiler

### Phase 2: Frontend - Advanced Editor (2-3 days)

1. ✅ Create `AdvancedEditor.vue` with 3-pane layout
2. ✅ Create `TemplatePane.vue` component
3. ✅ Create `DataPane.vue` component
4. ✅ Create `PreviewPane.vue` component
5. ✅ Update Pinia store with new state/actions
6. ✅ Add mode toggle (Simple ↔ Advanced)
7. ✅ Implement real-time template compilation
8. ✅ Add syntax highlighting for Handlebars

### Phase 3: Template Library (1-2 days)

1. ✅ Create `TemplateLibrary.vue` component
2. ✅ Add template browser/search
3. ✅ Implement save/load functionality
4. ✅ Add template categories
5. ✅ Create sample templates (ballot, survey, test)
6. ✅ Add import/export for templates

### Phase 4: Enhanced Features (1-2 days)

1. ✅ Add JSON schema validation for data
2. ✅ Implement template variables autocomplete
3. ✅ Add data validation against schema
4. ✅ Create template preview with sample data
5. ✅ Add template versioning
6. ✅ Implement template sharing

---

## User Workflow

### Advanced Mode Workflow:

1. **Create/Load Template**
   - User creates new Handlebars template OR
   - User loads existing template from library

2. **Edit Template**
   - Define structure with Handlebars syntax
   - Use variables: `{{election.title}}`, `{{#each positions}}`
   - Preview shows structure

3. **Provide Data**
   - Fill in JSON data with actual values
   - Data validates against template schema
   - Real-time validation feedback

4. **Preview Merged Result**
   - System compiles template + data
   - Shows resulting JSON specification
   - Shows PDF preview

5. **Render**
   - Click "Render PDF"
   - PDF generated from merged spec
   - Download PDF + coordinates

6. **Save for Reuse**
   - Save template to library
   - Save data for later use
   - Create template instances

---

## Example Use Cases

### Use Case 1: Multiple Elections with Same Structure

**Template:** `election-template-v1.hbs`
```handlebars
{
  "document": {
    "title": "{{election.title}}",
    "unique_id": "{{election.id}}"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "choices": [
        {{#each candidates}}
        {"code": "{{code}}", "label": "{{name}}"}{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

**Data for Election 1:** `election-2025-primary.json`
**Data for Election 2:** `election-2025-general.json`
**Data for Election 3:** `election-2026-midterm.json`

Same template, different data = Different ballots!

### Use Case 2: Survey Templates

**Template:** `satisfaction-survey-template.hbs`
```handlebars
{
  "document": {
    "title": "{{survey.title}}",
    "unique_id": "{{survey.id}}"
  },
  "sections": [
    {{#each questions}}
    {
      "type": "{{type}}",
      "code": "{{code}}",
      "title": "{{title}}",
      {{#if (eq type "rating_scale")}}
      "scale": [1,2,3,4,5],
      {{/if}}
      {{#if (eq type "multiple_choice")}}
      "choices": [
        {{#each options}}
        {"code": "{{code}}", "label": "{{label}}"}{{#unless @last}},{{/unless}}
        {{/each}}
      ],
      {{/if}}
      "question": "{{question}}"
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}
```

**Data:** Different survey questions for different departments/products.

---

## Benefits

### For Users:
- ✅ **Reusability** - Create once, use many times
- ✅ **Consistency** - Same structure across documents
- ✅ **Efficiency** - Just update data, not structure
- ✅ **Collaboration** - Share templates with team
- ✅ **Version Control** - Track template changes
- ✅ **Flexibility** - Easy to update structure

### For Developers:
- ✅ **Separation of Concerns** - Template logic vs data
- ✅ **Maintainability** - Easier to debug and update
- ✅ **Testability** - Test templates independently
- ✅ **Scalability** - Template library grows over time

---

## Migration Strategy

### Backward Compatibility

Keep the simple mode (current Editor.vue) for:
- Quick one-off documents
- Users who prefer direct JSON editing
- Simple templates without reuse needs

Add advanced mode for:
- Recurring document types
- Organizations with many similar documents
- Power users who need templates

**Toggle Button:**
```vue
<div class="mode-toggle">
  <button @click="mode = 'simple'">Simple Mode</button>
  <button @click="mode = 'advanced'">Advanced Mode</button>
</div>
```

---

## Technical Considerations

### 1. Handlebars Helpers

Add custom helpers for OMR-specific logic:

```php
// In HandlebarsCompiler
protected function registerHelpers(): array
{
    return [
        'eq' => function($a, $b) { return $a === $b; },
        'gt' => function($a, $b) { return $a > $b; },
        'lt' => function($a, $b) { return $a < $b; },
        'json' => function($value) { return json_encode($value); },
        'uppercase' => function($str) { return strtoupper($str); },
        'lowercase' => function($str) { return strtolower($str); },
    ];
}
```

### 2. Validation

Validate data against template schema before compilation:

```typescript
// Frontend validation
async function validateData() {
  const response = await axios.post('/api/templates/validate-data', {
    template_id: currentTemplate.value.id,
    data: templateData.value
  })
  
  if (!response.data.valid) {
    errors.value = response.data.errors
    return false
  }
  
  return true
}
```

### 3. Error Handling

Show helpful errors for:
- Handlebars syntax errors
- Missing required data fields
- Type mismatches
- JSON parsing errors

---

## Next Steps

1. **Review and Approve Plan**
2. **Start with Phase 1** (Backend Foundation)
3. **Create Backend Compiler Service**
4. **Add Compile API Endpoint**
5. **Test Compilation with Sample Templates**
6. **Move to Frontend Implementation**

---

## Estimated Timeline

- **Phase 1** (Backend): 1-2 days
- **Phase 2** (Frontend): 2-3 days
- **Phase 3** (Library): 1-2 days
- **Phase 4** (Enhanced): 1-2 days
- **Testing & Polish**: 1-2 days

**Total: 6-11 days** for full implementation

---

## Questions to Consider

1. Should templates be user-specific or shared organization-wide?
2. Do we need template permissions/roles?
3. Should we support template inheritance?
4. Do we need template preview with placeholder data?
5. Should we add a visual template builder (drag-and-drop)?

---

## Conclusion

This separation provides a **professional, scalable solution** for organizations that need to generate many similar OMR documents. It maintains backward compatibility while adding powerful new capabilities for template reuse and data management.
