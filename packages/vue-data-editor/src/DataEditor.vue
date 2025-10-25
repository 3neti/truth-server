<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { Code, FormInput, Plus } from 'lucide-vue-next'
import DataEditorField from './DataEditorField.vue'

const props = defineProps<{
  modelValue: Record<string, any>
}>()

const emit = defineEmits<{
  'update:modelValue': [value: Record<string, any>]
}>()

// View mode: 'form' or 'json'
const viewMode = ref<'form' | 'json'>('form')

// Local data state
const formData = ref<Record<string, any>>({ ...props.modelValue })
const jsonString = ref(JSON.stringify(props.modelValue, null, 2))
const jsonError = ref<string | null>(null)

// Watch JSON string changes - only when in JSON view
watch(jsonString, (newVal) => {
  if (viewMode.value === 'json') {
    try {
      const parsed = JSON.parse(newVal)
      formData.value = parsed
      emit('update:modelValue', parsed)
      jsonError.value = null
    } catch (e: any) {
      jsonError.value = e.message
    }
  }
})

const isJsonValid = computed(() => jsonError.value === null)

function toggleViewMode() {
  if (viewMode.value === 'form') {
    // Switching to JSON view - sync the JSON string
    jsonString.value = JSON.stringify(formData.value, null, 2)
    viewMode.value = 'json'
  } else {
    // Switching to form view - validate and sync
    try {
      const parsed = JSON.parse(jsonString.value)
      formData.value = parsed
      jsonError.value = null
      viewMode.value = 'form'
    } catch (e: any) {
      // Don't switch if JSON is invalid
      jsonError.value = e.message
    }
  }
}

function formatJson() {
  try {
    const parsed = JSON.parse(jsonString.value)
    jsonString.value = JSON.stringify(parsed, null, 2)
    jsonError.value = null
  } catch {
    jsonError.value = 'Invalid JSON - cannot format'
  }
}

function minifyJson() {
  try {
    const parsed = JSON.parse(jsonString.value)
    jsonString.value = JSON.stringify(parsed)
    jsonError.value = null
  } catch {
    jsonError.value = 'Invalid JSON - cannot minify'
  }
}

function updateTopLevelField(key: string, value: any) {
  formData.value = { ...formData.value, [key]: value }
  emit('update:modelValue', formData.value)
}

function removeTopLevelField(key: string) {
  const newData = { ...formData.value }
  delete newData[key]
  formData.value = newData
  emit('update:modelValue', formData.value)
}

const newFieldKey = ref('')
const newFieldType = ref<'string' | 'number' | 'boolean' | 'object' | 'array'>('string')

function addTopLevelField() {
  if (newFieldKey.value && !(newFieldKey.value in formData.value)) {
    let defaultValue: any
    switch (newFieldType.value) {
      case 'string':
        defaultValue = ''
        break
      case 'number':
        defaultValue = 0
        break
      case 'boolean':
        defaultValue = false
        break
      case 'object':
        defaultValue = {}
        break
      case 'array':
        defaultValue = []
        break
    }
    formData.value = { ...formData.value, [newFieldKey.value]: defaultValue }
    emit('update:modelValue', formData.value)
    newFieldKey.value = ''
  }
}

const fontSize = ref(13)

function increaseFontSize() {
  if (fontSize.value < 24) fontSize.value++
}

function decreaseFontSize() {
  if (fontSize.value > 8) fontSize.value--
}

