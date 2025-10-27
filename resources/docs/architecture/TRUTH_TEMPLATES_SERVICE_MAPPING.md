# Truth Templates Service Action Mapping

This document maps the conceptual service actions described in `TRUTH_TEMPLATES_ARCHITECTURE.md` to their actual implementation in the codebase.

## Implementation Overview

The Truth Templates system is implemented as a Laravel application with the following structure:

- **Controllers**: Handle HTTP requests and responses (located in `app/Http/Controllers/`)
- **Models**: Eloquent models for database entities (located in `app/Models/`)
- **Frontend Store**: Pinia store for state management (located in `resources/js/TruthTemplatesUi/stores/`)
- **API Routes**: Defined in `routes/truth-templates_api.php`
- **Web Routes**: Defined in `routes/truth-templates_web.php`

---

## 1. Template Management Service

### Actions and Implementation

| Action | Implementation | Location | HTTP Method & Route |
|--------|---------------|----------|---------------------|
| `createTemplate()` | `TemplateController@saveTemplate()` | `app/Http/Controllers/TemplateController.php:360` | `POST /api/truth-templates/templates` |
| `updateTemplate()` | `TemplateController@updateTemplate()` | `app/Http/Controllers/TemplateController.php:410` | `PUT /api/truth-templates/templates/{id}` |
| `deleteTemplate()` | `TemplateController@deleteTemplate()` | `app/Http/Controllers/TemplateController.php:486` | `DELETE /api/truth-templates/templates/{id}` |
| `compileTemplate()` | `TemplateController@compile()` | `app/Http/Controllers/TemplateController.php:195` | `POST /api/truth-templates/compile` |
| `validateTemplate()` | `TemplateController@validate()` | `app/Http/Controllers/TemplateController.php:66` | `POST /api/truth-templates/validate` |

#### Frontend Store Methods
| Store Method | Location |
|--------------|----------|
| `saveTemplateToLibrary()` | `resources/js/TruthTemplatesUi/stores/templates.ts:233` |
| `updateTemplateInLibrary()` | `resources/js/TruthTemplatesUi/stores/templates.ts:258` |
| `loadTemplateFromLibrary()` | `resources/js/TruthTemplatesUi/stores/templates.ts:283` |
| `compileTemplate()` | `resources/js/TruthTemplatesUi/stores/templates.ts:211` |
| `validateTemplate()` | `resources/js/TruthTemplatesUi/stores/templates.ts:96` |

#### Key Models
- **Template**: `app/Models/Template.php`
  - Handles versioning, signing, and validation
  - Methods: `createVersion()`, `sign()`, `verifyChecksum()`, `validateData()`

#### Additional Endpoints
- **Compile Standalone**: `POST /api/truth-templates/compile-standalone` - Compiles portable data files with `template_ref`
- **List Templates**: `GET /api/truth-templates/templates` - Browse all accessible templates
- **Get Template**: `GET /api/truth-templates/templates/{id}` - Retrieve specific template
- **Version History**: `GET /api/truth-templates/templates/{id}/versions` - Get template version history
- **Rollback**: `POST /api/truth-templates/templates/{id}/rollback/{versionId}` - Rollback to previous version
- **Sign Template**: `POST /api/truth-templates/templates/{id}/sign` - Generate SHA256 checksum
- **Verify Template**: `GET /api/truth-templates/templates/{id}/verify` - Verify template integrity

---

## 2. Family Management Service

### Actions and Implementation

| Action | Implementation | Location | HTTP Method & Route |
|--------|---------------|----------|---------------------|
| `createFamily()` | `TemplateFamilyController@store()` | `app/Http/Controllers/Api/TemplateFamilyController.php:52` | `POST /api/truth-templates/families` |
| `deleteFamily()` | `TemplateFamilyController@destroy()` | `app/Http/Controllers/Api/TemplateFamilyController.php:145` | `DELETE /api/truth-templates/families/{id}` |
| `exportFamily()` | `TemplateFamilyController@export()` | `app/Http/Controllers/Api/TemplateFamilyController.php:181` | `GET /api/truth-templates/families/{id}/export` |
| `importFamily()` | `TemplateFamilyController@import()` | `app/Http/Controllers/Api/TemplateFamilyController.php:222` | `POST /api/truth-templates/families/import` |
| `getFamilyVariants()` | `TemplateFamilyController@variants()` | `app/Http/Controllers/Api/TemplateFamilyController.php:163` | `GET /api/truth-templates/families/{id}/templates` |

#### Frontend Store Methods
| Store Method | Location |
|--------------|----------|
| `createTemplateFamily()` | `resources/js/TruthTemplatesUi/stores/templates.ts:351` |
| `deleteTemplateFamily()` | `resources/js/TruthTemplatesUi/stores/templates.ts:373` |
| `exportTemplateFamily()` | `resources/js/TruthTemplatesUi/stores/templates.ts:388` |
| `importTemplateFamily()` | `resources/js/TruthTemplatesUi/stores/templates.ts:398` |
| `getTemplateFamilies()` | `resources/js/TruthTemplatesUi/stores/templates.ts:316` |
| `getTemplateFamily()` | `resources/js/TruthTemplatesUi/stores/templates.ts:331` |
| `getFamilyVariants()` | `resources/js/TruthTemplatesUi/stores/templates.ts:341` |

