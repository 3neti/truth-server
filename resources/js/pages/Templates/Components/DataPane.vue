<script setup lang="ts">
import { ref, computed, watch } from 'vue'

const props = defineProps<{
  modelValue: Record<string, any>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, any>]
}>()

const jsonString = ref(JSON.stringify(props.modelValue, null, 2))
const fontSize = ref(13)
const validationError = ref<string | null>(null)

watch(() => props.modelValue, (newVal) => {
  jsonString.value = JSON.stringify(newVal, null, 2)
})

watch(jsonString, (newVal) => {
  try {
    const parsed = JSON.parse(newVal)
    emit('update:modelValue', parsed)
    validationError.value = null
  } catch (e: any) {
    validationError.value = e.message
  }
})

const isValid = computed(() => validationError.value === null)

function increaseFontSize() {
  if (fontSize.value < 24) fontSize.value++
}

function decreaseFontSize() {
  if (fontSize.value > 8) fontSize.value--
}

function resetFontSize() {
  fontSize.value = 13
}

function formatJson() {
  try {
    const parsed = JSON.parse(jsonString.value)
    jsonString.value = JSON.stringify(parsed, null, 2)
    validationError.value = null
  } catch (e: any) {
    validationError.value = 'Invalid JSON - cannot format'
  }
}

function minifyJson() {
  try {
    const parsed = JSON.parse(jsonString.value)
    jsonString.value = JSON.stringify(parsed)
    validationError.value = null
  } catch (e: any) {
    validationError.value = 'Invalid JSON - cannot minify'
  }
}
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3 pb-3 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-gray-900">JSON Data</h2>
        <span
          v-if="!isValid"
          class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded"
        >
          Invalid JSON
        </span>
        <span
          v-else
          class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded"
        >
          Valid
        </span>
      </div>
      <div class="flex items-center gap-2">
        <button
          @click="formatJson"
          class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          title="Format JSON"
        >
          Format
        </button>
        <button
          @click="minifyJson"
          class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          title="Minify JSON"
        >
          Minify
        </button>
        <div class="w-px h-4 bg-gray-300" />
        <button
          @click="decreaseFontSize"
          class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          title="Decrease font size"
        >
          A-
        </button>
        <span class="text-xs text-gray-500">{{ fontSize }}px</span>
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

    <!-- Validation Error -->
    <div
      v-if="validationError"
      class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-xs rounded"
    >
      <strong>JSON Error:</strong> {{ validationError }}
    </div>

    <!-- Editor -->
    <textarea
      v-model="jsonString"
      class="data-editor flex-1 w-full p-4 font-mono border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
      :class="{ 'border-red-300': !isValid, 'border-green-300': isValid }"
      :style="{ fontSize: `${fontSize}px` }"
      placeholder="Enter JSON data here...

Example:
{
  &quot;title&quot;: &quot;2025 General Election&quot;,
  &quot;id&quot;: &quot;BAL-2025-001&quot;,
  &quot;items&quot;: [
    {
      &quot;code&quot;: &quot;ITEM1&quot;,
      &quot;name&quot;: &quot;Item One&quot;
    },
    {
      &quot;code&quot;: &quot;ITEM2&quot;,
      &quot;name&quot;: &quot;Item Two&quot;
    }
  ]
}"
      spellcheck="false"
    />

    <!-- Help -->
    <div class="mt-3 p-2 bg-green-50 border border-green-200 rounded text-xs text-green-800">
      <strong>Tip:</strong> This data will be used to fill in the Handlebars template variables.
    </div>
  </div>
</template>

<style scoped>
.data-editor {
  tab-size: 2;
}

.data-editor::placeholder {
  color: #9ca3af;
  font-size: 0.875rem;
  line-height: 1.5;
}
</style>