function resetFontSize() {
  fontSize.value = 13
}
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3 pb-3 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-gray-900">Data Editor</h2>
        <span
          v-if="viewMode === 'json' && !isJsonValid"
          class="px-2 py-1 text-xs font-medium bg-red-100 text-red-700 rounded"
        >
          Invalid JSON
        </span>
        <span
          v-else-if="viewMode === 'json'"
          class="px-2 py-1 text-xs font-medium bg-green-100 text-green-700 rounded"
        >
          Valid JSON
        </span>
      </div>
      
      <div class="flex items-center gap-2">
        <!-- View Mode Toggle -->
        <button
          type="button"
          @click="toggleViewMode"
          :disabled="viewMode === 'json' && !isJsonValid"
          class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed"
        >
          <FormInput v-if="viewMode === 'json'" class="h-4 w-4 mr-1.5" />
          <Code v-else class="h-4 w-4 mr-1.5" />
          {{ viewMode === 'form' ? 'JSON View' : 'Form View' }}
        </button>

        <!-- JSON-specific controls -->
        <template v-if="viewMode === 'json'">
          <div class="w-px h-4 bg-gray-300" />
          <button
            type="button"
            @click="formatJson"
            class="px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50"
          >
            Format
          </button>
          <button
            type="button"
            @click="minifyJson"
            class="px-3 py-1.5 text-sm font-medium rounded-md border border-gray-300 bg-white hover:bg-gray-50"
          >
            Minify
          </button>
          <div class="w-px h-4 bg-gray-300" />
          <button
            type="button"
            @click="decreaseFontSize"
            class="px-2 py-1.5 text-sm font-medium rounded-md hover:bg-gray-100"
          >
            A-
          </button>
          <span class="text-xs text-gray-500">{{ fontSize }}px</span>
          <button
            type="button"
            @click="increaseFontSize"
            class="px-2 py-1.5 text-sm font-medium rounded-md hover:bg-gray-100"
          >
            A+
          </button>
          <button
            type="button"
            @click="resetFontSize"
            class="px-2 py-1.5 text-sm font-medium rounded-md hover:bg-gray-100"
          >
            Reset
          </button>
        </template>
      </div>
    </div>

    <!-- JSON Error -->
    <div
      v-if="viewMode === 'json' && jsonError"
      class="mb-3 p-2 bg-red-50 border border-red-200 text-red-700 text-xs rounded"
    >
      <strong>JSON Error:</strong> {{ jsonError }}
    </div>

    <!-- Form View -->
    <div v-if="viewMode === 'form'" class="flex-1 overflow-y-auto pr-2">
      <div class="space-y-1">
        <DataEditorField
          v-for="(value, key) in formData"
          :key="String(key)"
          :field-key="String(key)"
          :value="value"
          :depth="0"
          @update:value="(newVal) => updateTopLevelField(String(key), newVal)"
          @remove="removeTopLevelField(String(key))"
        />
      </div>

      <!-- Add new top-level field -->
      <div class="mt-4 p-3 border border-dashed border-gray-300 rounded-lg bg-gray-50">
        <div class="flex gap-2">
          <input
            v-model="newFieldKey"
            placeholder="Field name"
            class="flex-1 h-9 px-3 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
            @keyup.enter="addTopLevelField"
          />
          <select
            v-model="newFieldType"
            class="h-9 px-3 rounded-md border border-gray-300 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
          >
            <option value="string">String</option>
            <option value="number">Number</option>
            <option value="boolean">Boolean</option>
            <option value="object">Object</option>
            <option value="array">Array</option>
          </select>
          <button
            type="button"
            @click="addTopLevelField"
            :disabled="!newFieldKey || newFieldKey in formData"
            class="h-9 px-3 text-sm rounded-md bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed flex items-center gap-1"
          >
            <Plus class="h-4 w-4" />
            Add Field
          </button>
        </div>
      </div>

      <!-- Help tip -->
      <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800">
        <strong>Tip:</strong> Edit your data using form fields. Click the fields to expand nested objects and arrays.
      </div>
    </div>

    <!-- JSON View -->
    <div v-else class="flex-1 flex flex-col">
      <textarea
        v-model="jsonString"
        class="flex-1 w-full p-4 font-mono border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
        :class="{ 'border-red-300': !isJsonValid, 'border-green-300': isJsonValid }"
        :style="{ fontSize: `${fontSize}px` }"
        placeholder="Enter JSON data here..."
        spellcheck="false"
      />

      <!-- Help tip -->
      <div class="mt-3 p-2 bg-green-50 border border-green-200 rounded text-xs text-green-800">
        <strong>Tip:</strong> Edit JSON directly. Switch back to Form View for a guided editing experience.
      </div>
    </div>
  </div>
</template>

<style scoped>
textarea {
  tab-size: 2;
}

textarea::placeholder {
  color: #9ca3af;
  font-size: 0.875rem;
  line-height: 1.5;
}
</style>
