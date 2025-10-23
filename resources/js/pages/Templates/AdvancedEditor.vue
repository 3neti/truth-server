<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useTemplatesStore } from '@/stores/templates'
import { storeToRefs } from 'pinia'
import { useDebounceFn } from '@vueuse/core'
import TemplatePane from './Components/TemplatePane.vue'
import DataPane from './Components/DataPane.vue'
import PreviewPane from './Components/PreviewPane.vue'
import TemplateLibrary from './Components/TemplateLibrary.vue'

const store = useTemplatesStore()
const {
  handlebarsTemplate,
  templateData,
  mergedSpec,
  compilationError,
  pdfUrl,
  loading,
  error,
} = storeToRefs(store)

const autoCompileEnabled = ref(true)
const showSaveDialog = ref(false)
const showUpdateDialog = ref(false)
const showLibraryDrawer = ref(false)
const showShortcutsHelp = ref(false)
const libraryKey = ref(0)
const saveTemplateName = ref('')
const saveTemplateDescription = ref('')
const saveTemplateCategory = ref('ballot')
const saveTemplateIsPublic = ref(true) // Default to public
const updateTemplateId = ref<number | null>(null)
const updateTemplateName = ref('')
const updateTemplateDescription = ref('')
const updateTemplateCategory = ref('ballot')
const updateTemplateIsPublic = ref(true)

// Auto-compile with debounce
const debouncedCompile = useDebounceFn(async () => {
  if (autoCompileEnabled.value && handlebarsTemplate.value && templateData.value) {
    try {
      await store.compileTemplate()
    } catch (e) {
      console.error('Auto-compilation failed:', e)
    }
  }
}, 1000)

// Watch for changes and auto-compile
watch([handlebarsTemplate, templateData], () => {
  if (autoCompileEnabled.value) {
    debouncedCompile()
  }
}, { deep: true })

// Auto-save to localStorage
watch([handlebarsTemplate, templateData], () => {
  store.saveToLocalStorage()
}, { deep: true, debounce: 1000 })

onMounted(() => {
  store.setMode('advanced')
  store.loadFromLocalStorage()

  // Load sample template if empty
  if (!handlebarsTemplate.value) {
    loadSampleTemplate()
  }

  // Add keyboard shortcuts
  document.addEventListener('keydown', handleKeyboardShortcut)
})

onUnmounted(() => {
  document.removeEventListener('keydown', handleKeyboardShortcut)
})

async function handleCompile() {
  try {
    await store.compileTemplate()
  } catch (e) {
    console.error('Compilation failed:', e)
  }
}

async function handleRenderPdf() {
  try {
    // First compile if needed
    if (!mergedSpec.value) {
      await store.compileTemplate()
    }

    // Then render the merged spec
    if (mergedSpec.value) {
      // Temporarily set spec for rendering
      const originalSpec = store.spec
      store.updateSpec(mergedSpec.value)
      await store.renderTemplate()
      store.updateSpec(originalSpec)
    }
  } catch (e) {
    console.error('Render failed:', e)
  }
}

async function handleSaveTemplate() {
  try {
    const savedTemplate = await store.saveTemplateToLibrary(
      saveTemplateName.value,
      saveTemplateDescription.value,
      saveTemplateCategory.value,
      saveTemplateIsPublic.value
    )
    showSaveDialog.value = false
    
    // Reset form
    saveTemplateName.value = ''
    saveTemplateDescription.value = ''
    saveTemplateCategory.value = 'ballot'
    
    alert('Template saved successfully! You can now find it in the Browse Library.')
  } catch (e) {
    console.error('Save failed:', e)
    alert('Failed to save template. Please try again.')
  }
}

