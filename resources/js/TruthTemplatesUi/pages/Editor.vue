<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { useTemplatesStore } from '@/TruthTemplatesUi/stores/templates'
import { storeToRefs } from 'pinia'

const store = useTemplatesStore()
const { spec, pdfUrl, loading, error, isValid } = storeToRefs(store)

const jsonCode = ref(JSON.stringify(spec.value, null, 2))
const showPdfPreview = ref(false)
const activeTab = ref<'json' | 'visual'>('json')
const pdfTimestamp = ref(Date.now())
const jsonFontSize = ref(13) // Default font size in pixels

// Watch for spec changes to update JSON editor
watch(spec, (newSpec) => {
  jsonCode.value = JSON.stringify(newSpec, null, 2)
}, { deep: true })

// Watch for JSON code changes
watch(jsonCode, (newCode) => {
  try {
    const parsed = JSON.parse(newCode)
    store.updateSpec(parsed)
  } catch (e) {
    // Invalid JSON, don't update spec
  }
})

// Auto-save to localStorage
watch(spec, () => {
  store.saveToLocalStorage()
}, { deep: true, debounce: 1000 })

onMounted(() => {
  // Try to load from localStorage
  store.loadFromLocalStorage()
})

async function handleRender() {
  try {
    // Always show preview after rendering
    await store.renderTemplate()
    showPdfPreview.value = true
    pdfTimestamp.value = Date.now() // Force iframe refresh
  } catch (e) {
    console.error('Render failed:', e)
  }
}

async function handleValidate() {
  await store.validateTemplate()
}

function handleExport() {
  store.exportJson()
}

function handleImport(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (file) {
    const reader = new FileReader()
    reader.onload = (e) => {
      const content = e.target?.result as string
      if (store.importJson(content)) {
        jsonCode.value = content
      }
    }
    reader.readAsText(file)
  }
}

async function loadSample(sampleName: string) {
  if (!sampleName) return
  try {
    await store.loadSample(sampleName)
    jsonCode.value = JSON.stringify(spec.value, null, 2)
  } catch (e) {
    console.error('Failed to load sample:', e)
  }
}

function clearTemplate() {
  if (confirm('Are you sure you want to clear the current template?')) {
    store.clearSpec()
    jsonCode.value = JSON.stringify(spec.value, null, 2)
  }
}

function increaseFontSize() {
  if (jsonFontSize.value < 24) {
    jsonFontSize.value += 1
  }
}

function decreaseFontSize() {
  if (jsonFontSize.value > 8) {
    jsonFontSize.value -= 1
  }
}

function resetFontSize() {
  jsonFontSize.value = 13
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-7xl mx-auto">
      <!-- Header -->
      <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">OMR Template Editor</h1>
        <p class="text-gray-600">Design and preview OMR documents (ballots, surveys, forms)</p>
      </div>

      <!-- Toolbar -->
      <div class="bg-white rounded-lg shadow-sm p-4 mb-6 flex flex-wrap gap-3">
        <button
          @click="clearTemplate"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
          New
        </button>

        <label class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer">
          Import
          <input type="file" @change="handleImport" accept=".json" class="hidden">
        </label>

        <button
          @click="handleExport"
          :disabled="!isValid"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
        >
          Export
        </button>

        <button
          @click="handleValidate"
          :disabled="loading"
          class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 disabled:opacity-50"
        >
          Validate
        </button>

        <button
          @click="handleRender"
          :disabled="!isValid || loading"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {{ loading ? 'Rendering...' : 'Render PDF' }}
        </button>

        <div class="ml-auto flex gap-2">
          <select
            @change="loadSample(($event.target as HTMLSelectElement).value)"
            class="px-3 py-2 text-sm border border-gray-300 rounded-md"
          >
            <option value="">Load Sample...</option>
            <option value="sample-ballot">Sample Ballot</option>
            <option value="sample-survey">Sample Survey</option>
          </select>
        </div>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md mb-6">
        {{ error }}
      </div>

      <!-- Main Editor Area -->
      <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Left: JSON Editor (1/3 width) -->
        <div class="bg-white rounded-lg shadow-sm p-6 lg:col-span-1">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-lg font-semibold">JSON Specification</h2>
            <div class="flex items-center gap-2">
              <button
                @click="decreaseFontSize"
                class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                title="Decrease font size"
              >
                A-
              </button>
              <span class="text-xs text-gray-500">{{ jsonFontSize }}px</span>
              <button
                @click="increaseFontSize"
                class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                title="Increase font size"
              >
                A+
              </button>
              <button
                @click="resetFontSize"
                class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
                title="Reset font size"
              >
                Reset
              </button>
            </div>
          </div>
          <textarea
            v-model="jsonCode"
            :style="{ fontSize: jsonFontSize + 'px' }"
            class="w-full h-[600px] font-mono border border-gray-300 rounded-md p-4"
            spellcheck="false"
          ></textarea>
        </div>

        <!-- Right: Preview / Info (2/3 width) -->
        <div class="bg-white rounded-lg shadow-sm p-6 lg:col-span-2">
          <h2 class="text-lg font-semibold mb-4">Preview</h2>
          
          <div v-if="pdfUrl && showPdfPreview" class="space-y-4">
            <div class="border border-gray-300 rounded-md overflow-hidden">
              <iframe
                :key="pdfTimestamp"
                :src="pdfUrl"
                class="w-full h-[600px]"
                title="PDF Preview"
              ></iframe>
            </div>
            
            <div class="flex gap-3">
              <button
                @click="handleRender"
                :disabled="loading"
                class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
              >
                {{ loading ? 'Rendering...' : 'Re-render' }}
              </button>
              <a
                :href="pdfUrl"
                target="_blank"
                class="px-4 py-2 text-sm font-medium text-center text-white bg-green-600 rounded-md hover:bg-green-700"
              >
                Download
              </a>
              <button
                @click="showPdfPreview = false"
                class="px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
              >
                Close
              </button>
            </div>
          </div>

          <div v-else class="text-center py-20 text-gray-500">
            <svg class="mx-auto h-16 w-16 text-gray-400 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <p class="text-lg">No preview available</p>
            <p class="text-sm mt-2">Click "Render PDF" to generate a preview</p>
          </div>
        </div>
      </div>

      <!-- Document Info -->
      <div v-if="spec.document" class="mt-6 bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-semibold mb-3">Document Information</h3>
        <dl class="grid grid-cols-2 gap-4">
          <div>
            <dt class="text-sm font-medium text-gray-500">Title</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ spec.document.title }}</dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500">Unique ID</dt>
            <dd class="mt-1 text-sm text-gray-900 font-mono">{{ spec.document.unique_id }}</dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500">Layout</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ spec.document.layout || 'Not specified' }}</dd>
          </div>
          <div>
            <dt class="text-sm font-medium text-gray-500">Sections</dt>
            <dd class="mt-1 text-sm text-gray-900">{{ spec.sections?.length || 0 }}</dd>
          </div>
        </dl>
      </div>
    </div>
  </div>
</template>
