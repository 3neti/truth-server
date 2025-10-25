<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useTemplatesStore } from '@/stores/templates'
import FamilyCard from './FamilyCard.vue'

interface TemplateFamily {
  id: number
  slug: string
  name: string
  description?: string
  category: string
  version: string
  is_public: boolean
  variants_count: number
  layout_variants: string[]
  created_at: string
  templates?: any[]
}

const emit = defineEmits<{
  load: [family: TemplateFamily, variant: string]
  close: []
}>()

const store = useTemplatesStore()

const families = ref<TemplateFamily[]>([])
const loading = ref(false)
const searchQuery = ref('')
const selectedCategory = ref('all')
const showVariantSelector = ref(false)
const selectedFamily = ref<TemplateFamily | null>(null)
const selectedVariant = ref<string>('')

const categories = [
  { value: 'all', label: 'All' },
  { value: 'ballot', label: 'Ballots' },
  { value: 'survey', label: 'Surveys' },
  { value: 'test', label: 'Tests' },
  { value: 'questionnaire', label: 'Questionnaires' },
]

const filteredFamilies = computed(() => {
  let filtered = families.value

  // Category filter
  if (selectedCategory.value !== 'all') {
    filtered = filtered.filter((f) => f.category === selectedCategory.value)
  }

  // Search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(
      (f) =>
        f.name.toLowerCase().includes(query) ||
        (f.description && f.description.toLowerCase().includes(query))
    )
  }

  return filtered
})

const familyCount = computed(() => {
  return `${filteredFamilies.value.length} / ${families.value.length}`
})

async function loadFamilies() {
  loading.value = true
  try {
    families.value = await store.getTemplateFamilies({
      category: selectedCategory.value !== 'all' ? selectedCategory.value : undefined,
      search: searchQuery.value || undefined,
    })
  } catch (error) {
    console.error('Failed to load families:', error)
  } finally {
    loading.value = false
  }
}

function handleLoadFamily(family: TemplateFamily) {
  selectedFamily.value = family
  
  if (family.variants_count === 1) {
    // Only one variant, load directly
    emit('load', family, family.layout_variants[0])
  } else {
    // Multiple variants, show selector
    selectedVariant.value = family.layout_variants[0] || 'default'
    showVariantSelector.value = true
  }
}

function handleVariantSelected() {
  if (selectedFamily.value && selectedVariant.value) {
    emit('load', selectedFamily.value, selectedVariant.value)
    showVariantSelector.value = false
  }
}

async function handleDeleteFamily(family: TemplateFamily) {
  if (!confirm(`Are you sure you want to delete "${family.name}"?`)) {
    return
  }

  try {
    await store.deleteTemplateFamily(family.id.toString())
    await loadFamilies()
  } catch (error) {
    alert('Failed to delete family')
  }
}

async function handleExportFamily(family: TemplateFamily) {
  try {
    const data = await store.exportTemplateFamily(family.id.toString())
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const a = document.createElement('a')
    a.href = url
    a.download = `${family.slug}-family.json`
    a.click()
    URL.revokeObjectURL(url)
  } catch (error) {
    alert('Failed to export family')
  }
}

function handleImportClick() {
  const input = document.createElement('input')
  input.type = 'file'
  input.accept = '.json'
  input.onchange = async (e: any) => {
    const file = e.target?.files?.[0]
    if (!file) return

    try {
      const text = await file.text()
      const data = JSON.parse(text)
      
      await store.importTemplateFamily(data)
      await loadFamilies()
      alert('Family imported successfully!')
    } catch (error: any) {
      alert('Failed to import family: ' + (error.message || 'Invalid file'))
    }
  }
  input.click()
}

function handleViewDetails(family: TemplateFamily) {
  // For now, just show variant selector
  selectedFamily.value = family
  selectedVariant.value = family.layout_variants[0] || 'default'
  showVariantSelector.value = true
}

onMounted(() => {
  loadFamilies()
})
</script>

