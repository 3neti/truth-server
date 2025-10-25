# TODO - Future Enhancements

## Phase 5: Multiple Rendering Engines (Deferred)
- [ ] Support multiple template engines (Handlebars, Twig, Blade, Mustache)
- [ ] Templates can specify which renderer to use
- [ ] Renderer plugins/drivers architecture

## Unused Database Tables (Prepared but not implemented)

### `template_versions` 
**Purpose**: Store version history of templates for rollback/audit
**Status**: Table exists, model exists, relationships defined, but NOT used in UI
**Future Use Cases**:
- Track changes to templates over time
- Rollback to previous template versions
- Audit trail of who changed what
- Version comparison/diff view

**Files**:
- Model: `app/Models/TemplateVersion.php`
- Migration: Find via `grep -r "template_versions" database/migrations/`
- Used in: `app/Models/Template.php` (lines 74-77, 133-198)

**Methods in Template**:
- `versions()` - Get all versions
- `createVersion($changelog, $userId)` - Create version snapshot
- `incrementVersion($type)` - Bump version number
- `rollbackToVersion($versionId)` - Restore from version
- `getVersionHistory()` - Get version list

**API Endpoints** (exists but unused):
- `GET /api/templates/library/{id}/versions` - Get version history
- `POST /api/templates/library/{templateId}/rollback/{versionId}` - Rollback

**Decision**: 
- [ ] **Option 1**: Keep for future use (no action needed)
- [ ] **Option 2**: Remove unused code to simplify codebase
- [ ] **Option 3**: Implement version UI in Template Editor

---

### `template_instances`
**Purpose**: Track usage of templates (when template is used to generate documents)
**Status**: Table exists, model exists, relationships defined, but NOT used
**Future Use Cases**:
- Track which templates are most used
- Analytics: "This template was used 150 times this month"
- Link templates to generated PDFs
- Usage statistics and reporting

**Files**:
- Model: `app/Models/TemplateInstance.php`
- Migration: Find via `grep -r "template_instances" database/migrations/`
- Used in: `app/Models/Template.php` (lines 66-69)

**Method in Template**:
- `instances()` - Get all instances created from template

**Decision**:
- [ ] **Option 1**: Keep for future analytics
- [ ] **Option 2**: Remove if analytics not needed
- [ ] **Option 3**: Implement instance tracking when PDF is rendered

---

## Current Working Tables

✅ **templates** - Template storage with Handlebars
✅ **template_families** - Group templates by family/variants
✅ **template_data** - Data storage with template references

---

## Other Future Enhancements

### Template Features
- [ ] Template preview thumbnails
- [ ] Template ratings/favorites
- [ ] Template usage statistics
- [ ] Template dependencies (one template includes another)
- [ ] Template marketplace/sharing
- [ ] Import/export template families as packages

### Data File Features
- [ ] Data file versioning
- [ ] Batch operations (import/export multiple files)
- [ ] Data file locking (prevent concurrent edits)
- [ ] Data file diff/comparison
- [ ] CSV import to data files
- [ ] Data file templates (default structure)

### Validation
- [ ] Field-level validation rules
- [ ] Custom validation rules per template
- [ ] Validation warnings vs errors
- [ ] Suggest fixes for validation errors

### UI/UX
- [ ] Dark mode
- [ ] Template editor syntax highlighting (CodeMirror/Monaco)
- [ ] Split-pane resizing
- [ ] Undo/redo in editors
- [ ] Recent files/templates
- [ ] Keyboard navigation
- [ ] Mobile responsive design

### Performance
- [ ] Template caching
- [ ] Lazy loading in browsers
- [ ] Pagination for large lists
- [ ] Background PDF rendering
- [ ] Queue system for batch operations

### Security
- [ ] Role-based permissions (admin, editor, viewer)
- [ ] Template approval workflow
- [ ] Audit logs
- [ ] Template signing/verification (partially implemented)
- [ ] Rate limiting for API

### Integration
- [ ] GitHub sync for templates
- [ ] Webhook notifications
- [ ] REST API documentation (OpenAPI/Swagger)
- [ ] Export to different formats (CSV, Excel, XML)
- [ ] Import from other OMR systems

---

## Cleanup Recommendations

### Option A: Keep Everything (Recommended)
**Pros**: Ready for future use, no rework needed later
**Cons**: Small unused code/tables in codebase
**Action**: Document in TODO, move on

### Option B: Comment Out Unused Features
**Pros**: Clean codebase, easy to restore later
**Cons**: Need to uncomment/test when needed
**Action**: Comment out version/instance methods, keep models/tables

### Option C: Remove Completely
**Pros**: Cleanest codebase
**Cons**: Need to recreate migrations/models later, might break something
**Action**: Drop tables, delete models, remove relationships
**Risk**: ⚠️ High - might affect existing code

---

## Recommendation: Option A

Keep the version and instance infrastructure as-is. It's:
- Not causing any issues
- Well-designed and ready to use
- Minimal overhead (empty tables)
- Easy to implement UI for when needed
- Common pattern in production apps

Focus energy on new features instead of removing working code.

---

**Last Updated**: 2025-10-25  
**Phase**: Post-Phase 4
