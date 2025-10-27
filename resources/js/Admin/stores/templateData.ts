import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export interface TemplateData {
  id: number
  document_id: string
  name: string | null
  template_id: number | null
  template_ref: string | null
  json_data: Record<string, any>
  compiled_spec?: Record<string, any> | null
  portable_format: boolean
  created_at: string
  updated_at: string
  template?: {
    id: number
    name: string
    category: string
    family?: {
      id: number
      name: string
      slug: string
    }
  }
}

export const useTemplateDataStore = defineStore('admin-template-data', () => {
  // State
  const dataFiles = ref<TemplateData[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const currentDataFile = ref<TemplateData | null>(null)
  const compiledSpec = ref<Record<string, any> | null>(null)
  const pdfUrl = ref<string | null>(null)

  // Computed
  const dataFilesCount = computed(() => dataFiles.value.length)

  // Actions
  async function fetchDataFiles(params?: { 
    template_id?: number
    family_id?: number
    search?: string 
  }) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/truth-templates/data', { params })
      const data = response.data.data || response.data
      dataFiles.value = Array.isArray(data) ? data : []
      return dataFiles.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch data files'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchDataFile(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/truth-templates/data/${id}`)
      currentDataFile.value = response.data.data_file || response.data
      return currentDataFile.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch data file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createDataFile(data: Partial<TemplateData>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/data', data)
      const newDataFile = response.data.data_file || response.data
      dataFiles.value.push(newDataFile)
      return newDataFile
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to create data file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateDataFile(id: number | string, data: Partial<TemplateData>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(`/api/truth-templates/data/${id}`, data)
      const updatedDataFile = response.data.data_file || response.data
      
      // Optimistic update
      const index = dataFiles.value.findIndex(d => d.id === id)
      if (index !== -1) {
        dataFiles.value[index] = updatedDataFile
      }
      
      if (currentDataFile.value?.id === id) {
        currentDataFile.value = updatedDataFile
      }

      return updatedDataFile
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to update data file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteDataFile(id: number | string) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`/api/truth-templates/data/${id}`)
      
      // Optimistic update
      dataFiles.value = dataFiles.value.filter(d => d.id !== id)
      
      if (currentDataFile.value?.id === id) {
        currentDataFile.value = null
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to delete data file'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function compileWithTemplate(templateId: number | string, data: Record<string, any>) {
    loading.value = true
    error.value = null
    compiledSpec.value = null

    try {
      // First get the template
      const templateRes = await axios.get(`/api/truth-templates/templates/${templateId}`)
      const template = templateRes.data.template || templateRes.data

      // Then compile with the data
      const compileRes = await axios.post('/api/truth-templates/compile', {
        template: template.handlebars_template,
        data: data,
      })

      if (compileRes.data.success) {
        compiledSpec.value = compileRes.data.spec
      }

      return compileRes.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to compile'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function renderData(spec: Record<string, any>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/render', { spec })
      
      if (response.data.success) {
        pdfUrl.value = response.data.pdf_url
      }

      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to render'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function validateData(templateId: number | string, data: Record<string, any>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/truth-templates/templates/${templateId}/validate-data`, {
        data,
      })
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to validate'
      throw err
    } finally {
      loading.value = false
    }
  }

  function clearError() {
    error.value = null
  }

  function clearPreview() {
    compiledSpec.value = null
    pdfUrl.value = null
  }

  // New methods for data file operations
  async function validateDataFile(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/truth-templates/data/${id}/validate`)
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to validate'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function compileDataFile(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/truth-templates/data/${id}/compile`)
      compiledSpec.value = response.data.compiled_spec
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to compile'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function renderDataFile(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/truth-templates/data/${id}/render`)
      pdfUrl.value = response.data.pdf_url
      return response.data
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to render'
      throw err
    } finally {
      loading.value = false
    }
  }

  return {
    // State
    dataFiles,
    loading,
    error,
    currentDataFile,
    compiledSpec,
    pdfUrl,
    
    // Computed
    dataFilesCount,
    
    // Actions
    fetchDataFiles,
    fetchDataFile,
    createDataFile,
    updateDataFile,
    deleteDataFile,
    validateDataFile,
    compileDataFile,
    renderDataFile,
    compileWithTemplate,
    renderData,
    validateData,
    clearError,
    clearPreview,
  }
})
