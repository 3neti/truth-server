<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTemplatesStore } from '@/TruthTemplatesUi/stores/templates'
import TemplateCard from './TemplateCard.vue'
import axios from '@/lib/axios'

interface Template {
  id: number
  name: string
  description: string | null
  category: string
  handlebars_template: string
  sample_data: Record<string, any> | null
  is_public: boolean
  user_id: number | null
  created_at: string
  updated_at: string
}

const emit = defineEmits<{
  load: [template: Template]
  close: []
  update: [template: Template]
}>()

const store = useTemplatesStore()

const templates = ref<Template[]>([])
const loading = ref(false)
const searchQuery = ref('')
const selectedCategory = ref<string>('all')
const showDetailsModal = ref(false)
const selectedTemplate = ref<Template | null>(null)

const categories = [
  { value: 'all', label: 'All Templates' },
  { value: 'ballot', label: 'Ballots' },
  { value: 'survey', label: 'Surveys' },
  { value: 'test', label: 'Tests/Exams' },
  { value: 'questionnaire', label: 'Questionnaires' },
]

const filteredTemplates = computed(() => {
  let filtered = templates.value

  // Filter by category
  if (selectedCategory.value !== 'all') {
    filtered = filtered.filter(t => t.category === selectedCategory.value)
  }

  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(t =>
      t.name.toLowerCase().includes(query) ||
      (t.description && t.description.toLowerCase().includes(query))
    )
  }

  return filtered
})

const templateCount = computed(() => {
  const count = filteredTemplates.value.length
  const total = templates.value.length
  return searchQuery.value || selectedCategory.value !== 'all'
    ? `${count} of ${total} templates`
    : `${total} templates`
})

onMounted(async () => {
  await loadTemplates()
})

async function loadTemplates() {
  loading.value = true
  try {
    templates.value = await store.getTemplateLibrary()
  } catch (e) {
    console.error('Failed to load templates:', e)
  } finally {
    loading.value = false
  }
}

function handleView(template: Template) {
  selectedTemplate.value = template
  showDetailsModal.value = true
}

function handleLoad(template: Template) {
  emit('load', template)
}

async function handleDuplicate(template: Template) {
  const newName = prompt('Enter name for the duplicate template:', `${template.name} (Copy)`)
  if (!newName) return

  try {
    await store.saveTemplateToLibrary(
      newName,
      template.description || '',
      template.category,
      template.is_public
    )
    
    // Temporarily set the template and data to duplicate
    const originalTemplate = store.handlebarsTemplate
    const originalData = store.templateData
    
    store.updateHandlebarsTemplate(template.handlebars_template)
    store.updateTemplateData(template.sample_data || {})
    
    await store.saveTemplateToLibrary(
      newName,
      template.description || '',
      template.category,
      template.is_public
    )
    
    // Restore original
    store.updateHandlebarsTemplate(originalTemplate)
    store.updateTemplateData(originalData)
    
    // Reload templates
    await loadTemplates()
    alert('Template duplicated successfully!')
  } catch (e) {
    console.error('Failed to duplicate template:', e)
    alert('Failed to duplicate template')
  }
}

function handleUpdate(template: Template) {
  emit('update', template)
}

async function handleDelete(template: Template) {
  if (!confirm(`Are you sure you want to delete "${template.name}"?`)) {
    return
  }

  try {
    await axios.delete(`/api/truth-templates/templates/${template.id}`)
    
    // Reload templates
    await loadTemplates()
    alert('Template deleted successfully!')
  } catch (e: any) {
    console.error('Failed to delete template:', e)
    const errorMsg = e.response?.data?.error || e.message || 'Failed to delete template'
    alert(`Failed to delete template: ${errorMsg}`)
  }
}

function closeDetailsModal() {
  showDetailsModal.value = false
  selectedTemplate.value = null
}

function loadFromDetails() {
  if (selectedTemplate.value) {
    emit('load', selectedTemplate.value)
    closeDetailsModal()
  }
}

function exportTemplate(template: Template) {
  const data = {
    name: template.name,
    description: template.description,
    category: template.category,
    template: template.handlebars_template,
    sample_data: template.sample_data,
  }

  const blob = new Blob([JSON.stringify(data, null, 2)], {
    type: 'application/json',
  })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `${template.name.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.json`
  link.click()
}

