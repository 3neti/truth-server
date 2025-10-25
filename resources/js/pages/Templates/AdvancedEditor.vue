<script setup lang="ts">
import { ref, watch, onMounted, onUnmounted } from 'vue'
import { useTemplatesStore } from '@/stores/templates'
import { storeToRefs } from 'pinia'
import { useDebounceFn } from '@vueuse/core'
import TemplatePane from './Components/TemplatePane.vue'
import DataPane from './Components/DataPaneNew.vue'
import PreviewPane from './Components/PreviewPane.vue'
import TemplateLibrary from './Components/TemplateLibrary.vue'
import FamilyBrowser from './Components/FamilyBrowser.vue'
import TemplateDataBrowser from '@/components/TemplateDataBrowser.vue'

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
const showFamilyBrowser = ref(false)
const showTemplateDataBrowser = ref(false)
const showShortcutsHelp = ref(false)
const showSampleMenu = ref(false)
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

// Track currently loaded template for portable data export and updates
const currentTemplate = ref<{
  id?: number
  name?: string
  description?: string
  category?: string
  is_public?: boolean
  storage_type?: 'local' | 'remote' | 'hybrid'
  template_uri?: string
  family?: {
    slug: string
    variant?: string
  }
} | null>(null)

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
  console.log('=== Starting Compilation ===')
  console.log('Template:', handlebarsTemplate.value?.substring(0, 200))
  console.log('Data:', templateData.value)
  
  if (!handlebarsTemplate.value) {
    alert('No template loaded! Please load a template first.')
    return
  }
  
  if (!templateData.value || Object.keys(templateData.value).length === 0) {
    alert('No data loaded! Please load sample data or a data file first.')
    return
  }
  
  try {
    await store.compileTemplate()
  } catch (e) {
    console.error('Compilation failed:', e)
    alert('Compilation failed: ' + (e as any).message)
  }
}

async function handleRenderPdf() {
  try {
    // First compile if needed
    if (!mergedSpec.value) {
      console.log('No merged spec, compiling first...')
      await store.compileTemplate()
    }

    console.log('=== Rendering PDF ===')
    console.log('Merged spec:', JSON.stringify(mergedSpec.value, null, 2))
    console.log('Has document?', !!mergedSpec.value?.document)
    console.log('Has sections?', !!mergedSpec.value?.sections)
    console.log('Document title:', mergedSpec.value?.document?.title)
    console.log('Document unique_id:', mergedSpec.value?.document?.unique_id)
    console.log('Sections count:', mergedSpec.value?.sections?.length)

    // Then render the merged spec
    if (mergedSpec.value) {
      // Temporarily set spec for rendering
      const originalSpec = store.spec
      store.updateSpec(mergedSpec.value)
      await store.renderTemplate()
      store.updateSpec(originalSpec)
    } else {
      alert('No spec to render. Please compile first.')
    }
  } catch (e: any) {
    console.error('Render failed:', e)
    console.error('Error response:', e.response?.data)
    alert('Render failed: ' + (e.response?.data?.error || e.message))
  }
}

async function handleSaveTemplate() {
  try {
    const newTemplate = await store.saveTemplateToLibrary(
      saveTemplateName.value,
      saveTemplateDescription.value,
      saveTemplateCategory.value,
      saveTemplateIsPublic.value
    )
    
    showSaveDialog.value = false
    
    // If saving as new (not a copy), track as current template
    if (!currentTemplate.value?.id || currentTemplate.value?.name !== saveTemplateName.value) {
      currentTemplate.value = {
        id: newTemplate.id,
        name: newTemplate.name,
        description: newTemplate.description,
        category: newTemplate.category,
        is_public: newTemplate.is_public,
        storage_type: 'local',
      }
    }
    
    // Reset form
    saveTemplateName.value = ''
    saveTemplateDescription.value = ''
    saveTemplateCategory.value = 'ballot'
    
    // Refresh library
    libraryKey.value++
    
    alert('✓ Template saved successfully! You can now find it in the Browse Library.')
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
    
    // Update currentTemplate metadata
    if (currentTemplate.value) {
      currentTemplate.value.name = updateTemplateName.value
      currentTemplate.value.description = updateTemplateDescription.value
      currentTemplate.value.category = updateTemplateCategory.value
      currentTemplate.value.is_public = updateTemplateIsPublic.value
    }
    
    showUpdateDialog.value = false
    
    // Reset form
    updateTemplateId.value = null
    updateTemplateName.value = ''
    updateTemplateDescription.value = ''
    updateTemplateCategory.value = 'ballot'
    
    // Refresh library if open
    libraryKey.value++
    
    alert('✓ Template updated successfully!')
  } catch (e: any) {
    console.error('Update failed:', e)
    const errorMsg = e.response?.data?.error || e.message || 'Failed to update template'
    alert(`Failed to update template: ${errorMsg}`)
  }
}