async function handleUpdateTemplate() {
  if (!updateTemplateId.value) return
  
  try {
    await store.updateTemplateInLibrary(
      updateTemplateId.value,
      updateTemplateName.value,
      updateTemplateDescription.value,
      updateTemplateCategory.value,
      updateTemplateIsPublic.value
    )
    showUpdateDialog.value = false
    
    // Reset form
    updateTemplateId.value = null
    updateTemplateName.value = ''
    updateTemplateDescription.value = ''
    updateTemplateCategory.value = 'ballot'
    
    alert('Template updated successfully!')
  } catch (e: any) {
    console.error('Update failed:', e)
    const errorMsg = e.response?.data?.error || e.message || 'Failed to update template'
    alert(`Failed to update template: ${errorMsg}`)
  }
}

function loadSampleTemplate() {
  handlebarsTemplate.value = `{
  "document": {
    "title": "{{election.title}}",
    "unique_id": "{{election.id}}",
    "layout": "{{layout}}"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "question": "{{question}}",
      "maxSelections": {{maxSelections}},
      "layout": "{{../layout}}",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{code}}",
          "label": "{{name}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}`

  templateData.value = {
    election: {
      title: '2025 General Election',
      id: 'BAL-2025-001',
    },
    layout: '2-col',
    positions: [
      {
        code: 'PRESIDENT',
        title: 'President',
        question: 'Vote for one',
        maxSelections: 1,
        candidates: [
          { code: 'P-A', name: 'Alice Johnson' },
          { code: 'P-B', name: 'Bob Williams' },
        ],
      },
    ],
  }
}

function clearTemplate() {
  if (confirm('Are you sure you want to clear the current template?')) {
    handlebarsTemplate.value = ''
    templateData.value = {}
    store.updateHandlebarsTemplate('')
    store.updateTemplateData({})
  }
}

function switchToSimpleMode() {
  if (confirm('Switch to simple mode? Your current template will be saved.')) {
    store.setMode('simple')
    window.location.href = '/templates/editor'
  }
}

function handleLoadFromLibrary(template: any) {
  handlebarsTemplate.value = template.handlebars_template
  templateData.value = template.sample_data || {}
  showLibraryDrawer.value = false

  // Trigger compilation
  if (autoCompileEnabled.value) {
    setTimeout(() => {
      debouncedCompile()
    }, 100)
  }
}

function handleUpdateFromLibrary(template: any) {
  // Load template data into editor
  handlebarsTemplate.value = template.handlebars_template
  templateData.value = template.sample_data || {}
  
  // Populate update form
  updateTemplateId.value = template.id
  updateTemplateName.value = template.name
  updateTemplateDescription.value = template.description || ''
  updateTemplateCategory.value = template.category
  updateTemplateIsPublic.value = template.is_public
  
  // Close library and show update dialog
  showLibraryDrawer.value = false
  showUpdateDialog.value = true
  
  // Trigger compilation
  if (autoCompileEnabled.value) {
    setTimeout(() => {
      debouncedCompile()
    }, 100)
  }
}

function openLibrary() {
  showLibraryDrawer.value = true
  // Force reload of library by changing key
  libraryKey.value++
}