function handleImport(event: Event) {
  const file = (event.target as HTMLInputElement).files?.[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = async (e) => {
    try {
      const content = e.target?.result as string
      const data = JSON.parse(content)
      
      // Validate structure
      if (!data.template || !data.name) {
        alert('Invalid template file format')
        return
      }

      // Save to library
      await store.saveTemplateToLibrary(
        data.name,
        data.description || '',
        data.category || 'ballot'
      )
      
      // Reload templates
      await loadTemplates()
      alert('Template imported successfully!')
    } catch (e) {
      console.error('Failed to import template:', e)
      alert('Failed to import template')
    }
  }
  reader.readAsText(file)
}
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6 pb-4 border-b">
      <div>
        <h2 class="text-2xl font-bold text-gray-900">Template Library</h2>
        <p class="text-sm text-gray-600 mt-1">{{ templateCount }}</p>
      </div>
      <button
        @click="emit('close')"
        class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
      >
        Close
      </button>
    </div>

    <!-- Search and Filters -->
    <div class="mb-6 space-y-4">
      <!-- Search -->
      <div class="relative">
        <svg
          class="absolute left-3 top-1/2 transform -translate-y-1/2 w-5 h-5 text-gray-400"
          fill="none"
          viewBox="0 0 24 24"
          stroke="currentColor"
        >
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <input
          v-model="searchQuery"
          type="text"
          placeholder="Search templates..."
          class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent"
        />
      </div>

      <!-- Category Tabs -->
      <div class="flex gap-2 overflow-x-auto pb-2">
        <button
          v-for="category in categories"
          :key="category.value"
          @click="selectedCategory = category.value"
          class="px-4 py-2 text-sm font-medium rounded-md whitespace-nowrap transition-colors"
          :class="{
            'bg-blue-600 text-white': selectedCategory === category.value,
            'bg-gray-100 text-gray-700 hover:bg-gray-200': selectedCategory !== category.value,
          }"
        >
          {{ category.label }}
        </button>
      </div>

      <!-- Import/Export -->
      <div class="flex gap-2">
        <label class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50 cursor-pointer">
          Import Template
          <input type="file" @change="handleImport" accept=".json" class="hidden" />
        </label>
      </div>
    </div>

    <!-- Templates Grid -->
    <div class="flex-1 overflow-auto">
      <!-- Loading State -->
      <div v-if="loading" class="flex items-center justify-center h-64">
        <div class="text-center">
          <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4" />
          <div class="text-gray-600">Loading templates...</div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else-if="filteredTemplates.length === 0" class="flex items-center justify-center h-64">
        <div class="text-center text-gray-400">
          <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <div class="text-lg font-medium mb-1">No templates found</div>
          <div class="text-sm">{{ searchQuery ? 'Try a different search' : 'Create your first template!' }}</div>
        </div>
      </div>

      <!-- Templates List -->
      <div v-else class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <TemplateCard
          v-for="template in filteredTemplates"
          :key="template.id"
          :template="template"
          @load="handleLoad"
          @view="handleView"
          @delete="handleDelete"
          @duplicate="handleDuplicate"
          @update="handleUpdate"
        />
      </div>
    </div>

    <!-- Details Modal -->
    <div
      v-if="showDetailsModal && selectedTemplate"
      class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-6"
      @click.self="closeDetailsModal"
    >
      <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full max-h-[90vh] overflow-auto">
        <div class="p-6">
          <!-- Modal Header -->
          <div class="flex items-start justify-between mb-4">
            <div class="flex-1">
              <h3 class="text-2xl font-bold text-gray-900 mb-2">
                {{ selectedTemplate.name }}
              </h3>
              <p class="text-gray-600">
                {{ selectedTemplate.description || 'No description' }}
              </p>
            </div>
            <button
              @click="closeDetailsModal"
              class="ml-4 text-gray-400 hover:text-gray-600"
            >
              <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>

          <!-- Meta Info -->
          <div class="flex items-center gap-4 mb-6 text-sm text-gray-500">
            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded capitalize">
              {{ selectedTemplate.category }}
            </span>
            <span v-if="selectedTemplate.is_public">Public</span>
            <span v-else>Private</span>
            <span>{{ new Date(selectedTemplate.created_at).toLocaleDateString() }}</span>
          </div>

          <!-- Template Preview -->
          <div class="mb-6">
            <h4 class="font-semibold text-gray-900 mb-2">Template</h4>
            <pre class="bg-gray-50 border border-gray-200 rounded p-4 text-xs overflow-auto max-h-64">{{ selectedTemplate.handlebars_template }}</pre>
          </div>

          <!-- Sample Data Preview -->
          <div v-if="selectedTemplate.sample_data" class="mb-6">
            <h4 class="font-semibold text-gray-900 mb-2">Sample Data</h4>
            <pre class="bg-gray-50 border border-gray-200 rounded p-4 text-xs overflow-auto max-h-64">{{ JSON.stringify(selectedTemplate.sample_data, null, 2) }}</pre>
          </div>

          <!-- Actions -->
          <div class="flex gap-3">
            <button
              @click="loadFromDetails"
              class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
            >
              Load Template
            </button>
            <button
              @click="exportTemplate(selectedTemplate)"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Export
            </button>
            <button
              @click="closeDetailsModal"
              class="px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-md hover:bg-gray-50"
            >
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