async function loadSampleTemplate(sampleName = 'simple') {
  if (sampleName === 'philippines') {
    await loadPhilippinesSample()
  } else if (sampleName === 'barangay') {
    await loadBarangaySample()
  } else if (sampleName === 'barangay-mapping') {
    await loadBarangayMappingSample()
  } else {
    loadSimpleSample()
  }
}

function loadSimpleSample() {
  handlebarsTemplate.value = `{
  "document": {
    "title": "{{election_name}}",
    "unique_id": "{{precinct}}-{{date}}",
    "date": "{{date}}",
    "precinct": "{{precinct}}",
    "layout": "2-column"
  },
  "sections": [
    {{#each positions}}
    {
      "type": "multiple_choice",
      "code": "{{code}}",
      "title": "{{title}}",
      "question": "Vote for {{max_selections}}",
      "maxSelections": {{max_selections}},
      "layout": "2-column",
      "choices": [
        {{#each candidates}}
        {
          "code": "{{position}}",
          "label": "{{name}}",
          "description": "{{party}}"
        }{{#unless @last}},{{/unless}}
        {{/each}}
      ]
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}`

  const sampleData = {
    election_name: '2025 National Elections',
    precinct: '001-A',
    date: '2025-05-15',
    positions: [
      {
        code: 'PRES',
        title: 'President',
        max_selections: 1,
        candidates: [
          { position: 1, name: 'Alice Martinez', party: 'Progressive Party' },
          { position: 2, name: 'Robert Chen', party: 'Democratic Alliance' },
          { position: 3, name: 'Maria Santos', party: 'Independent' },
        ],
      },
      {
        code: 'VP',
        title: 'Vice President',
        max_selections: 1,
        candidates: [
          { position: 1, name: 'John Williams', party: 'Progressive Party' },
          { position: 2, name: 'Sarah Lee', party: 'Democratic Alliance' },
        ],
      },
      {
        code: 'SEN',
        title: 'Senator',
        max_selections: 6,
        candidates: [
          { position: 1, name: 'David Johnson', party: 'Progressive Party' },
          { position: 2, name: 'Emma Wilson', party: 'Democratic Alliance' },
          { position: 3, name: 'James Rodriguez', party: 'Independent' },
          { position: 4, name: 'Lisa Anderson', party: 'Progressive Party' },
          { position: 5, name: 'Michael Brown', party: 'Democratic Alliance' },
          { position: 6, name: 'Jennifer Garcia', party: 'Independent' },
          { position: 7, name: 'Daniel Kim', party: 'Progressive Party' },
          { position: 8, name: 'Amanda Taylor', party: 'Democratic Alliance' },
        ],
      },
    ],
  }
  
  // Update store
  store.updateTemplateData(sampleData)
  console.log('Sample data loaded:', sampleData)
  console.log('templateData.value:', templateData.value)

  // Clear current template tracking since this is a sample
  currentTemplate.value = null
}

async function loadPhilippinesSample() {
  try {
    // Load template file
    const templateResponse = await fetch('/packages/omr-template/resources/templates/philippines-election-template.hbs')
    const template = await templateResponse.text()
    
    // Load data file
    const dataResponse = await fetch('/packages/omr-template/resources/templates/philippines-election-data.json')
    const data = await dataResponse.json()
    
    handlebarsTemplate.value = template
    templateData.value = data
  } catch (e) {
    console.error('Failed to load Philippine election sample:', e)
    alert('Failed to load sample. Using default instead.')
    loadSimpleSample()
  }
}

