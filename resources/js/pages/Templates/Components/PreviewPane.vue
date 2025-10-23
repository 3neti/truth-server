<script setup lang="ts">
import { ref, computed } from 'vue'
import type { TemplateSpec } from '@/stores/templates'

const props = defineProps<{
  mergedSpec: TemplateSpec | null
  pdfUrl: string | null
  loading: boolean
  compilationError: string | null
}>()

const activeTab = ref<'spec' | 'pdf'>('spec')
const fontSize = ref(13)
const pdfTimestamp = ref(Date.now())

const mergedSpecJson = computed(() => {
  return props.mergedSpec ? JSON.stringify(props.mergedSpec, null, 2) : ''
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

function refreshPdf() {
  pdfTimestamp.value = Date.now()
}

function downloadSpec() {
  if (!props.mergedSpec) return

  const blob = new Blob([JSON.stringify(props.mergedSpec, null, 2)], {
    type: 'application/json',
  })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `${props.mergedSpec.document?.unique_id || 'template'}.json`
  link.click()
}
</script>

<template>
  <div class="flex flex-col h-full">
    <!-- Header with Tabs -->
    <div class="flex items-center justify-between mb-3 pb-3 border-b">
      <div class="flex items-center gap-2">
        <h2 class="text-lg font-semibold text-gray-900">Preview</h2>
        <div class="flex gap-1 bg-gray-100 rounded p-1">
          <button
            @click="activeTab = 'spec'"
            class="px-3 py-1 text-sm font-medium rounded transition-colors"
            :class="{
              'bg-white text-gray-900 shadow-sm': activeTab === 'spec',
              'text-gray-600 hover:text-gray-900': activeTab !== 'spec',
            }"
          >
            JSON Spec
          </button>
          <button
            @click="activeTab = 'pdf'"
            class="px-3 py-1 text-sm font-medium rounded transition-colors"
            :class="{
              'bg-white text-gray-900 shadow-sm': activeTab === 'pdf',
              'text-gray-600 hover:text-gray-900': activeTab !== 'pdf',
            }"
            :disabled="!pdfUrl"
          >
            PDF Preview
          </button>
        </div>
      </div>

      <!-- Tab-specific controls -->
      <div v-if="activeTab === 'spec'" class="flex items-center gap-2">
        <button
          v-if="mergedSpec"
          @click="downloadSpec"
          class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          title="Download JSON"
        >
          Download
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

      <div v-else-if="activeTab === 'pdf'" class="flex items-center gap-2">
        <button
          @click="refreshPdf"
          class="px-2 py-1 text-xs font-medium text-gray-700 bg-gray-100 rounded hover:bg-gray-200"
          title="Refresh PDF"
        >
          Refresh
        </button>
      </div>
    </div>

    <!-- Tab Content -->
    <div class="flex-1 relative">
      <!-- JSON Spec Tab -->
      <div
        v-show="activeTab === 'spec'"
        class="h-full flex flex-col"
      >
        <!-- Loading State -->
        <div
          v-if="loading"
          class="flex items-center justify-center h-full text-gray-500"
        >
          <div class="text-center">
            <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-600 mx-auto mb-4" />
            <div>Compiling template...</div>
          </div>
        </div>

        <!-- Error State -->
        <div
          v-else-if="compilationError"
          class="p-4 bg-red-50 border border-red-200 rounded text-red-700"
        >
          <strong>Compilation Error:</strong>
          <pre class="mt-2 text-sm whitespace-pre-wrap">{{ compilationError }}</pre>
        </div>

        <!-- No Spec State -->
        <div
          v-else-if="!mergedSpec"
          class="flex items-center justify-center h-full text-gray-400"
        >
          <div class="text-center">
            <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            <div>No compiled specification yet</div>
            <div class="text-sm mt-1">Click "Compile & Preview" to generate</div>
          </div>
        </div>

        <!-- Spec Content -->
        <textarea
          v-else
          :value="mergedSpecJson"
          readonly
          class="flex-1 w-full p-4 font-mono bg-gray-50 border border-gray-300 rounded-md resize-none"
          :style="{ fontSize: `${fontSize}px` }"
          spellcheck="false"
        />
      </div>

      <!-- PDF Preview Tab -->
      <div
        v-show="activeTab === 'pdf'"
        class="h-full flex flex-col"
      >
        <div
          v-if="!pdfUrl"
          class="flex items-center justify-center h-full text-gray-400"
        >
          <div class="text-center">
            <svg class="mx-auto h-12 w-12 mb-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z" />
            </svg>
            <div>No PDF generated yet</div>
            <div class="text-sm mt-1">Click "Render PDF" to generate</div>
          </div>
        </div>

        <iframe
          v-else
          :key="pdfTimestamp"
          :src="`${pdfUrl}#view=FitH&toolbar=1`"
          class="w-full h-full border border-gray-300 rounded-md"
        />
      </div>
    </div>
  </div>
</template>

<style scoped>
textarea {
  tab-size: 2;
}
</style>