function handleKeyboardShortcut(e: KeyboardEvent) {
  // Cmd/Ctrl + S: Save template
  if ((e.metaKey || e.ctrlKey) && e.key === 's') {
    e.preventDefault()
    if (handlebarsTemplate.value && templateData.value) {
      showSaveDialog.value = true
    }
  }
  
  // Cmd/Ctrl + Enter: Compile
  if ((e.metaKey || e.ctrlKey) && e.key === 'Enter') {
    e.preventDefault()
    if (handlebarsTemplate.value && templateData.value && !loading.value) {
      handleCompile()
    }
  }
  
  // Cmd/Ctrl + B: Browse library
  if ((e.metaKey || e.ctrlKey) && e.key === 'b') {
    e.preventDefault()
    openLibrary()
  }
  
  // Cmd/Ctrl + R: Render PDF
  if ((e.metaKey || e.ctrlKey) && e.key === 'r') {
    e.preventDefault()
    if (mergedSpec.value && !loading.value) {
      handleRenderPdf()
    }
  }
  
  // Escape: Close dialogs
  if (e.key === 'Escape') {
    showSaveDialog.value = false
    showUpdateDialog.value = false
    showLibraryDrawer.value = false
    showShortcutsHelp.value = false
  }
  
  // ? or Cmd/Ctrl + /: Show shortcuts help
  if (e.key === '?' || ((e.metaKey || e.ctrlKey) && e.key === '/')) {
    e.preventDefault()
    showShortcutsHelp.value = !showShortcutsHelp.value
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-6">
    <div class="max-w-[1800px] mx-auto">
      <!-- Header -->
      <div class="mb-6">
        <div class="flex items-center justify-between">
          <div>
            <h1 class="text-3xl font-bold text-gray-900 mb-2">
              OMR Template Editor
              <span class="ml-3 px-3 py-1 text-sm font-medium bg-purple-100 text-purple-700 rounded">
                Advanced Mode
              </span>
            </h1>
            <p class="text-gray-600">
              Separate Handlebars template from data for reusable OMR documents
            </p>
          </div>

          <div class="flex items-center gap-2">
            <button
              @click="showShortcutsHelp = true"
              class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-900"
              title="Keyboard shortcuts (?)"
            >
              ⌨️
            </button>
            <button
              @click="switchToSimpleMode"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Switch to Simple Mode
            </button>
          </div>
        </div>
      </div>

      <!-- Toolbar -->
      <div class="bg-white rounded-lg shadow-sm p-4 mb-6 flex flex-wrap gap-3">
        <button
          @click="openLibrary"
          class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100"
        >
          📚 Browse Library
        </button>

        <button
          @click="loadSampleTemplate"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
          Load Sample
        </button>

        <button
          @click="clearTemplate"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
          Clear
        </button>

        <button
          @click="showSaveDialog = true"
          :disabled="!handlebarsTemplate || !templateData"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
        >
          Save Template
        </button>

        <div class="w-px h-8 bg-gray-300" />

        <button
          @click="handleCompile"
          :disabled="!handlebarsTemplate || !templateData || loading"
          class="px-4 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100 disabled:opacity-50"
        >
          {{ loading ? 'Compiling...' : 'Compile & Preview' }}
        </button>

        <button
          @click="handleRenderPdf"
          :disabled="!mergedSpec || loading"
          class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
        >
          {{ loading ? 'Rendering...' : 'Render PDF' }}
        </button>

        <div class="ml-auto flex items-center gap-2">
          <label class="flex items-center gap-2 text-sm text-gray-700">
            <input
              type="checkbox"
              v-model="autoCompileEnabled"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <span>Auto-compile</span>
          </label>
        </div>
      </div>

      <!-- Error Display -->
      <div v-if="error" class="bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-md mb-6">
        {{ error }}
      </div>

      <!-- 3-Pane Layout -->
      <div class="grid grid-cols-12 gap-6" style="height: calc(100vh - 320px);">
        <!-- Left: Handlebars Template (25%) -->
        <div class="col-span-3 bg-white rounded-lg shadow-sm p-6 overflow-hidden">
          <TemplatePane v-model="handlebarsTemplate" />
        </div>

        <!-- Middle: JSON Data (25%) -->
        <div class="col-span-3 bg-white rounded-lg shadow-sm p-6 overflow-hidden">
          <DataPane v-model="templateData" />
        </div>

        <!-- Right: Preview (50%) -->
        <div class="col-span-6 bg-white rounded-lg shadow-sm p-6 overflow-hidden">
          <PreviewPane
            :merged-spec="mergedSpec"
            :pdf-url="pdfUrl"
            :loading="loading"
            :compilation-error="compilationError"
          />
        </div>
      </div>
    </div>

    <!-- Library Drawer -->
    <div
      v-if="showLibraryDrawer"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end sm:items-center justify-center"
      @click.self="showLibraryDrawer = false"
    >
      <div
        class="bg-white w-full h-[90vh] sm:max-w-6xl sm:rounded-lg shadow-xl overflow-hidden"
      >
        <div class="h-full p-6">
          <TemplateLibrary
            :key="libraryKey"
            @load="handleLoadFromLibrary"
            @update="handleUpdateFromLibrary"
            @close="showLibraryDrawer = false"
          />
        </div>
      </div>
    </div>

    <!-- Save Template Dialog -->
    <div
      v-if="showSaveDialog"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showSaveDialog = false"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-semibold mb-4">Save Template to Library</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Template Name *
            </label>
            <input
              v-model="saveTemplateName"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              placeholder="e.g., General Election Ballot"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              v-model="saveTemplateDescription"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              rows="3"
              placeholder="Optional description..."
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Category *
            </label>
            <select
              v-model="saveTemplateCategory"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="ballot">Ballot</option>
              <option value="survey">Survey</option>
              <option value="test">Test/Exam</option>
              <option value="questionnaire">Questionnaire</option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="template-is-public"
              v-model="saveTemplateIsPublic"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label for="template-is-public" class="text-sm text-gray-700">
              Make this template public (visible to everyone)
            </label>
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button
            @click="showSaveDialog = false"
            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="handleSaveTemplate"
            :disabled="!saveTemplateName"
            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 disabled:opacity-50"
          >
            Save
          </button>
        </div>
      </div>
    </div>

    <!-- Keyboard Shortcuts Help -->
    <div
      v-if="showShortcutsHelp"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showShortcutsHelp = false"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <div class="flex items-center justify-between mb-4">
          <h2 class="text-xl font-semibold">Keyboard Shortcuts</h2>
          <button
            @click="showShortcutsHelp = false"
            class="text-gray-400 hover:text-gray-600"
          >
            ✕
          </button>
        </div>

        <div class="space-y-3 text-sm">
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-gray-700">Save Template</span>
            <kbd class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">⌘ S</kbd>
          </div>
          
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-gray-700">Compile & Preview</span>
            <kbd class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">⌘ ↵</kbd>
          </div>
          
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-gray-700">Browse Library</span>
            <kbd class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">⌘ B</kbd>
          </div>
          
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-gray-700">Render PDF</span>
            <kbd class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">⌘ R</kbd>
          </div>
          
          <div class="flex items-center justify-between py-2 border-b border-gray-100">
            <span class="text-gray-700">Close Dialogs</span>
            <kbd class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">ESC</kbd>
          </div>
          
          <div class="flex items-center justify-between py-2">
            <span class="text-gray-700">Show This Help</span>
            <kbd class="px-2 py-1 bg-gray-100 rounded text-xs font-mono">?</kbd>
          </div>
        </div>

        <p class="mt-4 text-xs text-gray-500">
          Use Ctrl instead of ⌘ on Windows/Linux
        </p>
      </div>
    </div>

    <!-- Update Template Dialog -->
    <div
      v-if="showUpdateDialog"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50"
      @click.self="showUpdateDialog = false"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 w-full max-w-md">
        <h2 class="text-xl font-semibold mb-4">Update Template</h2>

        <div class="space-y-4">
          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Template Name *
            </label>
            <input
              v-model="updateTemplateName"
              type="text"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              placeholder="e.g., General Election Ballot"
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Description
            </label>
            <textarea
              v-model="updateTemplateDescription"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
              rows="3"
              placeholder="Optional description..."
            />
          </div>

          <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">
              Category *
            </label>
            <select
              v-model="updateTemplateCategory"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-blue-500 focus:border-blue-500"
            >
              <option value="ballot">Ballot</option>
              <option value="survey">Survey</option>
              <option value="test">Test/Exam</option>
              <option value="questionnaire">Questionnaire</option>
            </select>
          </div>

          <div class="flex items-center gap-2">
            <input
              type="checkbox"
              id="update-template-is-public"
              v-model="updateTemplateIsPublic"
              class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
            />
            <label for="update-template-is-public" class="text-sm text-gray-700">
              Make this template public (visible to everyone)
            </label>
          </div>
        </div>

        <div class="flex gap-3 mt-6">
          <button
            @click="showUpdateDialog = false"
            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
          >
            Cancel
          </button>
          <button
            @click="handleUpdateTemplate"
            :disabled="!updateTemplateName"
            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-purple-600 rounded-md hover:bg-purple-700 disabled:opacity-50"
          >
            Update
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
