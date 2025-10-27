# Standardize TemplateController to Laravel Resource Conventions

**Goal**: Align TemplateController method names with Laravel's RESTful resource controller conventions

---

## Current vs Standard Mapping

### Core CRUD Methods

| Current Method | → | Standard Method | HTTP | Route Pattern |
|----------------|---|-----------------|------|---------------|
| `listTemplates()` | → | **`index()`** | GET | `/templates` |
| `getTemplate()` | → | **`show()`** | GET | `/templates/{id}` |
| `saveTemplate()` | → | **`store()`** | POST | `/templates` |
| `updateTemplate()` | → | **`update()`** | PUT/PATCH | `/templates/{id}` |
| `deleteTemplate()` | → | **`destroy()`** | DELETE | `/templates/{id}` |

### Additional Methods (Non-RESTful)

These don't map to standard resource methods but are domain-specific:

| Current Method | Status | Note |
|----------------|--------|------|
| `getVersionHistory()` | ✅ Keep as-is | Custom route: `GET /templates/{id}/versions` |
| `rollbackToVersion()` | ✅ Keep as-is | Custom route: `POST /templates/{id}/rollback/{versionId}` |
| `validateData()` | ✅ Keep as-is | Custom route: `POST /templates/{id}/validate-data` |
| `signTemplate()` | ✅ Keep as-is | Custom route: `POST /templates/{id}/sign` |
| `verifyTemplate()` | ✅ Keep as-is | Custom route: `GET /templates/{id}/verify` |

### Not Needed (Web Forms - API Only)

| Standard Method | Status | Reason |
|----------------|--------|---------|
| `create()` | ❌ Skip | API-only controller, no web forms |
| `edit()` | ❌ Skip | API-only controller, no web forms |

---

## Refactoring Plan

### Phase 1: Rename Methods

**Simple renames** (no logic changes):

```php
listTemplates()   → index()
getTemplate()     → show()
saveTemplate()    → store()
updateTemplate()  → update()
deleteTemplate()  → destroy()
```

### Phase 2: Update Routes

Update `routes/truth-templates_api.php`:

**Current**:
```php
Route::get('/templates', [TemplateController::class, 'listTemplates'])
Route::post('/templates', [TemplateController::class, 'saveTemplate'])
Route::get('/templates/{id}', [TemplateController::class, 'getTemplate'])
Route::put('/templates/{id}', [TemplateController::class, 'updateTemplate'])
Route::delete('/templates/{id}', [TemplateController::class, 'deleteTemplate'])
```

**New (Option A - Explicit)**:
```php
Route::get('/templates', [TemplateController::class, 'index'])
Route::post('/templates', [TemplateController::class, 'store'])
Route::get('/templates/{id}', [TemplateController::class, 'show'])
Route::put('/templates/{id}', [TemplateController::class, 'update'])
Route::delete('/templates/{id}', [TemplateController::class, 'destroy'])
```

**New (Option B - Resource Route - RECOMMENDED)**:
```php
Route::apiResource('templates', TemplateController::class);

// Then add custom routes
Route::get('/templates/{id}/versions', [TemplateController::class, 'getVersionHistory'])
Route::post('/templates/{id}/rollback/{versionId}', [TemplateController::class, 'rollbackToVersion'])
Route::post('/templates/{id}/validate-data', [TemplateController::class, 'validateData'])
Route::post('/templates/{id}/sign', [TemplateController::class, 'signTemplate'])
Route::get('/templates/{id}/verify', [TemplateController::class, 'verifyTemplate'])
```

**Option B Benefits**:
- ✅ Automatically generates standard RESTful routes
- ✅ Follows Laravel conventions
- ✅ Less code
- ✅ Clearer intent

### Phase 3: Update Controller Docblocks

Update method docblocks to match Laravel's standard resource comments:

```php
/**
 * Display a listing of the templates.
 */
public function index(Request $request): JsonResponse

/**
 * Store a newly created template in storage.
 */
public function store(Request $request): JsonResponse

/**
 * Display the specified template.
 */
public function show(string $id): JsonResponse

/**
 * Update the specified template in storage.
 */
public function update(Request $request, string $id): JsonResponse

/**
 * Remove the specified template from storage.
 */
public function destroy(string $id): JsonResponse
```

### Phase 4: Verify Tests

No test changes needed - tests use HTTP endpoints, not controller method names directly.

---

## Execution Steps

### Step 1: Rename Controller Methods ✏️

```bash
# In TemplateController.php:
listTemplates()   → index()
getTemplate()     → show()
saveTemplate()    → store()
updateTemplate()  → update()
deleteTemplate()  → destroy()
```

### Step 2: Update Routes 🛣️

```bash
# In routes/truth-templates_api.php:
# Replace individual routes with apiResource
Route::apiResource('templates', TemplateController::class);
```

### Step 3: Update Docblocks 📝

```bash
# Update all 5 CRUD method docblocks to standard Laravel format
```

