# Phase 1: Backend Foundation - COMPLETED ✅

## Overview

Phase 1 of the Template-Data Separation project has been successfully completed. This phase establishes the backend foundation for compiling Handlebars templates with JSON data to produce OMR template specifications.

---

## What Was Implemented

### 1. ✅ LightnCandy Installation Verified

- **Package**: `zordius/lightncandy` v1.2.6
- **Status**: Already installed and working
- **Location**: `/vendor/zordius/lightncandy`

### 2. ✅ HandlebarsCompiler Service

**File**: `packages/omr-template/src/Services/HandlebarsCompiler.php`

**Features**:
- `compile(string $template, array $data): array` - Compiles Handlebars template with data
- `validate(string $template): bool` - Validates Handlebars syntax
- `compileWithHelpers(...)` - Compiles with custom Handlebars helpers
- Built-in helpers: `eq`, `ne`, `gt`, `gte`, `lt`, `lte`, `json`, `uppercase`, `lowercase`, `capitalize`, `length`

**Usage Example**:
```php
$compiler = app(HandlebarsCompiler::class);

$template = '{
    "title": "{{election.title}}",
    "id": "{{election.id}}"
}';

$data = [
    'election' => [
        'title' => '2025 General Election',
        'id' => 'BAL-2025-001'
    ]
];

$spec = $compiler->compile($template, $data);
// Returns: ['title' => '2025 General Election', 'id' => 'BAL-2025-001']
```

### 3. ✅ Service Provider Registration

**File**: `packages/omr-template/src/OMRTemplateServiceProvider.php`

The `HandlebarsCompiler` is registered as a singleton and can be dependency-injected anywhere in the application.

### 4. ✅ Database Migrations

**Created Tables**:

#### `omr_templates` Table
```sql
- id (primary key)
- name (string)
- description (text, nullable)
- category (string) - ballot, survey, test, etc.
- handlebars_template (longtext)
- sample_data (json, nullable)
- schema (json, nullable) - JSON schema for validation
- is_public (boolean, default false)
- user_id (foreign key to users, nullable)
- timestamps
- indexes on (category, is_public) and user_id
```

#### `template_instances` Table
```sql
- id (primary key)
- template_id (foreign key to omr_templates)
- document_id (string, unique)
- data (json)
- compiled_spec (json)
- pdf_path (string, nullable)
- coords_path (string, nullable)
- timestamps
- indexes on template_id and document_id
```

### 5. ✅ Eloquent Models

**Created Models**:

#### `App\Models\OmrTemplate`
- Relationships: `user()`, `instances()`
- Scopes: `public()`, `category($category)`, `accessibleBy($userId)`
- Casts: `sample_data`, `schema`, `is_public`

#### `App\Models\TemplateInstance`
- Relationships: `template()`
- Scopes: `byDocumentId($documentId)`
- Casts: `data`, `compiled_spec`

### 6. ✅ API Endpoints

**File**: `app/Http/Controllers/TemplateController.php`

