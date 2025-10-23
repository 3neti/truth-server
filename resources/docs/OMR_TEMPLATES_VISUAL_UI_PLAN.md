# 🧩 OMR Templates Visual UI — Vue 3 + Laravel Integration Plan

> **Goal:** Build a visual, browser-based document composer that consumes the `lbhurtado/omr-templates` package to generate ballots, surveys, and OMR forms from JSON or form inputs — and render them as PDFs.

---

## 1️⃣ Objectives

- Provide a **visual interface** to build or preview JSON template definitions (sections, choices, scales).
- Allow **importing JSON** (from file or clipboard) and live-editing it with validation.
- Generate **PDFs on demand** using the backend `omr-templates` package.
- Display PDF **preview and download links**.
- Support **drag-and-drop section arrangement** and **visual indicators** of layout (columns, bubbles, etc.).
- Eventually integrate **OpenCV calibration overlay** for appreciation testing.

---

## 2️⃣ System Architecture Overview

```mermaid
flowchart LR
  A[Vue 3 UI] -->|POST JSON + Template| B[Laravel Backend (omr-templates)]
  B -->|PDF + coords.json| A
  A -->|Preview| C[PDF Viewer]
  A -->|Edit JSON| D[JSON Editor]
  A -->|Import/Export| E[Local Storage / Filesystem]
```

---

## 3️⃣ Frontend Stack (Vue 3)

| Technology | Purpose |
|-------------|----------|
| **Vue 3 (script setup)** | SPA front-end |
| **Vite** | Hot reload + modern bundling |
| **Inertia.js** | Laravel-Vue integration |
| **Pinia** | Store for template state |
| **PDF.js** | Embedded PDF preview |
| **Monaco Editor** | Rich JSON editing |
| **FilePond / VueFileAgent** | File uploads (for importing JSON templates) |
| **TailwindCSS** | Styling and layout grid |
| **handlebars.js** | Optional: client-side Handlebars preview before backend rendering |

---

## 4️⃣ Backend Stack (Laravel)

| Component | Description |
|------------|-------------|
| `lbhurtado/omr-templates` | Core package (renderer, config, fiducials) |
| `TemplateController` | API endpoint for render/validate |
| `SpecValidator` | Validates incoming JSON spec |
| `HandlebarsCompiler` | Compiles templates on backend |
| `SmartLayoutRenderer` | Generates PDF + coords |
| `storage/app/omr` | Output folder for PDFs and JSONs |

---

## 5️⃣ API Endpoints (Laravel)

| Endpoint | Method | Purpose |
|-----------|---------|---------|
| `/api/templates/render` | `POST` | Accept JSON spec, return PDF + coords |
| `/api/templates/validate` | `POST` | Validate JSON schema only |
| `/api/templates/layouts` | `GET` | Return available layout presets (from config) |
| `/api/templates/samples` | `GET` | Return sample JSON templates |
| `/api/templates/download/{id}` | `GET` | Download generated PDF |
| `/api/templates/coords/{id}` | `GET` | Return coordinates JSON for appreciation |

---

## 6️⃣ Vue Application Structure

```text
resources/js/
├─ Pages/
│  ├─ Templates/
│  │  ├─ Index.vue
│  │  ├─ Editor.vue
│  │  ├─ Preview.vue
│  │  ├─ Uploader.vue
│  │  └─ Renderer.vue
│  └─ Dashboard.vue
├─ Components/
│  ├─ JsonEditor.vue
│  ├─ PdfViewer.vue
│  ├─ SectionBuilder.vue
│  ├─ ChoiceEditor.vue
│  ├─ LayoutPreview.vue
│  ├─ Toolbar.vue
│  └─ StatusToast.vue
├─ Composables/
│  ├─ useTemplates.ts
│  ├─ useApi.ts
│  ├─ usePdfPreview.ts
│  └─ useStorage.ts
└─ Stores/
   └─ templates.ts
```

---

## 7️⃣ Vue Workflow (User Journey)

1. **Landing Page:** create/import template.
2. **Editor Page:** left JSON editor, right layout preview.
3. **Render PDF:** POST spec to backend → PDF + coords.
4. **PDF Preview:** embedded PDF.js viewer.

