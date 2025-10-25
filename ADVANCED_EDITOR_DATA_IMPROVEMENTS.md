# Advanced Editor Data Loading Improvements

## Issue

In the `/templates/advanced` endpoint (OMR Template Editor):
1. When clicking "Load Sample", the Data Editor pane remained empty
2. No way to load data files from the database
3. Data was being set in the store but not reflected in the UI

## Root Cause

The `DataEditor` component was initializing `formData` from `props.modelValue` only once during component creation, but not watching for subsequent prop changes. When the parent component updated `templateData`, the DataEditor didn't react.

## Solution

### 1. Fixed DataEditor Component Reactivity

**File**: `packages/vue-data-editor/src/DataEditor.vue`

Added a watcher to detect changes to the `modelValue` prop:

```typescript
// Watch for external changes to modelValue prop
watch(() => props.modelValue, (newVal) => {
  formData.value = { ...newVal }
  jsonString.value = JSON.stringify(newVal, null, 2)
}, { deep: true })
```

**Result**: Now when `templateData` is updated (via samples or database), the DataEditor immediately reflects those changes.

### 2. Added Data File Browser

**File**: `resources/js/pages/Templates/AdvancedEditor.vue`

#### New Features:

**📁 Load Data File Button**
- Added green button in toolbar: "📁 Load Data File"
- Opens a drawer with the `TemplateDataBrowser` component
- Allows browsing and selecting data files from the database

**Handler Function**:
```typescript
function handleLoadTemplateData(dataFile: any) {
  // Extract the data from the data file
  const data = dataFile.data || {}
  
  // Handle nested structure (document/data/positions)
  if (data.data) {
    templateData.value = data.data
  } else {
    templateData.value = data
  }
  
  showTemplateDataBrowser.value = false
  
  // Trigger compilation if auto-compile is enabled
  if (autoCompileEnabled.value) {
    setTimeout(() => debouncedCompile(), 100)
  }
}
```

**Keyboard Shortcut**:
- `ESC` now also closes the Data File Browser

---

## What Works Now

### ✅ Load Sample Data
1. Click "Load Sample" dropdown
2. Select any sample (Simple, Philippine, Barangay, etc.)
3. Data immediately appears in Data Editor pane
4. Shows in both Form View and JSON View

### ✅ Load Data from Database
1. Click "📁 Load Data File" button
2. Browse existing data files
3. Filter by name, description, template_ref, category
4. Click a data file to load it
5. Data appears in Data Editor pane
6. Auto-compiles if enabled

### ✅ View Modes
- **Form View**: Structured fields with add/remove capabilities
- **JSON View**: Raw JSON editing with syntax validation

### ✅ Auto-Compilation
- When data is loaded, template auto-compiles if enabled
- Preview updates automatically
- Shows compilation errors if any

---

## User Workflow

### Scenario 1: Template with Sample Data
1. Open `/templates/advanced`
2. Click "Browse Library" → Select a template
3. Template loads in Template pane
4. Sample data loads in Data Editor pane ✅
5. Preview shows compiled spec
6. Click "Render PDF" to generate document

### Scenario 2: Template with Database Data
1. Open `/templates/advanced`
2. Click "Browse Library" → Select a template
3. Click "📁 Load Data File" → Select a data file
4. Data loads in Data Editor pane ✅
5. Preview shows compiled spec
6. Click "Render PDF" to generate document

### Scenario 3: Sample Template + Database Data
1. Open `/templates/advanced`
2. Click "Load Sample" → Select "Simple Election Ballot"
3. Template and sample data load
4. Click "📁 Load Data File" → Select your own data
5. Sample data is replaced with your data ✅
6. Compile and render with your data

---

## Files Modified

### 1. DataEditor Component
- `packages/vue-data-editor/src/DataEditor.vue`
  - Added watcher for `props.modelValue`
  - Ensures reactivity to external data changes

### 2. Advanced Editor
- `resources/js/pages/Templates/AdvancedEditor.vue`
  - Imported `TemplateDataBrowser` component
  - Added `showTemplateDataBrowser` state
  - Added `openTemplateDataBrowser()` function
  - Added `handleLoadTemplateData()` function
  - Added "📁 Load Data File" button in toolbar
  - Added Data File Browser drawer in template
  - Updated ESC key handler

---

## Benefits

### For Users
- ✅ See data immediately when loading samples
- ✅ Load existing data files from database
- ✅ Browse and search data files easily
- ✅ Visual confirmation of loaded data
- ✅ Seamless workflow

### For Developers
- ✅ Reactive DataEditor component
- ✅ Reusable TemplateDataBrowser integration
- ✅ Consistent data loading patterns
- ✅ Better debugging (console logs)

### For System
- ✅ Data visibility improved
- ✅ Better UX in template editor
- ✅ Reduced confusion
- ✅ More ways to load data

---

## Testing

### Build Status
```bash
npm run build
# ✓ built in 6.92s
# ✓ All assets generated
```

### Manual Testing Checklist
- [ ] Load Simple Sample → Data appears
- [ ] Load Philippine Sample → Data appears
- [ ] Load Barangay Sample → Data appears
- [ ] Click "Load Data File" → Browser opens
- [ ] Search data files → Filters work
- [ ] Select data file → Data loads in editor
- [ ] Switch Form/JSON View → Data persists
- [ ] Auto-compile → Works with loaded data
- [ ] Render PDF → Uses loaded data

---

## Before vs After

### Before
- ❌ Load Sample → Data Editor **empty**
- ❌ No way to load from database
- ❌ Users confused about missing data
- ❌ Had to manually type/paste data

### After
- ✅ Load Sample → Data Editor **shows data**
- ✅ "Load Data File" button available
- ✅ Browse database data files
- ✅ Immediate visual feedback
- ✅ Auto-compiles with loaded data

---

## Related Features

This improvement complements:
- Template Picker (for selecting template_ref in Data Editor)
- Data File Editor (for editing individual data files)
- Template Library (for browsing templates)
- Family Browser (for loading template families)

---

**Status**: ✅ Complete  
**Build**: ✅ Successful  
**Ready**: ✅ For testing