async function loadBarangaySample() {
  try {
    const templateResponse = await fetch('/packages/omr-template/resources/templates/barangay-election-template.hbs')
    const template = await templateResponse.text()
    
    const dataResponse = await fetch('/packages/omr-template/resources/templates/barangay-election-data.json')
    const data = await dataResponse.json()
    
    handlebarsTemplate.value = template
    templateData.value = data
  } catch (e) {
    console.error('Failed to load Barangay election sample:', e)
    alert('Failed to load sample. Using default instead.')
    loadSimpleSample()
  }
}

async function loadBarangayMappingSample() {
  try {
    const templateResponse = await fetch('/packages/omr-template/resources/templates/barangay-election-mapping-template.hbs')
    const template = await templateResponse.text()
    
    const dataResponse = await fetch('/packages/omr-template/resources/templates/barangay-election-mapping-data.json')
    const data = await dataResponse.json()
    
    handlebarsTemplate.value = template
    templateData.value = data
  } catch (e) {
    console.error('Failed to load Barangay mapping sample:', e)
    alert('Failed to load sample. Using default instead.')
    loadSimpleSample()
  }
}

function clearTemplate() {
  if (confirm('Are you sure you want to clear the current template?')) {
    handlebarsTemplate.value = ''
    templateData.value = {}
    store.updateHandlebarsTemplate('')
    store.updateTemplateData({})
    currentTemplate.value = null // Clear template tracking
  }
}

