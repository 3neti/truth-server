# DataEditor Component

A comprehensive form-based JSON data editor with dual view modes (Form and JSON) for editing structured data.

## Features

### 🎨 Dual View Modes
- **Form View**: User-friendly form interface with type-specific inputs
- **JSON View**: Raw JSON editing with syntax highlighting and validation

### 🔧 Form View Capabilities
- **Type Detection**: Automatically detects and renders appropriate inputs for:
  - Strings (text input)
  - Numbers (number input)
  - Booleans (checkbox)
  - Objects (expandable/collapsible)
  - Arrays (expandable/collapsible with item management)
  - Null values
- **Nested Editing**: Deep nested objects and arrays with visual hierarchy
- **Add/Remove Fields**: Dynamic field management at any level
- **Type Selection**: Choose field type when adding new fields

### 📝 JSON View Features
- Real-time validation
- Format/Minify controls
- Adjustable font size (8-24px)
- Syntax error reporting

## Installation

```typescript
import { DataEditor } from '@/components/ui/data-editor'
```

## Usage

### Basic Example

```vue
<script setup lang="ts">
import { ref } from 'vue'
import { DataEditor } from '@/components/ui/data-editor'

const data = ref({
  title: '2025 General Election',
  total_precincts: 150,
  is_active: true,
  items: [
    { code: 'ITEM1', name: 'President' },
    { code: 'ITEM2', name: 'Vice President' }
  ]
})
</script>

<template>
  <DataEditor v-model="data" />
</template>
```

### With Height Constraint

```vue
<template>
  <div class="h-[600px]">
    <DataEditor v-model="data" />
  </div>
</template>
```

## Props

| Prop | Type | Required | Description |
|------|------|----------|-------------|
| `modelValue` | `Record<string, any>` | Yes | The data object to edit |

## Events

| Event | Payload | Description |
|-------|---------|-------------|
| `update:modelValue` | `Record<string, any>` | Emitted when data changes |

## Component Structure

```
data-editor/
├── DataEditor.vue          # Main component with view toggle
├── DataEditorField.vue     # Recursive field editor
├── index.ts                # Exports
└── README.md               # Documentation
```

## Key Behaviors

### Reactivity
- Changes in Form View instantly update JSON View
- Changes in JSON View update Form View (when valid)
- External changes to `v-model` sync both views

### Type Preservation
- Edits preserve the original data types
- Number inputs maintain numeric types
- Boolean values use checkbox controls
- Type is displayed next to field labels

### Validation
- JSON View validates in real-time
- Invalid JSON prevents switching to Form View
- Clear error messages for JSON syntax issues

### Nested Data
- Objects and arrays are collapsible/expandable
- Visual indentation shows hierarchy depth
- Add/remove operations at any nesting level

## Examples

### Election Data

```typescript
const electionData = ref({
  title: '2025 General Election',
  id: 'BAL-2025-001',
  date: '2025-11-04',
  total_precincts: 150,
  is_active: true,
  items: [
    { code: 'ITEM1', name: 'President', position: 1 },
    { code: 'ITEM2', name: 'Vice President', position: 2 }
  ],
  metadata: {
    created_by: 'admin',
    region: 'NCR',
    verified: false
  }
})
```

### Template Data

```typescript
const templateData = ref({
  title: 'Invoice Template',
  company: {
    name: 'Acme Corp',
    address: '123 Main St',
    contact: {
      email: 'info@acme.com',
      phone: '555-0100'
    }
  },
  items: []
})
```

## Use Cases

1. **Template Data Editor**: Edit JSON data for Handlebars templates
2. **Configuration Editor**: Modify app configuration objects
3. **API Payload Builder**: Construct complex API request payloads
4. **Settings Manager**: Edit nested application settings
5. **Form Data Preview**: View and edit form submission data

## Demo Page

A demo page is available at `resources/js/pages/DataEditorDemo.vue` to test the component with sample data.

## Dependencies

- Vue 3.5+
- Existing UI components:
  - Button
  - Input
  - Label
  - Checkbox
- Lucide Vue icons:
  - `Code`
  - `FormInput`
  - `Plus`
  - `Trash2`

## Tips

- Use Form View for complex nested structures
- Use JSON View for quick copy/paste operations
- The component handles deep nesting gracefully
- Add new fields using the form at the bottom of each section
- Font size persists only during the current session
