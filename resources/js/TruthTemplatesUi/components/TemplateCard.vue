<script setup lang="ts">
import { computed } from 'vue'

interface Template {
  id: number
  name: string
  description: string | null
  category: string
  is_public: boolean
  user_id: number | null
  created_at: string
  updated_at: string
}

const props = defineProps<{
  template: Template
}>()

const emit = defineEmits<{
  load: [template: Template]
  view: [template: Template]
  delete: [template: Template]
  duplicate: [template: Template]
  update: [template: Template]
}>()

const categoryColors: Record<string, string> = {
  ballot: 'bg-blue-100 text-blue-700',
  survey: 'bg-green-100 text-green-700',
  test: 'bg-purple-100 text-purple-700',
  questionnaire: 'bg-orange-100 text-orange-700',
}

const categoryColor = computed(() => {
  return categoryColors[props.template.category] || 'bg-gray-100 text-gray-700'
})

const formattedCreatedDate = computed(() => {
  return new Date(props.template.created_at).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
})

const formattedUpdatedDate = computed(() => {
  return new Date(props.template.updated_at).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
})

const wasRecentlyModified = computed(() => {
  const created = new Date(props.template.created_at)
  const updated = new Date(props.template.updated_at)
  // Consider modified if updated_at is more than 1 minute after created_at
  return (updated.getTime() - created.getTime()) > 60000
})

const truncatedDescription = computed(() => {
  if (!props.template.description) return 'No description'
  return props.template.description.length > 120
    ? props.template.description.substring(0, 120) + '...'
    : props.template.description
})
</script>

<template>
  <div
    class="bg-white border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow cursor-pointer"
    @click="emit('view', template)"
  >
    <div class="flex items-start justify-between mb-3">
      <div class="flex-1">
        <h3 class="text-lg font-semibold text-gray-900 mb-1">
          {{ template.name }}
        </h3>
        <p class="text-sm text-gray-600">
          {{ truncatedDescription }}
        </p>
      </div>
      <span
        class="ml-3 px-2 py-1 text-xs font-medium rounded capitalize"
        :class="categoryColor"
      >
        {{ template.category }}
      </span>
    </div>

    <div class="flex items-center justify-between text-xs text-gray-500">
      <div class="flex items-center gap-3 flex-wrap">
        <span v-if="template.is_public" class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
          </svg>
          Public
        </span>
        <span v-else class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Private
        </span>
        <span class="flex items-center gap-1">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          Created {{ formattedCreatedDate }}
        </span>
        <span v-if="wasRecentlyModified" class="flex items-center gap-1 text-orange-600">
          <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
          </svg>
          Updated {{ formattedUpdatedDate }}
        </span>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click.stop="emit('load', template)"
          class="px-3 py-1 text-xs font-medium text-blue-700 bg-blue-50 border border-blue-200 rounded hover:bg-blue-100"
        >
          Load
        </button>
        <button
          @click.stop="emit('duplicate', template)"
          class="px-3 py-1 text-xs font-medium text-green-700 bg-green-50 border border-green-200 rounded hover:bg-green-100"
          title="Duplicate this template"
        >
          Copy
        </button>
        <button
          v-if="template.user_id"
          @click.stop="emit('update', template)"
          class="px-3 py-1 text-xs font-medium text-purple-700 bg-purple-50 border border-purple-200 rounded hover:bg-purple-100"
          title="Update this template"
        >
          Update
        </button>
        <button
          v-if="template.user_id"
          @click.stop="emit('delete', template)"
          class="px-3 py-1 text-xs font-medium text-red-700 bg-red-50 border border-red-200 rounded hover:bg-red-100"
        >
          Delete
        </button>
      </div>
    </div>
  </div>
</template>
