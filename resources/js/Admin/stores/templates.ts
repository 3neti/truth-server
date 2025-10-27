import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export interface Template {
  id: number
  name: string
  description: string | null
  category: string
  handlebars_template: string
  sample_data: Record<string, any> | null
  json_schema: Record<string, any> | null
  version: string
  is_public: boolean
  storage_type: 'local' | 'remote' | 'hybrid'
  template_uri: string | null
  layout_variant: string | null
  family_id: number | null
  user_id: number | null
  checksum_sha256: string | null
  verified_at: string | null
  verified_by: number | null
  created_at: string
  updated_at: string
  family?: {
    id: number
    name: string
    slug: string
  }
}

export const useTemplatesStore = defineStore('admin-templates', () => {
  // State
  const templates = ref<Template[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const currentTemplate = ref<Template | null>(null)

  // Computed
  const templatesCount = computed(() => templates.value.length)
  
  const templatesByFamily = computed(() => {
    const grouped: Record<string, Template[]> = {}
    templates.value.forEach(template => {
      const familyName = template.family?.name || 'Uncategorized'
      if (!grouped[familyName]) {
        grouped[familyName] = []
      }
      grouped[familyName].push(template)
    })
    return grouped
  })

  // Actions
  async function fetchTemplates(params?: { family_id?: number; category?: string; search?: string }) {
    loading.value = true
    error.value = null

    try {
      // Always request family relationships
      const requestParams = { ...params, with_families: true }
      const response = await axios.get('/api/truth-templates/templates', { params: requestParams })
      const data = response.data.templates || response.data
      templates.value = Array.isArray(data) ? data : []
      return templates.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch templates'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchTemplate(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/truth-templates/templates/${id}`)
      currentTemplate.value = response.data.template || response.data
      return currentTemplate.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createTemplate(data: Partial<Template>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/templates', data)
      const newTemplate = response.data.template || response.data
      templates.value.push(newTemplate)
      return newTemplate
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to create template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateTemplate(id: number | string, data: Partial<Template>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(`/api/truth-templates/templates/${id}`, data)
      const updatedTemplate = response.data.template || response.data
      
      // Optimistic update
      const index = templates.value.findIndex(t => t.id === id)
      if (index !== -1) {
        templates.value[index] = updatedTemplate
      }
      
      if (currentTemplate.value?.id === id) {
        currentTemplate.value = updatedTemplate
      }

      return updatedTemplate
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to update template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteTemplate(id: number | string) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`/api/truth-templates/templates/${id}`)
      
      // Optimistic update
      templates.value = templates.value.filter(t => t.id !== id)
      
      if (currentTemplate.value?.id === id) {
        currentTemplate.value = null
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to delete template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function compileTemplate(template: string, data: Record<string, any>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/compile', {
        template,
        data,
      })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to compile template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function renderTemplate(spec: Record<string, any>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/render', { spec })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to render template'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function validateTemplate(spec: Record<string, any>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/validate', { spec })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to validate template'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  return {
    // State
    templates,
    loading,
    error,
    currentTemplate,
    
    // Computed
    templatesCount,
    templatesByFamily,
    
    // Actions
    fetchTemplates,
    fetchTemplate,
    createTemplate,
    updateTemplate,
    deleteTemplate,
    compileTemplate,
    renderTemplate,
    validateTemplate,
    clearError,
  }
})