<template>
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="flex items-center justify-between mb-4">
      <h2 class="text-2xl font-bold text-gray-900">Template Families</h2>
      <div class="flex items-center gap-2">
        <button
          @click="handleImportClick"
          class="px-3 py-2 text-sm font-medium text-green-700 bg-green-50 border border-green-200 rounded-md hover:bg-green-100"
          title="Import family"
        >
          ⬆️ Import
        </button>
        <button
          @click="emit('close')"
          class="p-2 text-gray-400 hover:text-gray-600 rounded-md hover:bg-gray-100"
        >
          <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>
    </div>

    <!-- Search & Filters -->
    <div class="mb-4 space-y-3">
      <!-- Search -->
      <input
        v-model="searchQuery"
        @input="loadFamilies"
        type="text"
        placeholder="Search families..."
        class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
      />

      <!-- Category Tabs -->
      <div class="flex gap-2 overflow-x-auto pb-2">
        <button
          v-for="cat in categories"
          :key="cat.value"
          @click="selectedCategory = cat.value; loadFamilies()"
          :class="[
            'px-4 py-2 text-sm font-medium rounded-md whitespace-nowrap transition-colors',
            selectedCategory === cat.value
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          ]"
        >
          {{ cat.label }}
        </button>
      </div>

      <!-- Count -->
      <div class="text-sm text-gray-600">
        {{ familyCount }} families
      </div>
    </div>

    <!-- Loading -->
    <div v-if="loading" class="flex-1 flex items-center justify-center">
      <div class="text-center">
        <svg class="animate-spin h-8 w-8 text-blue-600 mx-auto mb-2" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z" />
        </svg>
        <p class="text-gray-600">Loading families...</p>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredFamilies.length === 0" class="flex-1 flex items-center justify-center">
      <div class="text-center text-gray-500">
        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
        </svg>
        <p class="text-lg font-medium mb-1">No families found</p>
        <p class="text-sm">Try adjusting your filters or create a new family</p>
      </div>
    </div>

    <!-- Families Grid -->
    <div v-else class="flex-1 overflow-y-auto">
      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <FamilyCard
          v-for="family in filteredFamilies"
          :key="family.id"
          :family="family"
          :show-delete="!family.is_public"
          @load="handleLoadFamily"
          @delete="handleDeleteFamily"
          @view-details="handleViewDetails"
          @export="handleExportFamily"
        />
      </div>
    </div>

    <!-- Variant Selector Modal -->
    <div
      v-if="showVariantSelector && selectedFamily"
      class="fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center"
      @click.self="showVariantSelector = false"
    >
      <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full mx-4">
        <h3 class="text-xl font-bold text-gray-900 mb-4">
          Select Layout Variant
        </h3>
        
        <p class="text-sm text-gray-600 mb-4">
          Choose a layout variant for <strong>{{ selectedFamily.name }}</strong>
        </p>

        <div class="space-y-2 mb-6">
          <label
            v-for="variant in selectedFamily.layout_variants"
            :key="variant"
            class="flex items-center p-3 border rounded-md cursor-pointer hover:bg-gray-50 transition-colors"
            :class="selectedVariant === variant ? 'border-blue-500 bg-blue-50' : 'border-gray-200'"
          >
            <input
              v-model="selectedVariant"
              type="radio"
              :value="variant"
              class="mr-3 text-blue-600"
            />
            <div>
              <div class="font-medium text-gray-900">{{ variant }}</div>
              <div class="text-xs text-gray-500 capitalize">
                {{ variant.replace(/-/g, ' ') }}
              </div>
            </div>
          </label>
        </div>

        <div class="flex gap-3">
          <button
            @click="showVariantSelector = false"
            class="flex-1 px-4 py-2 text-sm font-medium text-gray-700 bg-gray-100 rounded-md hover:bg-gray-200"
          >
            Cancel
          </button>
          <button
            @click="handleVariantSelected"
            class="flex-1 px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700"
          >
            Load Template
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
