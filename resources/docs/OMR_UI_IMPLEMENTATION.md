# OMR Templates Visual UI - Implementation Guide

## Overview

The OMR Templates Visual UI provides a browser-based interface for designing, editing, and previewing OMR documents (ballots, surveys, forms) that are generated using the `lbhurtado/omr-templates` package.

## Architecture

```
┌─────────────────────┐
│   Vue 3 Frontend    │
│  (Template Editor)  │
└──────────┬──────────┘
           │ HTTP/JSON
           ▼
┌─────────────────────┐
│  Laravel Backend    │
│  TemplateController │
└──────────┬──────────┘
           │
           ▼
┌─────────────────────┐
│ omr-templates pkg   │
│ SmartLayoutRenderer │
└─────────────────────┘
```

## Components

### Backend (Laravel)

#### TemplateController (`app/Http/Controllers/TemplateController.php`)
Provides API endpoints for template operations:

**Endpoints:**
- `POST /api/templates/render` - Render PDF from JSON spec
- `POST /api/templates/validate` - Validate JSON spec
- `GET /api/templates/layouts` - Get layout presets
- `GET /api/templates/samples` - Get sample templates
- `GET /api/templates/download/{id}` - Download PDF
- `GET /api/templates/coords/{id}` - Get coordinates JSON

### Frontend (Vue 3)

#### Pinia Store (`resources/js/stores/templates.ts`)
Manages template state and operations:
- Template specification (document + sections)
- PDF/coordinates URLs
- Loading/error states
- Render, validate, import/export actions
- LocalStorage persistence

#### Editor Page (`resources/js/pages/Templates/Editor.vue`)
Main UI for template editing:
- JSON text editor
- PDF preview iframe
- Toolbar (New, Import, Export, Validate, Render)
- Sample template loader
- Document information display
- Auto-save to localStorage

## Features

### ✅ Implemented

1. **JSON Template Editing**
   - Live JSON editing in textarea
   - Syntax highlighting (basic)
   - Real-time validation

2. **PDF Rendering**
   - Render PDF from JSON spec
   - Inline PDF preview
   - Download generated PDF

3. **Template Management**
   - Import JSON from file
   - Export JSON to file
   - Load sample templates
   - Clear/reset template

4. **Validation**
   - Client-side validation
   - Server-side validation
   - Error display

5. **Auto-Save**
   - LocalStorage persistence
   - Auto-restore on page load
   - Debounced save (1 second)

6. **Document Info**
   - Display document metadata
   - Section count
   - Layout information

### 🚧 Future Enhancements

1. **Visual Section Builder**
   - Drag-and-drop section editor
   - Choice editor UI
   - Layout preview

2. **Monaco Editor Integration**
   - Full-featured JSON editor
   - IntelliSense
   - Error highlighting
   - Auto-formatting

3. **PDF.js Integration**
   - Advanced PDF viewer
   - Zoom controls
   - Page navigation

4. **Coordinates Overlay**
   - Visualize OMR bubble positions
   - Fiducial markers overlay
   - Hover info for elements

5. **Template Library**
   - Save templates to database
   - Template versioning
   - Share templates

## Usage

### Starting the Editor

1. Navigate to `/templates/editor` in your browser
2. The editor will load with an empty template or restore from localStorage

### Creating a Template

1. Click "New" to start fresh
2. Edit the JSON in the left panel
3. Click "Validate" to check for errors
4. Click "Render PDF" to generate

### Loading a Sample

1. Use the "Load Sample..." dropdown
2. Select "Sample Ballot" or "Sample Survey"
3. The JSON will update automatically

### Importing/Exporting

**Import:**
1. Click "Import"
2. Select a `.json` file
3. The template will load

**Export:**
1. Click "Export"
2. A `.json` file will download

### Viewing the PDF

1. After rendering, the PDF preview appears on the right
2. Click "Download PDF" to save
3. Click "Close Preview" to hide

## API Examples

### Render Template

