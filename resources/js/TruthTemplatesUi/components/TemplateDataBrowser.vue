<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { useTemplateDataStore, type TemplateData } from '@/TruthTemplatesUi/stores/templateData'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Search, FileText, Trash2, Eye } from 'lucide-vue-next'

const emit = defineEmits<{
  'select': [dataFile: TemplateData]
  'close': []
}>()

const store = useTemplateDataStore()
const searchQuery = ref('')
const selectedCategory = ref('all')

onMounted(() => {
  store.fetchTemplateDatas()
})

const filteredDataFiles = computed(() => {
  let files = store.templateData

  // Filter by category
  if (selectedCategory.value !== 'all') {
    files = files.filter(df => df.category === selectedCategory.value)
  }

  // Filter by search
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    files = files.filter(df => 
      df.name.toLowerCase().includes(query) ||
      df.description?.toLowerCase().includes(query) ||
      df.template_ref?.toLowerCase().includes(query)
    )
  }

  return files
})

const categories = computed(() => {
  const cats = new Set(store.templateData.map(df => df.category))
  return ['all', ...Array.from(cats)]
})

function selectDataFile(dataFile: TemplateData) {
  emit('select', dataFile)
}

async function deleteDataFile(dataFile: TemplateData, event: Event) {
  event.stopPropagation()
  
  if (!confirm(`Delete "${dataFile.name}"?`)) {
    return
  }

  try {
    await store.deleteTemplateData(dataFile.id)
  } catch (e) {
    alert('Failed to delete data file')
  }
}
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="border-b pb-4 mb-4">
      <h3 class="text-lg font-semibold mb-3">Browse Data Files</h3>
      
      <!-- Search -->
      <div class="relative mb-3">
        <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
        <Input
          v-model="searchQuery"
          placeholder="Search by name, description, or template..."
          class="pl-9"
        />
      </div>

      <!-- Category Filter -->
      <div class="flex gap-2 flex-wrap">
        <button
          v-for="category in categories"
          :key="category"
          @click="selectedCategory = category"
          :class="[
            'px-3 py-1 text-sm rounded-md transition-colors',
            selectedCategory === category
              ? 'bg-blue-600 text-white'
              : 'bg-gray-100 text-gray-700 hover:bg-gray-200'
          ]"
        >
          {{ category }}
        </button>
      </div>
    </div>

    <!-- Loading -->
    <div v-if="store.loading" class="flex-1 flex items-center justify-center">
      <div class="text-gray-500">Loading data files...</div>
    </div>

    <!-- Empty State -->
    <div v-else-if="filteredDataFiles.length === 0" class="flex-1 flex items-center justify-center">
      <div class="text-center text-gray-500">
        <FileText class="h-12 w-12 mx-auto mb-2 text-gray-400" />
        <p class="text-sm">{{ searchQuery ? 'No matching data files' : 'No data files yet' }}</p>
      </div>
    </div>

    <!-- Data Files List -->
    <div v-else class="flex-1 overflow-y-auto space-y-2">
      <div
        v-for="dataFile in filteredDataFiles"
        :key="dataFile.id"
        @click="selectDataFile(dataFile)"
        class="p-4 border rounded-lg hover:bg-gray-50 cursor-pointer transition-colors group"
      >
        <div class="flex items-start justify-between">
          <div class="flex-1">
            <div class="flex items-center gap-2 mb-1">
              <h4 class="font-medium text-gray-900">{{ dataFile.name }}</h4>
              <span
                v-if="!dataFile.is_public"
                class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded"
              >
                Private
              </span>
              <span
                class="px-2 py-0.5 text-xs bg-blue-100 text-blue-700 rounded"
              >
                {{ dataFile.category }}
              </span>
            </div>
            
            <p v-if="dataFile.description" class="text-sm text-gray-600 mb-2">
              {{ dataFile.description }}
            </p>
            
            <div class="flex items-center gap-4 text-xs text-gray-500">
              <span v-if="dataFile.template_ref">
                Template: {{ dataFile.template_ref }}
              </span>
              <span>{{ dataFile.formatted_date }}</span>
              <span v-if="dataFile.user">by {{ dataFile.user.name }}</span>
            </div>
          </div>

          <div class="flex gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <Button
              variant="ghost"
              size="sm"
              @click.stop="selectDataFile(dataFile)"
              title="View/Edit"
            >
              <Eye class="h-4 w-4" />
            </Button>
            <Button
              variant="ghost"
              size="sm"
              @click="(e) => deleteDataFile(dataFile, e)"
              title="Delete"
              class="text-red-500 hover:text-red-700"
            >
              <Trash2 class="h-4 w-4" />
            </Button>
          </div>
        </div>
      </div>
    </div>

    <!-- Footer -->
    <div class="border-t pt-4 mt-4 flex justify-between items-center">
      <div class="text-sm text-gray-600">
        {{ filteredDataFiles.length }} {{ filteredDataFiles.length === 1 ? 'file' : 'files' }}
      </div>
      <Button variant="outline" size="sm" @click="emit('close')">
        Close
      </Button>
    </div>
  </div>
</template>
