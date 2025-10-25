<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue'
import { Input } from '@/components/ui/input'
import { Label } from '@/components/ui/label'
import { Button } from '@/components/ui/button'
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogTrigger,
} from '@/components/ui/dialog'
import { Search, FileText, Folder, Check } from 'lucide-vue-next'

interface Template {
  id: number
  name: string
  description?: string
  category: string
  family_id?: number
  family?: {
    name: string
    slug: string
  }
  layout_variant?: string
  version?: string
}

interface Props {
  modelValue?: string
  placeholder?: string
  label?: string
}

interface Emits {
  (e: 'update:modelValue', value: string): void
}

const props = withDefaults(defineProps<Props>(), {
  modelValue: '',
  placeholder: 'Search templates...',
  label: 'Template Reference',
})

const emit = defineEmits<Emits>()

// State
const open = ref(false)
const templates = ref<Template[]>([])
const loading = ref(false)
const searchQuery = ref('')

// Computed
const templateRef = computed({
  get: () => props.modelValue,
  set: (value: string) => emit('update:modelValue', value),
})

const filteredTemplates = computed(() => {
  if (!searchQuery.value) return templates.value
  
  const query = searchQuery.value.toLowerCase()
  return templates.value.filter((t) => {
    return (
      t.name.toLowerCase().includes(query) ||
      t.description?.toLowerCase().includes(query) ||
      t.category.toLowerCase().includes(query) ||
      t.family?.name.toLowerCase().includes(query) ||
      t.layout_variant?.toLowerCase().includes(query)
    )
  })
})

const selectedTemplate = computed(() => {
  if (!templateRef.value) return null
  
  // Try to parse the template_ref
  const ref = templateRef.value
  
  // Match local:family/variant or local:id
  if (ref.startsWith('local:')) {
    const parts = ref.substring(6).split('/')
    
    if (parts.length === 2) {
      // family/variant format
      const [familySlug, variant] = parts
      return templates.value.find(
        (t) => t.family?.slug === familySlug && t.layout_variant === variant
      )
    } else if (parts.length === 1 && !isNaN(parseInt(parts[0]))) {
      // ID format
      const id = parseInt(parts[0])
      return templates.value.find((t) => t.id === id)
    }
  }
  
  return null
})

const displayValue = computed(() => {
  if (selectedTemplate.value) {
    return formatTemplateDisplay(selectedTemplate.value)
  }
  return templateRef.value || 'Select template...'
})

// Methods
async function fetchTemplates() {
  loading.value = true
  try {
    const response = await fetch('/api/templates/library?with_families=1')
    const data = await response.json()
    
    if (data.success && data.templates) {
      templates.value = data.templates
    }
  } catch (e) {
    console.error('Failed to fetch templates:', e)
  } finally {
    loading.value = false
  }
}

function selectTemplate(template: Template) {
  // Generate template_ref based on family or ID
  if (template.family_id && template.family && template.layout_variant) {
    templateRef.value = `local:${template.family.slug}/${template.layout_variant}`
  } else {
    templateRef.value = `local:${template.id}`
  }
  
  open.value = false
}

function formatTemplateDisplay(template: Template): string {
  if (template.family && template.layout_variant) {
    return `${template.family.name} - ${template.layout_variant}`
  }
  return template.name
}

function formatTemplateRef(template: Template): string {
  if (template.family_id && template.family && template.layout_variant) {
    return `local:${template.family.slug}/${template.layout_variant}`
  }
  return `local:${template.id}`
}

function clearSelection() {
  templateRef.value = ''
}

// Lifecycle
onMounted(() => {
  fetchTemplates()
})

watch(open, (newVal) => {
  if (newVal) {
    searchQuery.value = ''
  }
})
</script>

<template>
  <div class="space-y-2">
    <Label v-if="label">{{ label }}</Label>
    
    <div class="flex gap-2">
      <Dialog v-model:open="open">
        <DialogTrigger as-child>
          <Button
            variant="outline"
            class="w-full justify-between"
          >
            <span class="truncate">{{ displayValue }}</span>
            <Search class="ml-2 h-4 w-4 shrink-0 opacity-50" />
          </Button>
        </DialogTrigger>
        <DialogContent class="max-w-2xl max-h-[80vh]">
          <DialogHeader>
            <DialogTitle>Select Template</DialogTitle>
          </DialogHeader>
          
          <div class="space-y-4">
            <div class="relative">
              <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400" />
              <Input
                v-model="searchQuery"
                :placeholder="placeholder"
                class="pl-10"
              />
            </div>
            
            <div class="max-h-[50vh] overflow-y-auto space-y-1">
              <div v-if="loading" class="text-center py-8 text-gray-500">
                Loading templates...
              </div>
              
              <div v-else-if="filteredTemplates.length === 0" class="text-center py-8 text-gray-500">
                No templates found.
              </div>
              
              <button
                v-for="template in filteredTemplates"
                :key="template.id"
                @click="selectTemplate(template)"
                class="w-full text-left p-3 rounded-lg border hover:bg-gray-50 transition-colors"
                :class="{
                  'bg-blue-50 border-blue-300': selectedTemplate?.id === template.id,
                  'border-gray-200': selectedTemplate?.id !== template.id,
                }"
              >
                <div class="flex items-start gap-3">
                  <div class="mt-0.5">
                    <Folder v-if="template.family" class="h-5 w-5 text-blue-500" />
                    <FileText v-else class="h-5 w-5 text-gray-500" />
                  </div>
                  
                  <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                      <p class="font-medium truncate">
                        {{ formatTemplateDisplay(template) }}
                      </p>
                      <span class="text-xs bg-gray-100 px-2 py-0.5 rounded shrink-0">
                        {{ template.category }}
                      </span>
                    </div>
                    
                    <p class="text-xs text-gray-500 mt-1 font-mono">
                      {{ formatTemplateRef(template) }}
                    </p>
                    
                    <p v-if="template.description" class="text-xs text-gray-600 mt-1">
                      {{ template.description }}
                    </p>
                  </div>
                  
                  <Check
                    v-if="selectedTemplate?.id === template.id"
                    class="h-5 w-5 text-blue-600 shrink-0 mt-0.5"
                  />
                </div>
              </button>
            </div>
          </div>
        </DialogContent>
      </Dialog>
      
      <Button
        v-if="templateRef"
        variant="outline"
        size="icon"
        @click="clearSelection"
        title="Clear selection"
      >
        ×
      </Button>
    </div>
    
    <!-- Manual input fallback -->
    <details class="text-xs text-gray-600">
      <summary class="cursor-pointer hover:text-gray-900">Manual input</summary>
      <Input
        v-model="templateRef"
        placeholder="e.g., local:ballot/vertical or github:org/repo/file.hbs@v1.0"
        class="mt-2"
      />
    </details>
    
    <!-- Selected template info -->
    <div v-if="selectedTemplate" class="text-xs text-gray-600 bg-blue-50 p-2 rounded border border-blue-200">
      <p class="font-medium text-blue-900">{{ selectedTemplate.name }}</p>
      <p v-if="selectedTemplate.description" class="mt-1">{{ selectedTemplate.description }}</p>
      <p class="mt-1 font-mono text-blue-700">{{ templateRef }}</p>
    </div>
  </div>
</template>
