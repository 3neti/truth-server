<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import axios from '@/lib/axios'

const props = defineProps<{
  modelValue: string
  selectedTemplate?: {
    id?: number
    name?: string
    template_ref?: string
  } | null
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'template-selected': [template: any]
}>()

const localValue = ref(props.modelValue)
const fontSize = ref(13)
const editMode = ref(false)
const templates = ref<any[]>([])
const loadingTemplates = ref(false)

watch(() => props.modelValue, (newVal) => {
  localValue.value = newVal
})

watch(localValue, (newVal) => {
  emit('update:modelValue', newVal)
})

function increaseFontSize() {
  if (fontSize.value < 24) fontSize.value++
}

function decreaseFontSize() {
  if (fontSize.value > 8) fontSize.value--
}

function resetFontSize() {
  fontSize.value = 13
}

function insertVariable(variable: string) {
  const textarea = document.querySelector('.template-editor') as HTMLTextAreaElement
  if (!textarea) return

  const start = textarea.selectionStart
  const end = textarea.selectionEnd
  const text = localValue.value
  const before = text.substring(0, start)
  const after = text.substring(end)

  localValue.value = before + `{{${variable}}}` + after

  // Set cursor position after inserted text
  setTimeout(() => {
    textarea.selectionStart = textarea.selectionEnd = start + variable.length + 4
    textarea.focus()
  }, 0)
}

const quickInserts = [
  { label: '{{title}}', variable: 'title' },
  { label: '{{id}}', variable: 'id' },
  { label: '{{#each}}', variable: 'each items' },
  { label: '{{#if}}', variable: 'if condition' },
]

const placeholderText = `Enter Handlebars template here...

Example:
{
  "document": {
    "title": "{{title}}",
    "unique_id": "{{id}}"
  },
  "sections": [
    {{#each items}}
    {
      "code": "{{code}}",
      "title": "{{name}}"
    }{{#unless @last}},{{/unless}}
    {{/each}}
  ]
}`

const syntaxExamples = [
  '{{variable}}',
  '{{#each items}}...{{/each}}',
  '{{#if condition}}...{{/if}}',
  '{{#unless @last}},{{/unless}}',
]

onMounted(async () => {
  await loadTemplates()
})

async function loadTemplates() {
  loadingTemplates.value = true
  try {
    const response = await axios.get('/api/truth-templates/templates')
    templates.value = response.data.templates || []
  } catch (e) {
    console.error('Failed to load templates:', e)
  } finally {
    loadingTemplates.value = false
  }
}

function selectTemplate(template: any) {
  emit('template-selected', template)
}

function toggleEditMode() {
  editMode.value = !editMode.value
}
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Template Selector -->
    <div class="mb-3 p-3 bg-blue-50 border border-blue-200 rounded">
      <label class="block text-sm font-medium text-gray-700 mb-2">Select Template from Registry:</label>
      <select 
        @change="selectTemplate(templates.find(t => t.id === Number(($event.target as HTMLSelectElement).value)))"
        class="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500"
        :disabled="loadingTemplates"
      >
        <option value="">-- Select a template --</option>
        <option v-for="template in templates" :key="template.id" :value="template.id">
          {{ template.name }} ({{ template.category }})
        </option>
      </select>
      
      <!-- Show selected template ref -->
      <div v-if="selectedTemplate" class="mt-2 p-2 bg-white border border-blue-300 rounded">
        <div class="flex items-center gap-2">
          <span class="text-xs font-mono text-blue-700">📋 {{ selectedTemplate.template_ref }}</span>
        </div>
      </div>
    </div>

    <!-- Header -->
    <div class="flex items-center justify-between mb-3 pb-3 border-b">
      <div class="flex items-center gap-3">
        <h2 class="text-lg font-semibold text-gray-900">Handlebars Template</h2>
        <!-- View/Edit Mode Toggle -->
        <div class="flex gap-1 bg-gray-100 rounded p-1">
          <button
            @click="editMode = false"
            class="px-3 py-1 text-xs font-medium rounded transition-colors"
            :class="{
              'bg-white text-gray-900 shadow-sm': !editMode,
              'text-gray-600 hover:text-gray-900': editMode,
            }"
          >
            View
          </button>
          <button
            @click="editMode = true"
            class="px-3 py-1 text-xs font-medium rounded transition-colors"
            :class="{
              'bg-white text-gray-900 shadow-sm': editMode,
              'text-gray-600 hover:text-gray-900': !editMode,
            }"
          >
            Edit
          </button>
        </div>
      </div>
      <div class="flex items-center gap-2">
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

    <!-- Quick Insert Variables (Edit Mode Only) -->
    <div v-if="editMode" class="mb-3 p-2 bg-gray-50 rounded text-xs">
      <div class="font-medium text-gray-700 mb-2">Quick Insert:</div>
      <div class="flex flex-wrap gap-1">
        <button
          v-for="insert in quickInserts"
          :key="insert.variable"
          @click="insertVariable(insert.variable)"
          class="px-2 py-1 bg-white border border-gray-200 rounded hover:bg-gray-100 text-gray-700"
          v-text="insert.label"
        />
      </div>
    </div>

    <!-- Editor -->
    <textarea
      v-model="localValue"
      :readonly="!editMode"
      class="template-editor flex-1 w-full p-4 font-mono border border-gray-300 rounded-md resize-none"
      :class="{
        'bg-gray-50 cursor-default': !editMode,
        'focus:ring-2 focus:ring-blue-500 focus:border-transparent': editMode
      }"
      :style="{ fontSize: `${fontSize}px` }"
      :placeholder="editMode ? placeholderText : 'Select a template from the dropdown above'"
      spellcheck="false"
    />

    <!-- Syntax Help -->
    <div class="mt-3 p-2 bg-blue-50 border border-blue-200 rounded text-xs text-blue-800">
      <strong>Handlebars Syntax:</strong>
      <code v-for="example in syntaxExamples" :key="example" class="ml-2" v-text="example" />
    </div>
  </div>
</template>

<style scoped>
.template-editor {
  tab-size: 2;
}

.template-editor::placeholder {
  color: #9ca3af;
  font-size: 0.875rem;
  line-height: 1.5;
}
</style>
