# Truth Templates Route Rationalization

## Overview
This document describes the route rationalization completed for the Truth Templates system. All template-related routes have been unified under a `/truth-templates` prefix with consistent naming conventions.

## Changes Made

### 1. New Unified Routes File
**File:** `routes/truth-templates.php`

All truth-templates routes (both web and API) are now defined in a single, well-organized file:
- **Web routes** under `/truth-templates` prefix
- **API routes** under `/api/truth-templates` prefix
- All routes properly named with consistent conventions

### 2. Web Routes (User-facing pages)

| Old Route | New Route | Route Name |
|-----------|-----------|------------|
| `/templates/editor` | `/truth-templates/editor` | `truth-templates.editor` |
| `/templates/advanced` | `/truth-templates/advanced` | `truth-templates.advanced` |
| `/data/editor` | `/truth-templates/data/editor` | `truth-templates.data.editor` |
| `/data-editor-demo` | `/truth-templates/data/demo` | `truth-templates.data.demo` |
| N/A | `/truth-templates/` | `truth-templates.index` |

**Backwards Compatibility:** Old routes redirect to new routes.

### 3. API Routes

All API routes moved from multiple prefixes to unified `/api/truth-templates`:

#### Template Operations
- `POST /api/truth-templates/render` → `api.truth-templates.render`
- `POST /api/truth-templates/validate` → `api.truth-templates.validate`
- `POST /api/truth-templates/compile` → `api.truth-templates.compile`
- `POST /api/truth-templates/compile-standalone` → `api.truth-templates.compile-standalone`

#### Template Library CRUD
- `GET /api/truth-templates/templates` → `api.truth-templates.templates.index`
- `POST /api/truth-templates/templates` → `api.truth-templates.templates.store`
- `GET /api/truth-templates/templates/{id}` → `api.truth-templates.templates.show`
- `PUT /api/truth-templates/templates/{id}` → `api.truth-templates.templates.update`
- `DELETE /api/truth-templates/templates/{id}` → `api.truth-templates.templates.destroy`

#### Template Families
- `GET /api/truth-templates/families` → `api.truth-templates.families.index`
- `POST /api/truth-templates/families` → `api.truth-templates.families.store`
- `GET /api/truth-templates/families/{id}` → `api.truth-templates.families.show`
- `PUT /api/truth-templates/families/{id}` → `api.truth-templates.families.update`
- `DELETE /api/truth-templates/families/{id}` → `api.truth-templates.families.destroy`
- `GET /api/truth-templates/families/{id}/templates` → `api.truth-templates.families.templates`
- `GET /api/truth-templates/families/{id}/export` → `api.truth-templates.families.export`
- `POST /api/truth-templates/families/import` → `api.truth-templates.families.import`

#### Template Data
- `GET /api/truth-templates/data` → `api.truth-templates.data.index`
- `POST /api/truth-templates/data` → `api.truth-templates.data.store`
- `GET /api/truth-templates/data/{dataFile}` → `api.truth-templates.data.show`
- `PUT /api/truth-templates/data/{dataFile}` → `api.truth-templates.data.update`
- `DELETE /api/truth-templates/data/{dataFile}` → `api.truth-templates.data.destroy`
- `POST /api/truth-templates/data/{dataFile}/validate` → `api.truth-templates.data.validate-file`

**Backwards Compatibility:** Old API routes (e.g., `/api/templates/*`, `/api/template-families/*`) remain functional but are marked as deprecated.

### 4. Vue Component Updates

Updated all Vue components to use Laravel's `route()` helper instead of hardcoded paths:

**Files Updated:**
- `resources/js/stores/templates.ts` - All API calls now use route names
- `resources/js/Pages/Templates/Components/TemplatePane.vue` - Template loading
- `resources/js/Pages/Templates/Components/TemplateLibrary.vue` - Template CRUD operations
- `resources/js/Pages/Templates/AdvancedEditor.vue` - Navigation links

**Example Change:**
```typescript
// Before
await axios.get('/api/templates/library')

// After
await axios.get(route('api.truth-templates.templates.index'))
```

### 5. Route Registration

**web.php:**
- Includes `routes/truth-templates.php`
- Old routes redirect to new routes for backwards compatibility

**api.php:**
- Legacy routes remain with deprecation notice
- Comments point to `routes/truth-templates.php` as canonical source

## Benefits

✅ **Single source of truth** - All template routes in one file  
✅ **Consistent naming** - All routes follow `truth-templates.*` pattern  
✅ **Type-safe** - Vue components use Ziggy route names  
✅ **Discoverable** - Easy to find all template endpoints  
✅ **Backwards compatible** - Old routes still work  
✅ **RESTful** - Follows Laravel conventions (index, store, show, update, destroy)  

## Testing

All routes verified working:
```bash
# View all truth-templates routes
php artisan route:list --name=truth-templates

# Build frontend with new routes
npm run build
```

38 routes registered successfully under the `truth-templates` namespace.

## Migration Notes

### For Frontend Development
- Use `route('api.truth-templates.*')` instead of hardcoded paths
- All template-related pages now under `/truth-templates/`
- Ziggy provides autocomplete for route names in TypeScript

### For API Consumers
- Update to new `/api/truth-templates/*` endpoints
- Old endpoints will continue to work but are deprecated
- New endpoints follow RESTful conventions more strictly

## Future Improvements

- [ ] Move controllers to `App\Http\Controllers\TruthTemplates` namespace (optional)
- [ ] Add route middleware for API rate limiting
- [ ] Create API documentation using route annotations
- [ ] Add route versioning (v1, v2) if needed
- [ ] Remove legacy route aliases after migration period

## Route Name Quick Reference

### Web Routes
```php
route('truth-templates.index')         // Dashboard
route('truth-templates.editor')        // Simple editor
route('truth-templates.advanced')      // Advanced editor
route('truth-templates.data.editor')   // Data editor
route('truth-templates.data.demo')     // Data demo
```

### API Routes
```php
route('api.truth-templates.render')                    // Render PDF
route('api.truth-templates.compile')                   // Compile template
route('api.truth-templates.templates.index')           // List templates
route('api.truth-templates.templates.store')           // Create template
route('api.truth-templates.families.index')            // List families
route('api.truth-templates.data.index')                // List data files
```
