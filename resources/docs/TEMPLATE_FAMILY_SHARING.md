# Template Family Sharing Guide

## Overview

The OMR Template Registry & Composition System (OTRCS) supports exporting and importing template families as JSON files. This enables sharing template families between Truth instances, team collaboration, and template distribution.

## Export/Import Architecture

### Export Format

Template families are exported as JSON files with the following structure:

```json
{
  "family": {
    "slug": "ballot-2025-generic",
    "name": "Generic Ballot 2025",
    "description": "A standard ballot template with multiple layout options",
    "category": "ballot",
    "version": "1.0.0",
    "is_public": false,
    "metadata": {
      "author": "Organization Name",
      "license": "MIT",
      "tags": ["ballot", "election", "standard"]
    }
  },
  "templates": [
    {
      "layout_variant": "single-column",
      "name": "Generic Ballot 2025 (single-column)",
      "template_data": { ... },
      "dimensions": { "width": 8.5, "height": 11, "unit": "in" },
      "is_public": false
    },
    {
      "layout_variant": "two-column",
      "name": "Generic Ballot 2025 (two-column)",
      "template_data": { ... },
      "dimensions": { "width": 8.5, "height": 11, "unit": "in" },
      "is_public": false
    }
  ],
  "manifest_version": "1.0",
  "exported_at": "2025-10-24T10:00:00Z"
}
```

### Key Properties

- **family**: Core family metadata including slug, name, description, category, and version
- **templates**: Array of template variants with layout_variant identifier, template data, and dimensions
- **manifest_version**: Schema version for compatibility checking
- **exported_at**: Export timestamp for reference

## User Workflows

### Exporting a Template Family

1. **Open Template Families Browser**
   - In the Advanced Template Editor, click the "Template Families" button in the toolbar
   - The FamilyBrowser modal will open

2. **Locate the Family**
   - Browse or search for the family you want to export
   - Use category filters to narrow results

3. **Export**
   - Click the "📤 Export" button on the family card
   - A JSON file named `{family-slug}-family.json` will be downloaded

4. **Share**
   - Share the JSON file via email, cloud storage, or version control
   - The file contains all variants and metadata needed for import

### Importing a Template Family

1. **Open Template Families Browser**
   - In the Advanced Template Editor, click "Template Families"

2. **Import**
   - Click the "⬆️ Import" button in the header
   - Select the family JSON file from your system

3. **Import Behavior**
   - If a family with the same slug exists, a unique suffix is added (e.g., `ballot-2025-generic-imported-1`)
   - All templates are imported as **private** by default for security
   - The family and its variants are immediately available

4. **Verify**
   - The imported family appears in the browser
   - You can load and test variants immediately

## Use Cases

### Team Collaboration
- Export standard families and share with team members
- Import common templates across development/staging/production environments

### Template Distribution
- Organizations can distribute official templates as JSON files
- Users can import verified templates without manual recreation

### Backup & Versioning
- Export families before major changes for backup
- Store family JSON files in git alongside code for version control

### Migration & Testing
- Export from production and import into staging for testing
- Migrate templates between Truth instances

## Technical Details

### API Endpoints

**Export:**
```
GET /api/template-families/{id}/export
Response: JSON family manifest
```

**Import:**
```
POST /api/template-families/import
Body: JSON family manifest
Response: Created family with ID and slug
```

### Security Considerations

- **All imported templates default to private** (`is_public: false`)
- The importing user becomes the owner
- Family slugs are guaranteed unique through automatic suffixing
- Template IDs are regenerated on import to avoid conflicts

### Slug Uniqueness

If a family with the same slug already exists:
- The system appends `-imported-{n}` to ensure uniqueness
- Example: `ballot-2025` → `ballot-2025-imported-1`
- This prevents accidental overwrites

## Best Practices

1. **Version Your Families**: Use semantic versioning in family metadata
2. **Document Changes**: Include meaningful descriptions when exporting
3. **Test After Import**: Always verify imported templates load correctly
4. **Control Access**: Keep sensitive templates private, only share what's necessary
5. **Backup Regularly**: Export important families periodically for disaster recovery

## Future Enhancements

Potential future features:
- Batch import/export of multiple families
- Direct GitHub integration for template repositories
- Template marketplace with browsing and installation
- Dependency management for template composition
- Digital signatures for template verification

## Related Documentation

- [Template Registry and Composition System](./TEMPLATE_REGISTRY_AND_COMPOSITION_SYSTEM.md)
- [Integration Roadmap](./INTEGRATION_ROADMAP.md)
- Phase 1: Template Families Foundation (completed)
- Phase 2: UI Integration (completed)
- Phase 3: Template Versioning (completed)
- Phase 4: Export/Import Sharing (current)
