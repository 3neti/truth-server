<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTemplateDataStore } from '@/stores/templateData'
import { DataEditor } from '@lbhurtado/vue-data-editor'
import TemplateDataBrowser from '@/components/TemplateDataBrowser.vue'
import TemplatePicker from '@/components/TemplatePicker.vue'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogDescription,
  DialogFooter,
} from '@/components/ui/dialog'
import { Save, FolderOpen, FileText, FilePlus } from 'lucide-vue-next'

const store = useTemplateDataStore()

// Editor state
const dataObject = ref<Record<string, any>>({})
const currentFileName = ref('')
const isModified = ref(false)
const editorKey = ref(0) // Force DataEditor re-render

// UI state
const showSaveDialog = ref(false)
const showBrowserDrawer = ref(false)
const validating = ref(false)
const validationResult = ref<any>(null)

// Save form
const saveForm = ref({
  name: '',
  description: '',
  template_ref: '',
  category: 'general',
  is_public: false,
})

onMounted(() => {
  // Start with empty data or load from query param
  const urlParams = new URLSearchParams(window.location.search)
  const dataFileId = urlParams.get('id')
  
  if (dataFileId) {
    loadTemplateData(parseInt(dataFileId))
  } else {
    resetEditor()
  }
})

function resetEditor() {
  dataObject.value = {}
  currentFileName.value = ''
  isModified.value = false
  store.clearCurrent()
  editorKey.value++ // Force re-render
}

function handleDataChange(newData: Record<string, any>) {
  dataObject.value = newData
  isModified.value = true
}

async function loadTemplateData(id: number) {
  try {
    const dataFile = await store.fetchTemplateData(id)
    console.log('Loading data file:', dataFile)
    dataObject.value = { ...dataFile.data }
    currentFileName.value = dataFile.name
    isModified.value = false
    editorKey.value++ // Force re-render
    
    // Update URL
    const url = new URL(window.location.href)
    url.searchParams.set('id', id.toString())
    window.history.pushState({}, '', url)
  } catch (e) {
    console.error('Failed to load data file:', e)
    alert('Failed to load data file')
  }
}

function openSaveDialog() {
  if (store.currentTemplateData) {
    // Editing existing file
    saveForm.value = {
      name: store.currentTemplateData.name,
      description: store.currentTemplateData.description || '',
      template_ref: store.currentTemplateData.template_ref || '',
      category: store.currentTemplateData.category,
      is_public: store.currentTemplateData.is_public,
    }
  } else {
    // New file
    saveForm.value = {
      name: '',
      description: '',
      template_ref: '',
      category: 'general',
      is_public: false,
    }
  }
  showSaveDialog.value = true
}

async function saveTemplateData() {
  if (!saveForm.value.name) {
    alert('Please enter a name')
    return
  }

  try {
    if (store.currentTemplateData) {
      // Update existing
      await store.updateTemplateData(store.currentTemplateData.id, {
        ...saveForm.value,
        data: dataObject.value,
      })
      currentFileName.value = saveForm.value.name
    } else {
      // Create new
      const newFile = await store.createTemplateData({
        ...saveForm.value,
        data: dataObject.value,
      })
      currentFileName.value = newFile.name
      
      // Update URL
      const url = new URL(window.location.href)
      url.searchParams.set('id', newFile.id.toString())
      window.history.pushState({}, '', url)
    }
    
    isModified.value = false
    showSaveDialog.value = false
    alert('Data file saved successfully!')
  } catch (e) {
    alert('Failed to save data file')
  }
}

function openBrowser() {
  showBrowserDrawer.value = true
}

function handleSelectTemplateData(dataFile: any) {
  loadTemplateData(dataFile.id)
  showBrowserDrawer.value = false
}

function createNew() {
  if (isModified.value && !confirm('You have unsaved changes. Continue?')) {
    return
  }
  
  resetEditor()
  
  // Clear URL
  const url = new URL(window.location.href)
  url.searchParams.delete('id')
  window.history.pushState({}, '', url)
}

const pageTitle = computed(() => {
  if (currentFileName.value) {
    return currentFileName.value + (isModified.value ? ' *' : '')
  }
  return 'New Data File' + (isModified.value ? ' *' : '')
})

async function validateData() {
  if (!store.currentTemplateData) return
  
  validating.value = true
  validationResult.value = null
  
  try {
    const response = await fetch(`/api/template-data/${store.currentTemplateData.id}/validate`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
      },
    })
    
    validationResult.value = await response.json()
    
    console.log('Validation result:', validationResult.value)
    
    if (validationResult.value.valid) {
      const specPreview = validationResult.value.spec_preview || {}
      let message = `✅ Validation passed!\n\nTemplate: ${validationResult.value.template_name}\n\n`
      message += `Your data successfully compiled into a valid OMR specification.\n\n`
      if (specPreview.document) {
        message += `Document: ${specPreview.document.title || 'N/A'}\n`
      }
      if (specPreview.sections_count !== undefined) {
        message += `Sections: ${specPreview.sections_count}\n`
      }
      alert(message)
    } else {
      let message = `❌ Validation failed\n\nTemplate: ${validationResult.value.template_name}\n\n`
      
      if (validationResult.value.error) {
        message += `${validationResult.value.error}\n`
      }
      if (validationResult.value.compilation_error) {
        message += `\nDetails: ${validationResult.value.compilation_error}`
      }
      
      console.log('Full validation result:', validationResult.value)
      alert(message)
    }
  } catch (e) {
    console.error('Validation failed:', e)
    alert('Failed to validate data file')
  } finally {
    validating.value = false
  }
}
</script>

