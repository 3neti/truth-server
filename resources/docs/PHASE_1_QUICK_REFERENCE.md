# Phase 1: Quick Reference Guide

## 🎯 What Phase 1 Delivers

**Backend foundation for separating Handlebars templates from JSON data in OMR documents.**

---

## 📁 Key Files Created

### Backend Services
```
packages/omr-template/src/Services/
└── HandlebarsCompiler.php          # Main compiler service
```

### Models
```
app/Models/
├── Template.php                 # Template storage model
└── TemplateInstance.php            # Compiled instance model
```

### Migrations
```
database/migrations/
├── 2025_10_23_150641_create_templates_table.php
└── 2025_10_23_150647_create_template_instances_table.php
```

### Controllers & Routes
```
app/Http/Controllers/
└── TemplateController.php          # Updated with new endpoints

routes/
└── api.php                         # New API routes added
```

### Tests
```
tests/
├── Unit/HandlebarsCompilerTest.php     # 7 unit tests
├── Feature/TemplateApiTest.php         # 8 feature tests
└── database/factories/TemplateFactory.php
```

### Sample Templates
```
packages/omr-template/resources/templates/
├── ballot-template.hbs             # Sample Handlebars template
└── ballot-data.json                # Sample JSON data
```

---

## 🔧 API Endpoints

### Compile Template
```http
POST /api/templates/compile
Content-Type: application/json

{
  "template": "{ \"title\": \"{{title}}\" }",
  "data": { "title": "My Document" }
}
```

### Template Library (CRUD)
```http
GET    /api/templates/library           # List templates
GET    /api/templates/library/{id}      # Get template
POST   /api/templates/library           # Create template
PUT    /api/templates/library/{id}      # Update template
DELETE /api/templates/library/{id}      # Delete template
```

---

## 💻 Usage Examples

### Basic Compilation

```php
use LBHurtado\OMRTemplate\Services\HandlebarsCompiler;

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
```

### Validation

```php
try {
    $isValid = $compiler->validate($template);
} catch (\Exception $e) {
    echo "Validation error: " . $e->getMessage();
}
```

### With Custom Helpers

```php
$template = '{ "title": "{{uppercase title}}" }';
$data = ['title' => 'test'];

$spec = $compiler->compileWithHelpers($template, $data);
// Result: ["title" => "TEST"]
```

---

## 🗄️ Database Schema

### templates
```php
Template::create([
    'name' => 'General Election Ballot',
    'description' => 'Standard ballot template',
    'category' => 'ballot',
    'handlebars_template' => '...',
    'sample_data' => [...],
    'is_public' => true,
    'user_id' => auth()->id(),
]);
```

### Query Examples
```php
// Get all public templates
$templates = Template::public()->get();

// Get ballot templates
$ballots = Template::category('ballot')->get();

// Get user's accessible templates
$templates = Template::accessibleBy(auth()->id())->get();
```

---

## 🧪 Testing

### Run All Phase 1 Tests
```bash
php artisan test --filter "HandlebarsCompilerTest|TemplateApiTest"
```

### Run Specific Test Suite
```bash
# Unit tests only
php artisan test --filter HandlebarsCompilerTest

# Feature tests only
php artisan test --filter TemplateApiTest
```

### Test Results
- ✅ 15 tests
- ✅ 52 assertions
- ✅ 100% pass rate

---

## 🔑 Built-in Handlebars Helpers

| Helper | Example | Result |
|--------|---------|--------|
| `eq` | `{{#if (eq status "active")}}` | Equality check |
| `ne` | `{{#if (ne count 0)}}` | Not equal |
| `gt` | `{{#if (gt count 5)}}` | Greater than |
| `gte` | `{{#if (gte count 5)}}` | Greater than or equal |
| `lt` | `{{#if (lt count 10)}}` | Less than |
| `lte` | `{{#if (lte count 10)}}` | Less than or equal |
| `json` | `{{json data}}` | JSON encode |
| `uppercase` | `{{uppercase text}}` | Convert to uppercase |
| `lowercase` | `{{lowercase text}}` | Convert to lowercase |
| `capitalize` | `{{capitalize text}}` | Capitalize first letter |
| `length` | `{{length items}}` | Array length |

---

## 📊 Data Flow

```
┌─────────────────┐
│ Handlebars      │
│ Template (.hbs) │
└────────┬────────┘
         │
         ├──────┐
         │      │
         ▼      ▼
┌─────────────────┐     ┌──────────────┐
│ JSON Data       │────▶│ Compiler     │
└─────────────────┘     └──────┬───────┘
                               │
                               ▼
                        ┌──────────────┐
                        │ JSON Spec    │
                        └──────┬───────┘
                               │
                               ▼
                        ┌──────────────┐
                        │ PDF + Coords │
                        └──────────────┘
```

---

## 🔐 Security Features

- ✅ User ownership verification on updates/deletes
- ✅ Public/private template access control
- ✅ Input validation on all endpoints
- ✅ Exception handling with safe error messages
- ✅ SQL injection protection (Eloquent)
- ✅ XSS protection (JSON validation)

---

## 📈 Performance Features

- ✅ Database indexes on queried fields
- ✅ Singleton service registration
- ✅ JSON casting for array fields
- ✅ Query scopes for efficient filtering

---

## 🚀 Next Phase Preview

**Phase 2: Frontend Advanced Editor**

Will implement:
- 3-pane editor layout (Template | Data | Preview)
- Real-time compilation
- Syntax highlighting
- Mode toggle (Simple ↔ Advanced)
- Template library browser
- Error handling UI

---

## 🐛 Troubleshooting

### Compiler Not Found
```php
// Make sure service is registered
php artisan clear-compiled
php artisan config:clear
```

### Template Compilation Error
```php
// Check Handlebars syntax
try {
    $compiler->validate($template);
} catch (\Exception $e) {
    // Fix syntax error in template
}
```

### Database Error
```bash
# Run migrations
php artisan migrate

# Fresh database
php artisan migrate:fresh
```

---

## 📚 Resources

- **LightnCandy Docs**: https://github.com/zordius/lightncandy
- **Handlebars Syntax**: https://handlebarsjs.com/
- **Phase 1 Plan**: `/resources/docs/TEMPLATE_DATA_SEPARATION_PLAN.md`
- **Completion Report**: `/resources/docs/PHASE_1_COMPLETION.md`

---

## ✅ Checklist for Phase 1

- [x] HandlebarsCompiler service created
- [x] Service registered in provider
- [x] Database migrations run
- [x] Models created with relationships
- [x] API endpoints implemented
- [x] Routes registered
- [x] Unit tests written (7 tests)
- [x] Feature tests written (8 tests)
- [x] All tests passing
- [x] Factory created
- [x] Sample templates created
- [x] Documentation complete

**Status**: ✅ COMPLETE - Ready for Phase 2
