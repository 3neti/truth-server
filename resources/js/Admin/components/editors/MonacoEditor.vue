<script setup lang="ts">
import { ref, onMounted, watch, onBeforeUnmount } from 'vue'
import * as monaco from 'monaco-editor'
import type { editor } from 'monaco-editor'

const props = withDefaults(defineProps<{
  modelValue: string
  language?: string
  theme?: 'vs' | 'vs-dark' | 'hc-black'
  readOnly?: boolean
  minimap?: boolean
  lineNumbers?: 'on' | 'off' | 'relative'
  wordWrap?: 'on' | 'off' | 'wordWrapColumn' | 'bounded'
  fontSize?: number
  tabSize?: number
}>(), {
  language: 'handlebars',
  theme: 'vs-dark',
  readOnly: false,
  minimap: true,
  lineNumbers: 'on',
  wordWrap: 'off',
  fontSize: 14,
  tabSize: 2,
})

const emit = defineEmits<{
  'update:modelValue': [value: string]
  'change': [value: string]
}>()

const editorContainer = ref<HTMLElement | null>(null)
let editorInstance: editor.IStandaloneCodeEditor | null = null

onMounted(() => {
  if (!editorContainer.value) return

  // Register Handlebars language if not already registered
  if (!monaco.languages.getLanguages().find(lang => lang.id === 'handlebars')) {
    monaco.languages.register({ id: 'handlebars' })
    
    // Define Handlebars syntax highlighting
    monaco.languages.setMonarchTokensProvider('handlebars', {
      tokenizer: {
        root: [
          [/\{\{!--/, 'comment.block.start.handlebars', '@commentBlock'],
          [/\{\{!/, 'comment.start.handlebars', '@comment'],
          [/\{\{\{/, 'delimiter.start.handlebars', '@tripleBlock'],
          [/\{\{/, 'delimiter.start.handlebars', '@block'],
        ],
        commentBlock: [
          [/--\}\}/, 'comment.block.end.handlebars', '@pop'],
          [/./, 'comment.content.handlebars'],
        ],
        comment: [
          [/\}\}/, 'comment.end.handlebars', '@pop'],
          [/./, 'comment.content.handlebars'],
        ],
        tripleBlock: [
          [/\}\}\}/, 'delimiter.end.handlebars', '@pop'],
          [/[#/]?\w+/, 'keyword.helper.handlebars'],
          [/"([^"\\]|\\.)*"/, 'string.handlebars'],
          [/'([^'\\]|\\.)*'/, 'string.handlebars'],
          [/[=!<>]+/, 'operator.handlebars'],
        ],
        block: [
          [/\}\}/, 'delimiter.end.handlebars', '@pop'],
          [/[#/]?\w+/, 'keyword.helper.handlebars'],
          [/"([^"\\]|\\.)*"/, 'string.handlebars'],
          [/'([^'\\]|\\.)*'/, 'string.handlebars'],
          [/[=!<>]+/, 'operator.handlebars'],
        ],
      },
    })
  }

  // Create editor instance
  editorInstance = monaco.editor.create(editorContainer.value, {
    value: props.modelValue,
    language: props.language,
    theme: props.theme,
    readOnly: props.readOnly,
    minimap: { enabled: props.minimap },
    lineNumbers: props.lineNumbers,
    wordWrap: props.wordWrap,
    fontSize: props.fontSize,
    tabSize: props.tabSize,
    automaticLayout: true,
    scrollBeyondLastLine: false,
    renderWhitespace: 'selection',
    formatOnPaste: true,
    formatOnType: true,
  })

  // Listen for content changes
  editorInstance.onDidChangeModelContent(() => {
    const value = editorInstance?.getValue() || ''
    emit('update:modelValue', value)
    emit('change', value)
  })
})

// Watch for external changes to modelValue
watch(() => props.modelValue, (newValue) => {
  if (editorInstance && editorInstance.getValue() !== newValue) {
    editorInstance.setValue(newValue)
  }
})

// Watch for language changes
watch(() => props.language, (newLanguage) => {
  if (editorInstance) {
    const model = editorInstance.getModel()
    if (model) {
      monaco.editor.setModelLanguage(model, newLanguage)
    }
  }
})

// Watch for theme changes
watch(() => props.theme, (newTheme) => {
  monaco.editor.setTheme(newTheme)
})

onBeforeUnmount(() => {
  editorInstance?.dispose()
})

// Expose methods
defineExpose({
  focus: () => editorInstance?.focus(),
  getEditor: () => editorInstance,
})
</script>

<template>
  <div ref="editorContainer" class="monaco-editor-wrapper" />
</template>

<style scoped>
.monaco-editor-wrapper {
  width: 100%;
  height: 100%;
  min-height: 200px;
}
</style>
