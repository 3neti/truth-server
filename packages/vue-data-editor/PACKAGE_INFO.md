# @lbhurtado/vue-data-editor

Official Vue Data Editor package for the Truth project.

## Package Structure

```
packages/vue-data-editor/
├── dist/                      # Built package (generated)
│   ├── index.es.js           # ES module build (16.98 kB)
│   ├── index.umd.js          # UMD build (13.67 kB)
│   └── vue-data-editor.css   # Styles (0.13 kB)
├── src/                       # Source files
│   ├── DataEditor.vue        # Main editor component
│   ├── DataEditorField.vue   # Recursive field editor
│   └── index.ts              # Package exports
├── package.json              # Package configuration
├── vite.config.ts            # Build configuration
└── README.md                 # Documentation
```

## What's Different from the Original

The package version uses **native HTML elements** instead of UI library components:

### Original (UI Library Dependent)
- Used `Button` from `@/components/ui/button`
- Used `Input` from `@/components/ui/input`
- Used `Label` from `@/components/ui/label`
- Used `Checkbox` from `@/components/ui/checkbox`

### Package (Standalone)
- Uses native `<button>` elements with Tailwind classes
- Uses native `<input>` elements with Tailwind classes
- Uses native `<label>` elements with Tailwind classes
- Uses native checkbox `<input type="checkbox">`

## Dependencies

### Peer Dependencies
- `vue`: ^3.5.0
- `lucide-vue-next`: ^0.468.0 (for icons only)

### Dev Dependencies
- `@vitejs/plugin-vue`: ^6.0.0
- `typescript`: ^5.2.2
- `vite`: ^7.0.4
- `vue-tsc`: ^2.2.4

## Installation

### As Local Package (Current Setup)
```json
{
  "dependencies": {
    "@lbhurtado/vue-data-editor": "file:packages/vue-data-editor"
  }
}
```

### As NPM Package (Future)
```bash
npm install @lbhurtado/vue-data-editor
```

## Usage

### Basic Import
```vue
<script setup lang="ts">
import { ref } from 'vue'
import { DataEditor } from '@lbhurtado/vue-data-editor'

const data = ref({
  title: 'My Data',
  count: 42,
  active: true
})
</script>

<template>
  <DataEditor v-model="data" />
</template>
```

### Global Registration
```typescript
import { createApp } from 'vue'
import VueDataEditor from '@lbhurtado/vue-data-editor'

const app = createApp(App)
app.use(VueDataEditor)
```

## Build Commands

```bash
# Install dependencies
npm install

# Build the package
npm run build

# Type check
npm run type-check

# Development mode
npm run dev
```

## Exports

The package exports:
- `DataEditor` - Main component
- `DataEditorField` - Field editor component (for advanced use)
- `install` - Vue plugin install function
- Default export with `install` method

## Integration in Truth Project

### Files Updated

1. **package.json**
   - Added: `"@lbhurtado/vue-data-editor": "file:packages/vue-data-editor"`

2. **DataEditorDemo.vue**
   ```typescript
   // Changed from:
   import { DataEditor } from '@/components/ui/data-editor'
   // To:
   import { DataEditor } from '@lbhurtado/vue-data-editor'
   ```

3. **DataPaneNew.vue**
   ```typescript
   // Changed from:
   import { DataEditor } from '@/components/ui/data-editor'
   // To:
   import { DataEditor } from '@lbhurtado/vue-data-editor'
   ```

## Build Output

```
dist/vue-data-editor.css   0.13 kB │ gzip: 0.12 kB
dist/index.es.js          16.98 kB │ gzip: 4.18 kB
dist/index.umd.js         13.67 kB │ gzip: 3.74 kB
```

## Future Publishing

To publish to NPM:

1. Update `package.json` with correct repository URLs
2. Create GitHub repository: `https://github.com/lbhurtado/vue-data-editor`
3. Run `npm publish --access public`

## Version History

### 1.0.0 (Initial Release)
- ✅ Dual view modes (Form + JSON)
- ✅ Type-aware field editing
- ✅ Nested data support
- ✅ Real-time validation
- ✅ Native HTML elements (no UI library dependencies)
- ✅ Fully reactive boolean checkboxes
- ✅ No infinite loop issues
- ✅ Production ready

## License

MIT

## Author

rli

## Links

- Repository: https://github.com/lbhurtado/vue-data-editor
- Issues: https://github.com/lbhurtado/vue-data-editor/issues
- Documentation: README.md