<template>
  <div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto p-8">
      <!-- Header -->
      <div class="mb-6 flex items-center justify-between">
        <div>
          <h1 class="text-3xl font-bold text-gray-900 flex items-center gap-2">
            <FileText class="h-8 w-8" />
            {{ pageTitle }}
          </h1>
          <p class="text-sm text-gray-600 mt-1">
            Edit structured data with template reference
          </p>
        </div>

        <div class="flex gap-2">
          <Button variant="outline" @click="createNew">
            <FilePlus class="h-4 w-4 mr-2" />
            New
          </Button>
          <Button variant="outline" @click="openBrowser">
            <FolderOpen class="h-4 w-4 mr-2" />
            Open
          </Button>
          <Button @click="openSaveDialog">
            <Save class="h-4 w-4 mr-2" />
            Save
          </Button>
          <Button 
            v-if="store.currentTemplateData" 
            variant="outline" 
            @click="validateData"
            :disabled="validating"
          >
            {{ validating ? 'Validating...' : 'Validate' }}
          </Button>
        </div>
      </div>

      <!-- Metadata Panel (when file is loaded) -->
      <div v-if="store.currentTemplateData" class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-4">
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <h3 class="text-sm font-semibold text-blue-900 mb-2">File Metadata</h3>
            <div class="grid grid-cols-2 gap-4 text-sm">
              <div>
                <span class="text-gray-600">Category:</span>
                <span class="ml-2 font-medium text-gray-900">{{ store.currentTemplateData.category }}</span>
              </div>
              <div>
                <span class="text-gray-600">Visibility:</span>
                <span class="ml-2 font-medium text-gray-900">{{ store.currentTemplateData.is_public ? 'Public' : 'Private' }}</span>
              </div>
              <div class="col-span-2">
                <span class="text-gray-600">Template Reference:</span>
                <span v-if="store.currentTemplateData.template_ref" class="ml-2 font-mono text-sm bg-white px-2 py-1 rounded border border-blue-300 text-blue-900">
                  {{ store.currentTemplateData.template_ref }}
                </span>
                <span v-else class="ml-2 text-gray-400 italic">None</span>
              </div>
              <div v-if="store.currentTemplateData.description" class="col-span-2">
                <span class="text-gray-600">Description:</span>
                <p class="mt-1 text-gray-900">{{ store.currentTemplateData.description }}</p>
              </div>
            </div>
          </div>
          <Button variant="ghost" size="sm" @click="openSaveDialog" class="text-blue-700 hover:text-blue-900">
            Edit Metadata
          </Button>
        </div>
      </div>

      <!-- Editor -->
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6" style="height: calc(100vh - 350px);">
        <DataEditor :key="editorKey" :model-value="dataObject" @update:model-value="handleDataChange" />
      </div>

      <!-- Info -->
      <div class="mt-4 text-sm text-gray-600">
        <p>💡 <strong>Tip:</strong> Use Form View for guided editing or JSON View for direct JSON editing.</p>
        <p class="mt-1">Data files can be linked to templates using the <code class="bg-gray-100 px-1 rounded">template_ref</code> field.</p>
      </div>
    </div>

    <!-- Save Dialog -->
    <Dialog v-model:open="showSaveDialog">
      <DialogContent class="max-w-lg">
        <DialogHeader>
          <DialogTitle>
            {{ store.currentTemplateData ? 'Update' : 'Save' }} Data File
          </DialogTitle>
          <DialogDescription>
            {{ store.currentTemplateData ? 'Update the details of this data file' : 'Save this data as a new file' }}
          </DialogDescription>
        </DialogHeader>

        <div class="space-y-4 py-4">
          <div>
            <Label>Name *</Label>
            <Input v-model="saveForm.name" placeholder="e.g., Election 2025 Data" />
          </div>

          <div>
            <Label>Description</Label>
            <textarea
              v-model="saveForm.description"
              placeholder="Brief description of this data file..."
              rows="3"
              class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            />
          </div>

          <div>
            <TemplatePicker
              v-model="saveForm.template_ref"
              label="Template Reference (Optional)"
              placeholder="Search templates..."
            />
            <p class="text-xs text-gray-500 mt-1">
              Link this data file to a template for validation and rendering
            </p>
          </div>

          <div class="grid grid-cols-2 gap-4">
            <div>
              <Label>Category</Label>
              <select
                v-model="saveForm.category"
                class="w-full h-9 px-3 rounded-md border border-gray-300 text-sm"
              >
                <option value="general">General</option>
                <option value="ballot">Ballot</option>
                <option value="election">Election</option>
                <option value="test">Test</option>
              </select>
            </div>

            <div class="flex items-center gap-2 mt-6">
              <input
                type="checkbox"
                v-model="saveForm.is_public"
                id="is_public"
                class="h-4 w-4 rounded border-gray-300"
              />
              <Label for="is_public">Public</Label>
            </div>
          </div>
        </div>

        <DialogFooter>
          <Button variant="outline" @click="showSaveDialog = false">
            Cancel
          </Button>
          <Button @click="saveTemplateData">
            {{ store.currentTemplateData ? 'Update' : 'Save' }}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>

    <!-- Browser Dialog -->
    <Dialog v-model:open="showBrowserDrawer">
      <DialogContent class="max-w-4xl h-[80vh] flex flex-col">
        <DialogHeader>
          <DialogTitle>Open Data File</DialogTitle>
        </DialogHeader>
        <div class="flex-1 overflow-hidden">
          <TemplateDataBrowser
            @select="handleSelectTemplateData"
            @close="showBrowserDrawer = false"
          />
        </div>
      </DialogContent>
    </Dialog>
  </div>
</template>