---

## 8️⃣ Example Pinia Store

```js
// stores/templates.ts
import { defineStore } from 'pinia'
export const useTemplates = defineStore('templates', {
  state: () => ({ spec: {}, pdfUrl: null, coordsUrl: null, loading: false, error: null }),
  actions: {
    async renderTemplate() {
      this.loading = true
      try {
        const res = await fetch('/api/templates/render', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ spec: this.spec })
        })
        const data = await res.json()
        this.pdfUrl = data.pdf_url
        this.coordsUrl = data.coords_url
      } catch (err) { this.error = err.message }
      finally { this.loading = false }
    }
  }
})
```

---

## 9️⃣ JSON Editor Integration (Monaco)

```vue
<script setup>
import { ref, watch } from 'vue'
import MonacoEditor from '@guolao/vue-monaco-editor'
import { useTemplates } from '@/stores/templates'

const store = useTemplates()
const code = ref(JSON.stringify(store.spec, null, 2))

watch(code, (val) => {
  try { store.spec = JSON.parse(val) } catch (e) {}
})
</script>

<template>
  <MonacoEditor language="json" v-model="code" theme="vs-dark" height="500px" />
</template>
```

---

## 🔟 PDF Preview (PDF.js)

```vue
<script setup>
import { ref, onMounted } from 'vue'
import { useTemplates } from '@/stores/templates'
const store = useTemplates()
const viewer = ref()
onMounted(() => {
  if (store.pdfUrl) {
    import('pdfjs-dist/web/pdf_viewer').then(({ PDFViewer }) => {
      const container = viewer.value
      const pdfjsLib = window['pdfjs-dist/build/pdf']
      pdfjsLib.GlobalWorkerOptions.workerSrc = '/pdf.worker.min.js'
      pdfjsLib.getDocument(store.pdfUrl).promise.then((pdfDoc) => {
        const pdfViewer = new PDFViewer({ container })
        pdfViewer.setDocument(pdfDoc)
      })
    })
  }
})
</script>

<template>
  <div ref="viewer" class="pdf-container"></div>
</template>
```

---

## 11️⃣ Layout Preview (Concept)

- Draw section boxes.
- Display bubbles and labels using mock spacing.
- Drag-and-drop sections using `vuedraggable`.

---

## 12️⃣ Import / Export Feature

- Use **FilePond** for upload.
- Export JSON as downloadable file.

```js
function exportJson(spec) {
  const blob = new Blob([JSON.stringify(spec, null, 2)], { type: 'application/json' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `${spec.document?.unique_id || 'template'}.json`
  link.click()
}
```

---

## 13️⃣ Advanced: Coords Overlay

- Parse `coords.json`.
- Draw circles over PDF preview.
- Hover highlights bubble metadata (section/choice).
- Future: show OpenCV marks from `appreciate.py`.

---

## 14️⃣ Local Storage Autosave

```js
watch(() => store.spec, (val) => {
  localStorage.setItem('omr-template', JSON.stringify(val))
}, { deep: true })
```

---

## 15️⃣ Visual Branding / UX

- Toolbar: New / Import / Validate / Render / Download.
- Tailwind + DaisyUI theming.
- Toast notifications + loading overlay.

---

## 16️⃣ Deployment

- Hosted under `/templates` route.
- PDFs saved to `/storage/app/omr`.
- Accessible via `/storage` symlink.

---

## 17️⃣ Stretch Goals

| Feature | Description |
|----------|--------------|
| Live OMR Preview | Overlay fiducials + bubbles |
| OpenCV Result Overlay | Display scanned marks |
| Template Library | Save and version JSON templates |
| Role-based Access | Admin vs Operator |
| Theme Packs | UI control over spacing/fonts |
| Drag-and-Drop Field Builder | Generate JSON visually |

---

## ✅ Summary

This UI enables:
- Visual JSON template creation.
- Validation and on-demand PDF generation.
- Real-time PDF preview via PDF.js.
- Full integration with `lbhurtado/omr-templates` backend.

It becomes a **document design & verification environment** for OMR ballots, surveys, and hybrid election systems.

---
