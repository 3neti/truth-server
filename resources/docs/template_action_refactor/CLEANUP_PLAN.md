# Controller and Routes Cleanup Plan

## What to Remove

### 1. TemplateController - Remove Deprecated Methods (Lines 19-170)

These methods are **orphaned** - routes now point directly to Actions:

**Lines to DELETE**:
```
render() (19-29)         → Route uses RenderTemplateSpec::class
validate() (31-79)       → Route uses ValidateTemplateSpec::class  
layouts() (81-92)        → Route uses GetLayoutPresets::class
samples() (94-105)       → Route uses GetSampleTemplates::class
download() (107-125)     → Route uses DownloadRenderedPdf::class
coords() (127-145)       → Route uses GetCoordinatesMap::class
compile() (147-157)      → Route uses CompileHandlebarsTemplate::class
compileStandalone() (159-170) → Route uses CompileStandaloneData::class
```

**Lines to KEEP**:
```
listTemplates() (172+)   → Still used by /api/truth-templates/templates routes
getTemplate()
saveTemplate()
updateTemplate()
deleteTemplate()
getVersionHistory()
rollbackToVersion()
validateData()
signTemplate()
verifyTemplate()
extractDataPayload() (private helper)
```

**Result**: TemplateController will only contain Phase 4 methods (template CRUD)

---

### 2. routes/api.php - Remove Duplicate Legacy Routes

**Lines to DELETE**: 24-53 (entire `/templates` prefix block)

These are **duplicate** routes that conflict with `truth-templates_api.php`:

```php
Route::prefix('templates')->name('templates.')->group(function () {
    Route::post('/render', ...)           // Duplicate of /api/truth-templates/render
    Route::post('/validate', ...)         // Duplicate of /api/truth-templates/validate
    Route::get('/layouts', ...)           // Duplicate of /api/truth-templates/layouts
    Route::get('/samples', ...)           // Duplicate of /api/truth-templates/samples
    Route::get('/download/{id}', ...)     // Duplicate of /api/truth-templates/download/{id}
    Route::get('/coords/{id}', ...)       // Duplicate of /api/truth-templates/coords/{id}
    
    // Template library routes (also duplicates)
    Route::get('/library', ...)           // Duplicate of /api/truth-templates/templates
    Route::post('/library', ...)
    Route::get('/library/{id}', ...)
    ... (all library routes)
});
```

**Lines to KEEP**: 55-81 (Template Families, Template Data, Data Validation routes)

These are **NOT duplicates** - they provide alternate prefixes:
- `/api/template-families/*` (also in `/api/truth-templates/families/*`)
- `/api/template-data/*` (also in `/api/truth-templates/data/*`)
- `/api/data/validate` (also in `/api/truth-templates/validate-data`)

**Decision**: Keep these for now as they may be used by external clients

---

### 3. routes/web.php - NO CHANGES NEEDED

All routes are web UI routes, no cleanup needed.

---

## Impact Analysis

### Before Cleanup:

**TemplateController**: 18 methods (700+ lines)
- 8 deprecated proxy methods (orphaned)
- 10 active template CRUD methods
- 1 private helper

**routes/api.php**: 86 lines
- 30 lines of duplicate legacy routes
- 28 lines of families/data routes (not duplicates but alternate prefixes)

### After Cleanup:

**TemplateController**: 11 methods (~520 lines)
- 0 deprecated proxy methods ✅
- 10 active template CRUD methods
- 1 private helper

**routes/api.php**: ~58 lines
- 0 duplicate routes ✅
- 28 lines of families/data routes (kept)

---

## Execution Steps

1. **Backup files** (via git)
2. **Remove deprecated methods** from TemplateController (lines 19-170)
3. **Remove duplicate routes** from routes/api.php (lines 24-53)
4. **Clean up imports** in TemplateController (remove unused)
5. **Verify tests** still pass
6. **Test endpoints** manually

---

## Risk Assessment

**LOW RISK** ✅

**Why**:
- All orphaned controller methods already have routes pointing to Actions
- Removing duplicate routes eliminates confusion
- Template CRUD routes (Phase 4) remain untouched
- Families/Data controllers untouched
- All tests pass before cleanup
- Changes are purely cleanup, no logic changes

**Rollback**: Simple `git revert` if needed
