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
  // State - Simple Mode
  const spec = ref<TemplateSpec>({
    document: {
      title: 'New Document',
      unique_id: `DOC-${Date.now()}`,
      layout: '2-col',
    },
    sections: [],
  })

  // State - Advanced Mode
  const mode = ref<'simple' | 'advanced'>('simple')
  const handlebarsTemplate = ref<string>('')
  const templateData = ref<Record<string, any>>({})
  const mergedSpec = ref<TemplateSpec | null>(null)
  const compilationError = ref<string | null>(null)

  // State - Common
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
      const response = await axios.post('/api/truth-templates/render', {
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
      const response = await axios.post('/api/truth-templates/validate', {
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
      const response = await axios.get('/api/truth-templates/samples')
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
      const response = await axios.get('/api/truth-templates/samples')
      return response.data.samples
    } catch (err: any) {
      error.value = err.message || 'Failed to get samples'
      return []
    }
  }

  async function getLayouts() {
    try {
      const response = await axios.get('/api/truth-templates/layouts')
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

  // Advanced Mode Actions
  async function compileTemplate() {
    loading.value = true
    compilationError.value = null

    try {
      const response = await axios.post('/api/truth-templates/compile', {
        template: handlebarsTemplate.value,
        data: templateData.value,
      })

      if (response.data.success) {
        mergedSpec.value = response.data.spec
        return response.data.spec
      }
    } catch (err: any) {
      compilationError.value = err.response?.data?.error || err.message || 'Compilation failed'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function saveTemplateToLibrary(name: string, description: string, category: string, isPublic: boolean = true) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/templates', {
        name,
        description,
        category,
        handlebars_template: handlebarsTemplate.value,
        sample_data: templateData.value,
        is_public: isPublic,
      })

      if (response.data.success) {
        return response.data.template
      }
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to save template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTemplateInLibrary(id: number, name: string, description: string, category: string, isPublic: boolean = true) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(`/api/truth-templates/templates/${id}`, {
        name,
        description,
        category,
        handlebars_template: handlebarsTemplate.value,
        sample_data: templateData.value,
        is_public: isPublic,
      })

      if (response.data.success) {
        return response.data.template
      }
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to update template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function loadTemplateFromLibrary(id: string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/truth-templates/templates/${id}`)

      if (response.data.success) {
        const template = response.data.template
        handlebarsTemplate.value = template.handlebars_template
        templateData.value = template.sample_data || {}
        return template
      }
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to load template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function getTemplateLibrary(category?: string) {
    try {
      const url = category ? `/api/truth-templates/templates?category=${category}` : '/api/truth-templates/templates'
      const response = await axios.get(url)
      return response.data.templates || []
    } catch (err: any) {
      error.value = err.message || 'Failed to get templates'
      return []
    }
  }

  // Template Families
  async function getTemplateFamilies(params?: { category?: string; search?: string }) {
    try {
      const queryParams = new URLSearchParams()
      if (params?.category) queryParams.append('category', params.category)
      if (params?.search) queryParams.append('search', params.search)
      
      const url = `/api/truth-templates/families${queryParams.toString() ? '?' + queryParams.toString() : ''}`
      const response = await axios.get(url)
      return response.data || []
    } catch (err: any) {
      error.value = err.message || 'Failed to get template families'
      return []
    }
  }

  async function getTemplateFamily(id: string) {
    try {
      const response = await axios.get(`/api/truth-templates/families/${id}`)
      return response.data
    } catch (err: any) {
      error.value = err.message || 'Failed to get template family'
      throw err
    }
  }

  async function getFamilyVariants(id: string) {
    try {
      const response = await axios.get(`/api/truth-templates/families/${id}/templates`)
      return response.data
    } catch (err: any) {
      error.value = err.message || 'Failed to get family variants'
      throw err
    }
  }

  async function createTemplateFamily(data: {
    name: string
    description?: string
    category: string
    repo_url?: string
    version?: string
    is_public?: boolean
  }) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/families', data)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to create family'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteTemplateFamily(id: string) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`/api/truth-templates/families/${id}`)
      return true
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to delete family'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function exportTemplateFamily(id: string) {
    try {
      const response = await axios.get(`/api/truth-templates/families/${id}/export`)
      return response.data
    } catch (err: any) {
      error.value = err.message || 'Failed to export family'
      throw err
    }
  }

  async function importTemplateFamily(familyData: any) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/families/import', { family_data: familyData })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to import family'
      throw err
    } finally {
      loading.value = false
    }
  }

  function setMode(newMode: 'simple' | 'advanced') {
    mode.value = newMode
  }

  function updateHandlebarsTemplate(template: string) {
    handlebarsTemplate.value = template
  }

  function updateTemplateData(data: Record<string, any>) {
    templateData.value = data
  }

  // Local storage
  function saveToLocalStorage() {
    if (mode.value === 'simple') {
      localStorage.setItem('omr-template', JSON.stringify(spec.value))
    } else {
      localStorage.setItem('omr-template-advanced', JSON.stringify({
        template: handlebarsTemplate.value,
        data: templateData.value,
      }))
    }
  }

  function loadFromLocalStorage() {
    if (mode.value === 'simple') {
      const saved = localStorage.getItem('omr-template')
      if (saved) {
        try {
          spec.value = JSON.parse(saved)
          return true
        } catch {
          return false
        }
      }
    } else {
      const saved = localStorage.getItem('omr-template-advanced')
      if (saved) {
        try {
          const parsed = JSON.parse(saved)
          handlebarsTemplate.value = parsed.template || ''
          templateData.value = parsed.data || {}
          return true
        } catch {
          return false
        }
      }
    }
    return false
  }

  return {
    // State - Simple Mode
    spec,
    // State - Advanced Mode
    mode,
    handlebarsTemplate,
    templateData,
    mergedSpec,
    compilationError,
    // State - Common
    pdfUrl,
    coordsUrl,
    documentId,
    loading,
    error,
    validationErrors,
    // Computed
    isValid,
    // Actions - Simple Mode
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
    // Actions - Advanced Mode
    compileTemplate,
    saveTemplateToLibrary,
    updateTemplateInLibrary,
    loadTemplateFromLibrary,
    getTemplateLibrary,
    setMode,
    updateHandlebarsTemplate,
    updateTemplateData,
    // Actions - Template Families
    getTemplateFamilies,
    getTemplateFamily,
    getFamilyVariants,
    createTemplateFamily,
    deleteTemplateFamily,
    exportTemplateFamily,
    importTemplateFamily,
    // Actions - Common
    saveToLocalStorage,
    loadFromLocalStorage,
  }
})
