<script setup lang="ts">
import { ref, watch } from 'vue'

const props = defineProps<{
  modelValue: string
}>()

const emit = defineEmits<{
  'update:modelValue': [value: string]
}>()

const localValue = ref(props.modelValue)
const fontSize = ref(13)

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
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Header -->
    <div class="flex items-center justify-between mb-3 pb-3 border-b">
      <h2 class="text-lg font-semibold text-gray-900">Handlebars Template</h2>
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

    <!-- Quick Insert Variables -->
    <div class="mb-3 p-2 bg-gray-50 rounded text-xs">
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
      class="template-editor flex-1 w-full p-4 font-mono border border-gray-300 rounded-md focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
      :style="{ fontSize: `${fontSize}px` }"
      :placeholder="placeholderText"
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