function handleTemplateSelection(template: any) {
  // Load the selected template
  handlebarsTemplate.value = template.handlebars_template || ''
  templateData.value = template.sample_data || {}
  
  // Track template info for portable export
  let familyInfo: { slug: string; variant?: string } | undefined
  if (template.family_id && template.family) {
    familyInfo = {
      slug: template.family.slug || template.family_slug,
      variant: template.layout_variant
    }
  } else if (template.family_id && template.family_slug) {
    familyInfo = {
      slug: template.family_slug,
      variant: template.layout_variant
    }
  }

  // Build template_ref
  let templateRef: string
  if (template.template_uri) {
    templateRef = template.template_uri
  } else if (familyInfo) {
    templateRef = `local:${familyInfo.slug}/${familyInfo.variant}`
  } else {
    templateRef = `local:${template.id}`
  }

  currentTemplate.value = {
    id: template.id,
    name: template.name,
    storage_type: template.storage_type || 'local',
    template_uri: template.template_uri,
    template_ref: templateRef,
    family: familyInfo
  }

  console.log('Template selected:', currentTemplate.value)

  // Trigger compilation
  if (autoCompileEnabled.value) {
    setTimeout(() => {
      debouncedCompile()
    }, 100)
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

  // Track loaded template for portable export and updates
  // Extract family info if the template belongs to a family
  let familyInfo: { slug: string; variant?: string } | undefined
  if (template.family_id && template.family) {
    // If template.family is an object with slug
    familyInfo = {
      slug: template.family.slug || template.family_slug,
      variant: template.layout_variant
    }
  } else if (template.family_id && template.family_slug) {
    // If family info is flattened (from JOIN)
    familyInfo = {
      slug: template.family_slug,
      variant: template.layout_variant
    }
  }

  currentTemplate.value = {
    id: template.id,
    name: template.name,
    description: template.description,
    category: template.category,
    is_public: template.is_public,
    storage_type: template.storage_type || 'local',
    template_uri: template.template_uri,
    family: familyInfo
  }

  console.log('Loaded template tracking:', currentTemplate.value)

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
  
  // Track as current template
  let familyInfo: { slug: string; variant?: string } | undefined
  if (template.family_id && template.family) {
    familyInfo = {
      slug: template.family.slug || template.family_slug,
      variant: template.layout_variant
    }
  } else if (template.family_id && template.family_slug) {
    familyInfo = {
      slug: template.family_slug,
      variant: template.layout_variant
    }
  }
  
  currentTemplate.value = {
    id: template.id,
    name: template.name,
    description: template.description,
    category: template.category,
    is_public: template.is_public,
    storage_type: template.storage_type || 'local',
    template_uri: template.template_uri,
    family: familyInfo
  }
  
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

function openFamilyBrowser() {
  showFamilyBrowser.value = true
}

function openTemplateDataBrowser() {
  showTemplateDataBrowser.value = true
}

async function handleLoadDataFile(dataFile: any) {
  // Extract the data from the data file
  const fileData = dataFile.data || {}
  
  console.log('Loading data file:', dataFile.name, fileData)
  
  // Check if data file has a template reference
  const templateRef = fileData.document?.template_ref || dataFile.template_ref
  
  if (templateRef && templateRef.startsWith('local:')) {
    console.log('Data file references template:', templateRef)
    
    // Try to load the template
    try {
      const ref = templateRef.substring(6) // Remove "local:"
      let templateId: number | null = null
      
      // Check if it's family/variant format or direct ID
      if (ref.includes('/')) {
        // Family/variant format - need to resolve to ID
        // For now, just log and let user load manually
        console.log('Template family/variant format detected:', ref)
        alert(`This data file references template: ${templateRef}\n\nPlease load the matching template from Browse Library or Template Families.`)
      } else {
        // Direct ID
        templateId = parseInt(ref)
        
        if (!isNaN(templateId)) {
          console.log('Loading template ID:', templateId)
          const template = await store.loadTemplateFromLibrary(templateId.toString())
          
          if (template) {
            console.log('Template loaded successfully:', template.name)
            // Template is now in handlebarsTemplate and store
            currentTemplate.value = {
              id: template.id,
              name: template.name,
              description: template.description,
              category: template.category,
              is_public: template.is_public,
              storage_type: 'local',
            }
          }
        }
      }
    } catch (e) {
      console.error('Failed to auto-load template:', e)
      alert(`Could not auto-load template ${templateRef}. Please load it manually from Browse Library.`)
    }
  }
  
  // Send the FULL data structure to the backend
  // The compile endpoint will extract the payload using extractDataPayload()
  // just like the validation endpoint does
  store.updateTemplateData(fileData)
  
  showTemplateDataBrowser.value = false
  
  console.log('Loaded full data structure (backend will extract payload):', fileData)
  
  // Trigger compilation if auto-compile is enabled
  if (autoCompileEnabled.value && handlebarsTemplate.value) {
    setTimeout(() => {
      debouncedCompile()
    }, 100)
  }
}

async function handleLoadFromFamily(family: any, variant: string) {
  try {
    // Get the specific variant template
    const variantData = await store.getFamilyVariants(family.id.toString())
    const template = variantData.variants.find((v: any) => v.layout_variant === variant)
    
    if (template) {
      handlebarsTemplate.value = template.handlebars_template
      templateData.value = template.sample_data || {}
      showFamilyBrowser.value = false

      // Track loaded template for portable export
      currentTemplate.value = {
        id: template.id,
        storage_type: template.storage_type || family.storage_type || 'local',
        template_uri: template.template_uri,
        family: {
          slug: family.slug,
          variant: variant
        }
      }

      // Trigger compilation
      if (autoCompileEnabled.value) {
        setTimeout(() => {
          debouncedCompile()
        }, 100)
      }
    }
  } catch (e) {
    console.error('Failed to load from family:', e)
    alert('Failed to load template from family')
  }
}

function openUpdateDialog() {
  if (!currentTemplate.value?.id) return
  
  updateTemplateId.value = currentTemplate.value.id
  updateTemplateName.value = currentTemplate.value.name || ''
  updateTemplateDescription.value = currentTemplate.value.description || ''
  updateTemplateCategory.value = currentTemplate.value.category || 'ballot'
  updateTemplateIsPublic.value = currentTemplate.value.is_public ?? true
  
  showUpdateDialog.value = true
}

function handleKeyboardShortcut(e: KeyboardEvent) {
  // Cmd/Ctrl + S: Save or Update template
  if ((e.metaKey || e.ctrlKey) && e.key === 's') {
    e.preventDefault()
    if (handlebarsTemplate.value && templateData.value) {
      if (currentTemplate.value?.id) {
        openUpdateDialog()
      } else {
        showSaveDialog.value = true
      }
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
    showFamilyBrowser.value = false
    showTemplateDataBrowser.value = false
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
              <span v-if="currentTemplate?.name" class="ml-3 px-3 py-1 text-sm font-medium bg-green-100 text-green-700 rounded">
                📝 {{ currentTemplate.name }}
              </span>
            </h1>
            <p class="text-gray-600">
              <template v-if="currentTemplate?.name">
                Editing loaded template
                <span v-if="currentTemplate.description" class="text-gray-500">• {{ currentTemplate.description }}</span>
              </template>
              <template v-else>
                Separate Handlebars template from data for reusable OMR documents
              </template>
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
          @click="openFamilyBrowser"
          class="px-4 py-2 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-md hover:bg-purple-100"
        >
          👨‍👩‍👧‍👦 Template Families
        </button>

        <button
          @click="openTemplateDataBrowser"
          class="px-4 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100"
        >
          📁 Load Data File
        </button>

        <div class="relative">
          <button
            @click="showSampleMenu = !showSampleMenu"
            class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 flex items-center gap-2"
          >
            Load Sample
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
          </button>
          
          <div
            v-if="showSampleMenu"
            class="absolute left-0 mt-2 w-56 bg-white border border-gray-200 rounded-md shadow-lg z-10"
          >
            <button
              @click="loadSampleTemplate('simple'); showSampleMenu = false"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 first:rounded-t-md"
            >
              Simple Election Ballot
            </button>
            <button
              @click="loadSampleTemplate('philippines'); showSampleMenu = false"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              🇵🇭 Philippine General Election
            </button>
            <button
              @click="loadSampleTemplate('barangay'); showSampleMenu = false"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100"
            >
              🗳️ 2026 Barangay Elections (Ballot)
            </button>
            <button
              @click="loadSampleTemplate('barangay-mapping'); showSampleMenu = false"
              class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 last:rounded-b-md"
            >
              📋 Barangay Candidate Mapping
            </button>
          </div>
        </div>

        <button
          @click="clearTemplate"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
        >
          Clear
        </button>

        <button
          v-if="!currentTemplate?.id"
          @click="showSaveDialog = true"
          :disabled="!handlebarsTemplate || !templateData"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
        >
          💾 Save as New
        </button>
        
        <button
          v-else
          @click="openUpdateDialog"
          :disabled="!handlebarsTemplate || !templateData"
          class="px-4 py-2 text-sm font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded-md hover:bg-purple-100 disabled:opacity-50"
        >
          ✏️ Update Template
        </button>
        
        <button
          v-if="currentTemplate?.id"
          @click="showSaveDialog = true"
          :disabled="!handlebarsTemplate || !templateData"
          class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 disabled:opacity-50"
        >
          💾 Save as Copy
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
          <TemplatePane 
            v-model="handlebarsTemplate" 
            :selected-template="currentTemplate"
            @template-selected="handleTemplateSelection"
          />
        </div>

        <!-- Middle: JSON Data (25%) -->
        <div class="col-span-3 bg-white rounded-lg shadow-sm p-6 overflow-hidden">
          <DataPane 
            :model-value="templateData" 
            @update:model-value="(data) => store.updateTemplateData(data)" 
          />
        </div>

        <!-- Right: Preview (50%) -->
        <div class="col-span-6 bg-white rounded-lg shadow-sm p-6 overflow-hidden">
          <PreviewPane
            :merged-spec="mergedSpec"
            :pdf-url="pdfUrl"
            :loading="loading"
            :compilation-error="compilationError"
            :current-template="currentTemplate"
            :original-data="templateData"
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

    <!-- Family Browser Drawer -->
    <div
      v-if="showFamilyBrowser"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end sm:items-center justify-center"
      @click.self="showFamilyBrowser = false"
    >
      <div
        class="bg-white w-full h-[90vh] sm:max-w-6xl sm:rounded-lg shadow-xl overflow-hidden"
      >
        <div class="h-full p-6">
          <FamilyBrowser
            @load="handleLoadFromFamily"
            @close="showFamilyBrowser = false"
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

    <!-- Data File Browser Drawer -->
    <div
      v-if="showTemplateDataBrowser"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-end sm:items-center justify-center"
      @click.self="showTemplateDataBrowser = false"
    >
      <div
        class="bg-white w-full h-[90vh] sm:max-w-4xl sm:rounded-lg shadow-xl overflow-hidden"
      >
        <div class="h-full p-6 flex flex-col">
          <div class="flex items-center justify-between mb-4">
            <h2 class="text-xl font-semibold">Load Data File</h2>
            <button
              @click="showTemplateDataBrowser = false"
              class="text-gray-400 hover:text-gray-600"
            >
              ✕
            </button>
          </div>
          <div class="flex-1 overflow-hidden">
            <TemplateDataBrowser
              @select="handleLoadDataFile"
              @close="showTemplateDataBrowser = false"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
