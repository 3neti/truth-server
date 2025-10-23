import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export interface DocumentSpec {
  title: string
  unique_id: string
  layout?: string
  locale?: string
}

export interface ChoiceSpec {
  code: string
  label: string
}

export interface SectionSpec {
  type: string
  code: string
  title: string
  layout?: string
  maxSelections?: number
  question?: string
  scale?: number[]
  choices?: ChoiceSpec[]
}

export interface TemplateSpec {
  document: DocumentSpec
  sections: SectionSpec[]
}

export const useTemplatesStore = defineStore('templates', () => {
  // State
  const spec = ref<TemplateSpec>({
    document: {
      title: 'New Document',
      unique_id: `DOC-${Date.now()}`,
      layout: '2-col',
    },
    sections: [],
  })

  const pdfUrl = ref<string | null>(null)
  const coordsUrl = ref<string | null>(null)
  const documentId = ref<string | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const validationErrors = ref<Record<string, string[]> | null>(null)

  // Computed
  const isValid = computed(() => {
    return (
      spec.value.document?.title &&
      spec.value.document?.unique_id &&
      spec.value.sections.length > 0
    )
  })

  // Actions
  async function renderTemplate() {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/templates/render', {
        spec: spec.value,
      })

      if (response.data.success) {
        pdfUrl.value = response.data.pdf_url
        coordsUrl.value = response.data.coords_url
        documentId.value = response.data.document_id
        return response.data
      }
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to render template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function validateTemplate() {
    loading.value = true
    error.value = null
    validationErrors.value = null

    try {
      const response = await axios.post('/api/templates/validate', {
        spec: spec.value,
      })

      if (response.data.valid) {
        return true
      }
    } catch (err: any) {
      validationErrors.value = err.response?.data?.errors || null
      error.value = 'Validation failed'
      return false
    } finally {
      loading.value = false
    }
  }

  async function loadSample(sampleName: string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/templates/samples')
      const sample = response.data.samples.find((s: any) => s.name === sampleName)

      if (sample) {
        spec.value = sample.spec
      }
    } catch (err: any) {
      error.value = err.message || 'Failed to load sample'
    } finally {
      loading.value = false
    }
  }

  async function getSamples() {
    try {
      const response = await axios.get('/api/templates/samples')
      return response.data.samples
    } catch (err: any) {
      error.value = err.message || 'Failed to get samples'
      return []
    }
  }

  async function getLayouts() {
    try {
      const response = await axios.get('/api/templates/layouts')
      return response.data.layouts
    } catch (err: any) {
      error.value = err.message || 'Failed to get layouts'
      return {}
    }
  }

  function updateSpec(newSpec: TemplateSpec) {
    spec.value = newSpec
  }

  function addSection(section: SectionSpec) {
    spec.value.sections.push(section)
  }

  function removeSection(index: number) {
    spec.value.sections.splice(index, 1)
  }

  function updateSection(index: number, section: SectionSpec) {
    spec.value.sections[index] = section
  }

  function clearSpec() {
    spec.value = {
      document: {
        title: 'New Document',
        unique_id: `DOC-${Date.now()}`,
        layout: '2-col',
      },
      sections: [],
    }
    pdfUrl.value = null
    coordsUrl.value = null
    documentId.value = null
    error.value = null
    validationErrors.value = null
  }

  function exportJson() {
    const blob = new Blob([JSON.stringify(spec.value, null, 2)], {
      type: 'application/json',
    })
    const link = document.createElement('a')
    link.href = URL.createObjectURL(blob)
    link.download = `${spec.value.document?.unique_id || 'template'}.json`
    link.click()
  }

  function importJson(jsonString: string) {
    try {
      const parsed = JSON.parse(jsonString)
      spec.value = parsed
      error.value = null
      return true
    } catch (err) {
      error.value = 'Invalid JSON format'
      return false
    }
  }

  // Local storage
  function saveToLocalStorage() {
    localStorage.setItem('omr-template', JSON.stringify(spec.value))
  }

  function loadFromLocalStorage() {
    const saved = localStorage.getItem('omr-template')
    if (saved) {
      try {
        spec.value = JSON.parse(saved)
        return true
      } catch {
        return false
      }
    }
    return false
  }

  return {
    // State
    spec,
    pdfUrl,
    coordsUrl,
    documentId,
    loading,
    error,
    validationErrors,
    // Computed
    isValid,
    // Actions
    renderTemplate,
    validateTemplate,
    loadSample,
    getSamples,
    getLayouts,
    updateSpec,
    addSection,
    removeSection,
    updateSection,
    clearSpec,
    exportJson,
    importJson,
    saveToLocalStorage,
    loadFromLocalStorage,
  }
})
