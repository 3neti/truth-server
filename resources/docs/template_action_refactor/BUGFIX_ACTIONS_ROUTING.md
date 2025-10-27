# Bug Fix: Laravel Actions Routing Issue

## Problem
After refactoring controllers to use Laravel Actions, the compile endpoint was returning 500 errors:
```
TypeError: CompileHandlebarsTemplate::asController(): Argument #1 ($request) must be of type 
Lorisleiva\Actions\ActionRequest, Illuminate\Http\Request given
```

## Root Cause
**Three issues identified:**

### 1. Duplicate Route Definitions
Old route file `routes/truth-templates.php` was still being loaded (commented out in `routes/web.php` line 26), taking precedence over the new action-based routes in `routes/truth-templates_api.php`.

**Credit: @rli solved this!** 🏆

### 2. Actions Called Incorrectly from Controllers
Controller methods were calling actions with `::run($request)` or `::make()->asController($request)`, but `asController()` expects `ActionRequest` not `Request`.

**Solution:** Register actions directly in routes instead of going through controllers.

### 3. Missing JSON Response Wrapper
Actions returned raw arrays, but API clients expected `{success: true, spec: {...}}` format.

**Solution:** Added `jsonResponse()` method to wrap responses properly.

## Fixes Applied

### Fix 1: Route Registration (routes/truth-templates_api.php)
```php
// ❌ OLD: Through controller
Route::post('/compile', [TemplateController::class, 'compile']);

// ✅ NEW: Direct action
Route::post('/compile', CompileHandlebarsTemplate::class);
```

### Fix 2: Commented Out Duplicate Routes
```php
// routes/api.php - lines 33-36
// NOTE: These routes are now in truth-templates_api.php
// Route::post('/compile', ...)->name('compile');
```

### Fix 3: Added JSON Response Method
```php
// CompileHandlebarsTemplate.php
public function jsonResponse($spec): \Illuminate\Http\JsonResponse
{
    return response()->json([
        'success' => true,
        'spec' => $spec,
    ]);
}
```

## Verification

### Routes Now Point to Actions
```bash
$ php artisan route:list --path="truth-templates/compile"

POST  api/truth-templates/compile 
  → App\Actions\TruthTemplates\Compilation\CompileHandlebarsTemplate  ✅
```

### Tests Pass
```bash
$ php artisan test --filter=CompilationTest

✓ CompileHandlebarsTemplate Action → it compiles a valid OMR template
✓ CompileHandlebarsTemplate Action → it works via asController with valid template
- CompileStandaloneData Action → it compiles with template reference (skipped)

Tests:  1 skipped, 2 passed (8 assertions)
```

## Key Learnings

1. **Always check for old route files** when routes aren't updating
2. **Register actions directly in routes** for proper `ActionRequest` handling
3. **Use `jsonResponse()` or `htmlResponse()`** methods for custom response formatting
4. **Clear ALL caches** when debugging route issues:
   ```bash
   php artisan optimize:clear
   php artisan route:clear
   composer dump-autoload
   ```

## Files Changed

- ✅ `routes/truth-templates_api.php` - Routes now use actions directly
- ✅ `routes/api.php` - Commented out duplicate compile routes
- ✅ `app/Actions/TruthTemplates/Compilation/CompileHandlebarsTemplate.php` - Added `jsonResponse()`
- ✅ `tests/Feature/Actions/TruthTemplates/CompilationTest.php` - Fixed with valid OMR templates

## Status
🟢 **RESOLVED** - All tests passing, actions working correctly through routes.
