<script setup lang="ts">
import { computed } from 'vue'

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
  user?: {
    id: number
    name: string
  }
}

const props = defineProps<{
  family: TemplateFamily
  showDelete?: boolean
}>()

const emit = defineEmits<{
  load: [family: TemplateFamily]
  delete: [family: TemplateFamily]
  viewDetails: [family: TemplateFamily]
}>()

const categoryColor = computed(() => {
  const colors: Record<string, string> = {
    ballot: 'bg-blue-100 text-blue-700',
    survey: 'bg-green-100 text-green-700',
    test: 'bg-purple-100 text-purple-700',
    questionnaire: 'bg-orange-100 text-orange-700',
  }
  return colors[props.family.category] || 'bg-gray-100 text-gray-700'
})

const truncatedDescription = computed(() => {
  if (!props.family.description) return 'No description'
  return props.family.description.length > 120
    ? props.family.description.substring(0, 120) + '...'
    : props.family.description
})

const formattedDate = computed(() => {
  return new Date(props.family.created_at).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
})

const variantsList = computed(() => {
  return props.family.layout_variants.join(', ')
})
</script>

<template>
  <div
    class="bg-white rounded-lg border border-gray-200 p-4 hover:shadow-md transition-shadow cursor-pointer"
    @click="emit('viewDetails', family)"
  >
    <!-- Header -->
    <div class="flex items-start justify-between mb-2">
      <h3 class="text-lg font-semibold text-gray-900 flex-1 mr-2">
        {{ family.name }}
      </h3>
      <span
        :class="categoryColor"
        class="px-2 py-1 text-xs font-medium rounded-full whitespace-nowrap"
      >
        {{ family.category }}
      </span>
    </div>

    <!-- Description -->
    <p class="text-sm text-gray-600 mb-3">
      {{ truncatedDescription }}
    </p>

    <!-- Variants -->
    <div class="mb-3">
      <span class="text-xs font-medium text-gray-500">Variants:</span>
      <span class="text-xs text-gray-700 ml-1">{{ variantsList }}</span>
    </div>

    <!-- Meta Info -->
    <div class="flex items-center justify-between text-xs text-gray-500 mb-3">
      <div class="flex items-center gap-3">
        <span v-if="family.is_public" class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path d="M10 2a8 8 0 100 16 8 8 0 000-16zM8 11a1 1 0 112 0v3a1 1 0 11-2 0v-3zm1-6a1 1 0 011 1v1a1 1 0 11-2 0V6a1 1 0 011-1z" />
          </svg>
          Public
        </span>
        <span v-else class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M5 9V7a5 5 0 0110 0v2a2 2 0 012 2v5a2 2 0 01-2 2H5a2 2 0 01-2-2v-5a2 2 0 012-2zm8-2v2H7V7a3 3 0 016 0z" clip-rule="evenodd" />
          </svg>
          Private
        </span>
        <span>v{{ family.version }}</span>
        <span>{{ family.variants_count }} variant(s)</span>
      </div>
      <span>{{ formattedDate }}</span>
    </div>

    <!-- Actions -->
    <div class="flex gap-2" @click.stop>
      <button
        @click="emit('load', family)"
        class="flex-1 px-3 py-2 text-sm font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded-md hover:bg-blue-100"
      >
        Load
      </button>
      <button
        v-if="showDelete"
        @click="emit('delete', family)"
        class="px-3 py-2 text-sm font-medium text-red-700 bg-red-50 border border-red-200 rounded-md hover:bg-red-100"
      >
        Delete
      </button>
    </div>
  </div>
</template>