### Step 4: Clean Up TempController 🗑️

```bash
# Remove the temporary TempController.php
rm app/Http/Controllers/TempController.php
```

### Step 5: Verify 🧪

```bash
# Clear caches
php artisan route:clear
php artisan optimize:clear

# Verify routes
php artisan route:list --name=truth-templates.templates

# Run tests
php artisan test tests/Feature/Actions/TruthTemplates/
```

---

## Impact Analysis

### Low Risk ✅

**Why**:
- Method names are internal implementation details
- HTTP endpoints remain the same (`/api/truth-templates/templates`)
- Test coverage exists
- No breaking changes to API consumers
- Standard Laravel conventions = easier for new developers

### Files Modified

1. `app/Http/Controllers/TemplateController.php` - Rename 5 methods
2. `routes/truth-templates_api.php` - Use `apiResource()` helper
3. `app/Http/Controllers/TempController.php` - Delete (temporary reference file)

### Files NOT Modified

- ❌ No test files (they use HTTP endpoints)
- ❌ No action files
- ❌ No frontend code
- ❌ No configuration

---

## Before vs After

### Before (Non-Standard)

```php
class TemplateController extends Controller
{
    public function listTemplates(Request $request): JsonResponse { }
    public function getTemplate(string $id): JsonResponse { }
    public function saveTemplate(Request $request): JsonResponse { }
    public function updateTemplate(Request $request, string $id): JsonResponse { }
    public function deleteTemplate(Request $request, string $id): JsonResponse { }
    
    // Custom methods (unchanged)
    public function getVersionHistory(...) { }
    public function rollbackToVersion(...) { }
    public function validateData(...) { }
    public function signTemplate(...) { }
    public function verifyTemplate(...) { }
}
```

### After (Laravel Standard)

```php
class TemplateController extends Controller
{
    // Standard RESTful resource methods
    public function index(Request $request): JsonResponse { }
    public function show(string $id): JsonResponse { }
    public function store(Request $request): JsonResponse { }
    public function update(Request $request, string $id): JsonResponse { }
    public function destroy(string $id): JsonResponse { }
    
    // Custom methods (unchanged)
    public function getVersionHistory(...) { }
    public function rollbackToVersion(...) { }
    public function validateData(...) { }
    public function signTemplate(...) { }
    public function verifyTemplate(...) { }
}
```

### Routes Before

```php
Route::prefix('truth-templates')->group(function () {
    Route::get('/templates', [TemplateController::class, 'listTemplates']);
    Route::post('/templates', [TemplateController::class, 'saveTemplate']);
    Route::get('/templates/{id}', [TemplateController::class, 'getTemplate']);
    Route::put('/templates/{id}', [TemplateController::class, 'updateTemplate']);
    Route::delete('/templates/{id}', [TemplateController::class, 'deleteTemplate']);
    
    // Custom routes...
});
```

### Routes After (Using apiResource)

```php
Route::prefix('truth-templates')->group(function () {
    // Generates all 5 standard routes automatically
    Route::apiResource('templates', TemplateController::class);
    
    // Custom routes
    Route::get('/templates/{id}/versions', [TemplateController::class, 'getVersionHistory']);
    Route::post('/templates/{id}/rollback/{versionId}', [TemplateController::class, 'rollbackToVersion']);
    Route::post('/templates/{id}/validate-data', [TemplateController::class, 'validateData']);
    Route::post('/templates/{id}/sign', [TemplateController::class, 'signTemplate']);
    Route::get('/templates/{id}/verify', [TemplateController::class, 'verifyTemplate']);
});
```

---

## Benefits

### Developer Experience

✅ **Familiarity**: Every Laravel developer knows resource controller methods  
✅ **Consistency**: Matches all other Laravel resource controllers  
✅ **Less Code**: `apiResource()` replaces 5 explicit route definitions  
✅ **Documentation**: Standard Laravel docs apply  
✅ **IDE Support**: Better autocomplete and method hints

### Maintenance

✅ **Onboarding**: New developers understand immediately  
✅ **Best Practice**: Follows Laravel's conventions  
✅ **Future-Proof**: Aligns with Laravel ecosystem  
✅ **Package Ready**: Standard naming when extracting to package

---

## Optional: Add FormRequest Classes

For even cleaner code, consider extracting validation:

```php
// app/Http/Requests/StoreTemplateRequest.php
class StoreTemplateRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            // ... etc
        ];
    }
}

// Then in controller:
public function store(StoreTemplateRequest $request): JsonResponse
{
    $template = Template::create($request->validated());
    // ...
}
```

**This is optional** - can be done later as a separate cleanup task.

---

## Recommendation

**✅ PROCEED with standardization**

This is a low-risk, high-value refactoring that:
- Improves code quality
- Follows Laravel best practices  
- Makes the codebase more maintainable
- Aligns with your package extraction goals

**Estimated Time**: ~15 minutes

---

**Ready to execute? Let me know and I'll implement the changes!**