**New Endpoints**:

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/templates/compile` | Compile Handlebars template with data |
| GET | `/api/templates/library` | List templates (filtered by category) |
| GET | `/api/templates/library/{id}` | Get specific template |
| POST | `/api/templates/library` | Save new template |
| PUT | `/api/templates/library/{id}` | Update template |
| DELETE | `/api/templates/library/{id}` | Delete template |

### 7. ✅ API Routes

**File**: `routes/api.php`

All new endpoints registered under `/api/templates` prefix with proper naming.

### 8. ✅ Comprehensive Tests

#### Unit Tests (7 tests, 25 assertions)
**File**: `tests/Unit/HandlebarsCompilerTest.php`

Tests:
- ✅ Compiles simple handlebars template with data
- ✅ Compiles template with each loop
- ✅ Compiles OMR ballot template with positions and candidates
- ✅ Validates correct handlebars template
- ✅ Throws exception for invalid handlebars syntax
- ✅ Throws exception for template that generates invalid JSON
- ✅ Compiles with custom helpers

#### Feature Tests (8 tests, 27 assertions)
**File**: `tests/Feature/TemplateApiTest.php`

Tests:
- ✅ Can compile handlebars template with data
- ✅ Compile endpoint validates required fields
- ✅ Can list public templates
- ✅ Can get specific template by id
- ✅ Returns 404 for non-existent template
- ✅ Can save new template
- ✅ Save template validates required fields
- ✅ Can filter templates by category

**All tests passing**: ✅ 15 tests, 52 assertions

### 9. ✅ Model Factory

**File**: `database/factories/OmrTemplateFactory.php`

Factory for generating test data for `OmrTemplate` model with realistic fake data.

### 10. ✅ Sample Templates

**Created Sample Files**:

1. **Handlebars Template**: `packages/omr-template/resources/templates/ballot-template.hbs`
   - Reusable ballot structure with positions and candidates
   - Supports party metadata
   - Dynamic layout selection

2. **Sample Data**: `packages/omr-template/resources/templates/ballot-data.json`
   - Example election data (2025 General Election)
   - President, Vice President, and Senator positions
   - Multiple candidates with party affiliations

---

## Testing the Implementation

### Test the Compile Endpoint

```bash
curl -X POST http://truth.test/api/templates/compile \
  -H "Content-Type: application/json" \
  -d '{
    "template": "{\"title\": \"{{title}}\"}",
    "data": {"title": "Test Document"}
  }'
```

Expected response:
```json
{
  "success": true,
  "spec": {
    "title": "Test Document"
  }
}
```

### Test Saving a Template

```bash
curl -X POST http://truth.test/api/templates/library \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Simple Ballot",
    "category": "ballot",
    "description": "A simple ballot template",
    "handlebars_template": "{\"title\": \"{{title}}\"}",
    "sample_data": {"title": "Sample Ballot"},
    "is_public": true
  }'
```

### Run All Tests

```bash
# Unit tests
php artisan test --filter HandlebarsCompilerTest

# Feature tests
php artisan test --filter TemplateApiTest

# All tests
php artisan test
```

---

## Code Quality

- ✅ All code follows PSR-12 standards
- ✅ Comprehensive PHPDoc comments
- ✅ Type hints on all methods
- ✅ Proper exception handling
- ✅ Validation on all API endpoints
- ✅ Security: User authorization checks on update/delete
- ✅ Database indexes for performance

---

## Architecture Decisions

### 1. Separation of Concerns
- `HandlebarsEngine` handles raw template rendering
- `HandlebarsCompiler` adds JSON-specific compilation logic
- Controller handles HTTP layer
- Models handle data persistence

### 2. Reusability
- Templates can be saved and reused across multiple documents
- Public templates accessible to all users
- Private templates for specific users

### 3. Security
- Templates require user ownership for updates/deletes
- Optional user_id for anonymous templates
- API validation prevents invalid data

### 4. Extensibility
- Custom Handlebars helpers can be added
- Schema validation for data (prepared for Phase 4)
- Template versioning structure (ready for future)

---

## Performance Considerations

- Database indexes on frequently queried fields
- Singleton service instances
- JSON casting for array fields
- Efficient query scopes

---

## Next Steps: Phase 2 - Frontend Advanced Editor

With Phase 1 complete, we're ready to move to Phase 2:

1. Create `AdvancedEditor.vue` with 3-pane layout
2. Create component for Handlebars template editor
3. Create component for JSON data editor
4. Create preview pane component
5. Update Pinia store with new state
6. Add mode toggle (Simple ↔ Advanced)
7. Implement real-time compilation
8. Add syntax highlighting

**Estimated Time**: 2-3 days

---

## Summary

Phase 1 successfully establishes a robust backend foundation for:
- ✅ Compiling Handlebars templates with JSON data
- ✅ Storing and managing template library
- ✅ Creating template instances with compiled specs
- ✅ Full CRUD operations via REST API
- ✅ Comprehensive test coverage
- ✅ Sample templates and data

**Status**: ✅ READY FOR PHASE 2