#### Key Models
- **TemplateFamily**: `app/Models/TemplateFamily.php`
  - Manages template family relationships
  - Methods: `templates()`, `layoutVariants()`, `accessibleBy()`

#### Additional Endpoints
- **List Families**: `GET /api/truth-templates/families` - Browse all accessible families
- **Get Family**: `GET /api/truth-templates/families/{id}` - Retrieve specific family
- **Update Family**: `PUT /api/truth-templates/families/{id}` - Update family metadata

---

## 3. Data Management Service

### Actions and Implementation

| Action | Implementation | Location | HTTP Method & Route |
|--------|---------------|----------|---------------------|
| `createData()` | `TemplateDataController@store()` | `app/Http/Controllers/Api/TemplateDataController.php:55` | `POST /api/template-data` |
| `updateData()` | `TemplateDataController@update()` | `app/Http/Controllers/Api/TemplateDataController.php:89` | `PUT /api/template-data/{dataFile}` |
| `deleteData()` | `TemplateDataController@destroy()` | `app/Http/Controllers/Api/TemplateDataController.php:113` | `DELETE /api/template-data/{dataFile}` |
| `validateData()` | `DataValidationController@validate()` | `app/Http/Controllers/Api/DataValidationController.php` | `POST /api/template-data/{id}/validate` |
| `loadTemplateFromRef()` | Frontend implementation | `resources/js/TruthTemplatesUi/pages/DataEditor.vue:256` | N/A (client-side) |

#### Frontend Store Methods
| Store Method | Location |
|--------------|----------|
| Template Data Store | `resources/js/TruthTemplatesUi/stores/templateData.ts` |

#### Key Models
- **TemplateData**: `app/Models/TemplateData.php`
  - Stores JSON data payloads with `template_ref` links

#### Additional Endpoints
- **List Data Files**: `GET /api/template-data` - Browse all accessible data files
- **Get Data File**: `GET /api/template-data/{id}` - Retrieve specific data file

---

## 4. Rendering Service

### Actions and Implementation

| Action | Implementation | Location | HTTP Method & Route |
|--------|---------------|----------|---------------------|
| `renderPDF()` | `TemplateController@render()` | `app/Http/Controllers/TemplateController.php:20` | `POST /api/truth-templates/render` |
| `generateCoordinates()` | Part of `render()` | `app/Http/Controllers/TemplateController.php:40` | N/A (bundled with render) |
| `downloadPDF()` | `TemplateController@download()` | `app/Http/Controllers/TemplateController.php:152` | `GET /api/templates/download/{documentId}` |
| `previewSpec()` | Frontend component | `resources/js/TruthTemplatesUi/components/PreviewPane.vue` | N/A (client-side) |

#### Frontend Store Methods
| Store Method | Location |
|--------------|----------|
| `renderTemplate()` | `resources/js/TruthTemplatesUi/stores/templates.ts:73` |

#### Key Services
- **SmartLayoutRenderer**: `packages/omr-template/src/Engine/SmartLayoutRenderer.php`
  - Generates PDFs with TCPDF
  - Extracts CV coordinates
- **HandlebarsCompiler**: `packages/omr-template/src/Services/HandlebarsCompiler.php`
  - Compiles Handlebars templates with data

#### Additional Endpoints
- **Get Coordinates**: `GET /api/templates/coords/{documentId}` - Retrieve coordinate JSON for CV scanning

---

## 5. Storage Service

### Actions and Implementation

| Action | Implementation | Location | HTTP Method & Route |
|--------|---------------|----------|---------------------|
| `saveToLibrary()` | Multiple controllers | Templates: `TemplateController@saveTemplate()`<br>Data: `TemplateDataController@store()` | Various |
| `loadFromLibrary()` | Multiple controllers | Templates: `TemplateController@getTemplate()`<br>Data: `TemplateDataController@show()` | Various |
| `searchTemplates()` | `TemplateController@listTemplates()` | `app/Http/Controllers/TemplateController.php:302` | `GET /api/truth-templates/templates?search=...` |
| `listFamilies()` | `TemplateFamilyController@index()` | `app/Http/Controllers/Api/TemplateFamilyController.php:18` | `GET /api/truth-templates/families` |

#### Frontend Store Methods
| Store Method | Location |
|--------------|----------|
| `getTemplateLibrary()` | `resources/js/TruthTemplatesUi/stores/templates.ts:304` |
| `getTemplateFamilies()` | `resources/js/TruthTemplatesUi/stores/templates.ts:316` |
| `saveToLocalStorage()` | `resources/js/TruthTemplatesUi/stores/templates.ts:426` |
| `loadFromLocalStorage()` | `resources/js/TruthTemplatesUi/stores/templates.ts:437` |

#### Database Tables
- `templates` - Stores Handlebars templates
- `template_families` - Groups related template variants
- `template_data` - Stores JSON data payloads
- `template_instances` - Version history snapshots

