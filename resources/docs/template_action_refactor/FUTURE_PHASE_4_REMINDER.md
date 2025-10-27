# Phase 4 Migration Reminder

**Status**: Deferred - Not currently needed  
**Date Deferred**: 2025-01-27

---

## When to Implement Phase 4: Template CRUD Actions

You should proceed with Phase 4 migration **ONLY IF** you need to build:

### ❌ Template Marketplace or Library
- Public template catalog for users to browse
- Template discovery and search functionality
- Featured/popular templates
- Template categories and tagging

### ❌ Template Authoring Tool with Versioning
- Web-based template editor/creator
- Save/edit Handlebars templates in database
- Automatic version snapshots on changes
- Rollback to previous versions
- Version comparison/diff viewing
- Changelog tracking

### ❌ Multi-user Template Management with Permissions
- User-owned templates (private templates)
- Public vs private template visibility
- Shared team templates
- Template ownership transfer
- Access control lists (ACLs)
- Role-based permissions (admin, author, viewer)

### ❌ Template Audit Trails and Compliance Features
- Template integrity verification (signing/checksums)
- Tamper detection
- Audit logs of all template changes
- Compliance reporting (who changed what, when)
- Legal/regulatory requirements for template provenance
- Digital signatures for template certification

---

## Current System (Phases 1-3 Complete)

**What Works Now**: ✅
- ✅ Template compilation with data
- ✅ Spec validation
- ✅ PDF rendering with coordinates
- ✅ File downloads
- ✅ Layout presets
- ✅ Sample templates

**Templates Managed Via**:
- Filesystem storage (`packages/omr-template/resources/samples/`)
- Git version control
- Code/config-based selection
- No database required

---

## Phase 4 Actions (Deferred)

### 10 Actions Not Yet Migrated:

1. **ListTemplates** - Browse template library
2. **GetTemplate** - Get single template by ID
3. **CreateTemplate** - Save new template to database
4. **UpdateTemplate** - Edit template with auto-versioning
5. **DeleteTemplate** - Remove template from database
6. **GetTemplateVersionHistory** - View revision history
7. **RollbackTemplateVersion** - Restore previous version
8. **ValidateTemplateData** - Validate against JSON schema
9. **SignTemplate** - Generate SHA256 checksum
10. **VerifyTemplate** - Verify integrity/checksum

### Controller Methods Not Yet Migrated:
All methods in `TemplateController.php` after line 168:
- `listTemplates()` (line 173)
- `getTemplate()` (line 328)
- `saveTemplate()` (line 348)
- `updateTemplate()` (line 398)
- `deleteTemplate()` (line 474)
- `getVersionHistory()` (line 511)
- `rollbackToVersion()` (line 533)
- `validateData()` (line 578)
- `signTemplate()` (line 614)
- `verifyTemplate()` (line 653)

---

## Decision Checklist

Before implementing Phase 4, ask yourself:

- [ ] Do users need to create/edit templates via web UI?
- [ ] Do templates need database storage (not filesystem)?
- [ ] Do multiple users need to own different templates?
- [ ] Is template versioning/rollback required?
- [ ] Do we need audit trails of template changes?
- [ ] Are there compliance requirements for template integrity?
- [ ] Is template signing/verification necessary?

**If all answers are NO** → Stay with current filesystem-based approach  
**If any answers are YES** → Consider implementing Phase 4

---

## Migration Effort Estimate

**Time**: ~2-3 hours  
**Complexity**: Medium-High  
**Dependencies**: 
- Template model (already exists)
- User authentication (already exists)
- Database migrations (may need updates)

**Testing**: ~1 hour  
**Documentation**: ~30 minutes

---

## Related Files

**Documentation**:
- `resources/docs/ACTION_MIGRATION_LOG.md` - Complete migration log
- `resources/docs/PHASE_3_COMPLETE.md` - Last completed phase
- This file - Future Phase 4 reminder

**Code to Migrate** (when needed):
- `app/Http/Controllers/TemplateController.php` (lines 168-700+)
- Route definitions in `routes/truth-templates_api.php`

**Stub Actions** (already scaffolded):
- `app/Actions/TruthTemplates/Templates/` (non-core actions)
- These will be removed in cleanup but can be referenced from git history

---

## Package Extraction Note

When extracting truth-templates to a package:
- ✅ Include Phases 1-3 (core processing)
- ❌ Exclude Phase 4 (optional CRUD) unless specifically needed
- Keep this reminder document in the package documentation

---

**Last Updated**: 2025-01-27  
**Next Review**: When building template management UI or receiving user requirements for the features listed above