```bash
curl -X POST http://localhost/api/templates/render \
  -H "Content-Type: application/json" \
  -d @sample-ballot.json
```

Response:
```json
{
  "success": true,
  "document_id": "BAL-2025-0000123",
  "pdf_url": "http://localhost/api/templates/download/BAL-2025-0000123",
  "coords_url": "http://localhost/api/templates/coords/BAL-2025-0000123"
}
```

### Validate Template

```bash
curl -X POST http://localhost/api/templates/validate \
  -H "Content-Type: application/json" \
  -d '{"spec": {...}}'
```

Response:
```json
{
  "valid": true,
  "message": "Template specification is valid"
}
```

### Get Samples

```bash
curl http://localhost/api/templates/samples
```

Response:
```json
{
  "samples": [
    {
      "name": "sample-ballot",
      "filename": "sample-ballot.json",
      "spec": {...}
    }
  ]
}
```

## JSON Specification Format

### Document

```json
{
  "document": {
    "title": "Sample Ballot",
    "unique_id": "BAL-2025-0000123",
    "layout": "2-col",
    "locale": "en-PH"
  }
}
```

### Multiple Choice Section

```json
{
  "type": "multiple_choice",
  "code": "PRESIDENT",
  "title": "President of the Philippines",
  "maxSelections": 1,
  "layout": "2-col",
  "choices": [
    { "code": "P-A1", "label": "Candidate A" },
    { "code": "P-A2", "label": "Candidate B" }
  ]
}
```

### Rating Scale Section

```json
{
  "type": "rating_scale",
  "code": "SAT-EXP",
  "title": "Rate your experience",
  "scale": [1, 2, 3, 4, 5],
  "question": "Overall satisfaction"
}
```

## Development

### Adding the Editor Route

Add to `routes/web.php`:

```php
Route::get('/templates/editor', function () {
    return Inertia::render('Templates/Editor');
})->name('templates.editor');
```

### Building Frontend

```bash
npm run dev    # Development with hot reload
npm run build  # Production build
```

### Testing the API

```bash
# Test render endpoint
php artisan test --filter TemplateControllerTest

# Manual test
php /path/to/test-smart-layout.php
```

## Troubleshooting

### PDF Not Rendering

1. Check `storage/omr-output` permissions
2. Verify TCPDF is installed: `composer show elibyy/tcpdf-laravel`
3. Check Laravel logs: `tail -f storage/logs/laravel.log`

### JSON Import Fails

1. Verify JSON syntax is valid
2. Check required fields exist:
   - `document.title`
   - `document.unique_id`
   - `sections` (array with at least one section)

### LocalStorage Not Working

1. Check browser console for errors
2. Clear localStorage: `localStorage.removeItem('omr-template')`
3. Ensure browser allows localStorage

## File Structure

```
truth/
├─ app/Http/Controllers/
│  └─ TemplateController.php          # API controller
├─ routes/
│  ├─ api.php                          # API routes
│  └─ web.php                          # Web routes (add editor route)
├─ resources/js/
│  ├─ stores/
│  │  └─ templates.ts                  # Pinia store
│  └─ pages/Templates/
│     └─ Editor.vue                    # Main editor page
└─ packages/omr-template/
   └─ resources/samples/
      ├─ sample-ballot.json
      └─ sample-survey.json
```

## Next Steps

1. **Enhance JSON Editor**: Integrate Monaco Editor for better editing experience
2. **Visual Builder**: Create drag-and-drop section builder
3. **PDF Viewer**: Integrate PDF.js for advanced viewing
4. **Coordinates Overlay**: Visualize OMR element positions
5. **Template Library**: Save/load templates from database
6. **OpenCV Integration**: Show appreciation results overlay

## Credits

Built with:
- **Vue 3** - Frontend framework
- **Pinia** - State management
- **Laravel** - Backend framework
- **lbhurtado/omr-templates** - PDF generation engine
- **Tailwind CSS** - Styling