---

## Supporting Services

### Sample Data Service
| Action | Implementation | Location | Route |
|--------|---------------|----------|-------|
| Get sample templates | `TemplateController@samples()` | `app/Http/Controllers/TemplateController.php:127` | `GET /api/truth-templates/samples` |
| Get layout presets | `TemplateController@layouts()` | `app/Http/Controllers/TemplateController.php:115` | `GET /api/truth-templates/layouts` |

### Template Resolution Service
| Component | Location | Purpose |
|-----------|----------|---------|
| TemplateResolver | `app/Services/Templates/TemplateResolver.php` | Resolves `template_ref` strings to actual templates |

---

## Frontend Components

### Pages
| Component | Location | Purpose |
|-----------|----------|---------|
| AdvancedEditor | `resources/js/TruthTemplatesUi/pages/AdvancedEditor.vue` | Edit Handlebars templates with live preview |
| DataEditor | `resources/js/TruthTemplatesUi/pages/DataEditor.vue` | Edit JSON data files with template preview |
| Editor (Simple) | `resources/js/TruthTemplatesUi/pages/Editor.vue` | Simple visual template editor |
| Index | `resources/js/TruthTemplatesUi/pages/Index.vue` | Landing page for Truth Templates |

### Components
| Component | Location | Purpose |
|-----------|----------|---------|
| TemplateLibrary | `resources/js/TruthTemplatesUi/components/TemplateLibrary.vue` | Browse and select templates |
| FamilyBrowser | `resources/js/TruthTemplatesUi/components/FamilyBrowser.vue` | Browse template families |
| PreviewPane | `resources/js/TruthTemplatesUi/components/PreviewPane.vue` | Preview compiled OMR specs and PDFs |
| TemplateDataBrowser | `resources/js/TruthTemplatesUi/components/TemplateDataBrowser.vue` | Browse and select data files |
| TemplatePicker | `resources/js/TruthTemplatesUi/components/TemplatePicker.vue` | Select template via family/variant |
| DataEditor | `resources/js/TruthTemplatesUi/components/DataEditor.vue` | JSON editor with form/code views |

---

## API Route Prefix

All template-related API routes are prefixed with `/api/truth-templates/` and defined in:
- `routes/truth-templates_api.php`

All template-related web routes are prefixed with `/truth-templates/` and defined in:
- `routes/truth-templates_web.php`

---

## Data Flow Example: Creating and Rendering a Ballot

```
1. USER: Opens Advanced Editor
   → Web Route: GET /truth-templates/advanced
   → Component: AdvancedEditor.vue

2. USER: Writes Handlebars template, provides sample data
   → Frontend Store: templates.updateHandlebarsTemplate()
   → Frontend Store: templates.updateTemplateData()

3. USER: Clicks "Compile"
   → Frontend Store: templates.compileTemplate()
   → API: POST /api/truth-templates/compile
   → Controller: TemplateController@compile()
   → Service: HandlebarsCompiler->compile()
   → Response: OMR Specification JSON

4. USER: Clicks "Save to Library"
   → Frontend Store: templates.saveTemplateToLibrary()
   → API: POST /api/truth-templates/templates
   → Controller: TemplateController@saveTemplate()
   → Model: Template::create()
   → Database: Insert into templates table

5. USER: Opens Data Editor
   → Web Route: GET /truth-templates/data/editor
   → Component: DataEditor.vue

6. USER: Creates election data with template_ref
   → Frontend Store: templateData.save()
   → API: POST /api/template-data
   → Controller: TemplateDataController@store()
   → Model: TemplateData::create()
   → Database: Insert into template_data table

7. USER: Opens data file (auto-loads template)
   → Frontend: loadTemplateFromRef() in DataEditor
   → Frontend Store: templates.getTemplateFamilies()
   → Frontend Store: templates.getFamilyVariants()
   → Auto-loads matching template variant

8. USER: Clicks "Show Preview"
   → Frontend Store: templates.compileTemplate()
   → API: POST /api/truth-templates/compile
   → PreviewPane shows compiled OMR Spec

9. USER: Clicks "Render PDF"
   → Frontend Store: templates.renderTemplate()
   → API: POST /api/truth-templates/render
   → Controller: TemplateController@render()
   → Service: SmartLayoutRenderer->render()
   → Output: PDF file + coordinates JSON
   → Response: URLs for download

10. USER: Downloads PDF
    → API: GET /api/templates/download/{documentId}
    → Controller: TemplateController@download()
    → Response: PDF file stream

11. BALLOT PRINTED → Webcam scans → CV processing → Election returns
```

---

## Summary

The Truth Templates system is a full-stack Laravel + Vue.js application where:

- **Backend Controllers** handle HTTP requests and business logic
- **Eloquent Models** manage database persistence and relationships
- **Frontend Pinia Store** manages client-side state and API calls
- **Vue Components** provide the user interface
- **Template Resolution** bridges portable `template_ref` strings to actual templates
- **Rendering Pipeline** compiles templates + data → OMR specs → PDFs with coordinates

All service actions described in the architecture document map to concrete implementations in these components.
