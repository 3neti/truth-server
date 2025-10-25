import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@/lib/axios'

export interface TemplateData {
  id: number
  name: string
  description: string | null
  template_ref: string | null
  data: Record<string, any>
  user_id: number | null
  is_public: boolean
  category: string
  created_at: string
  updated_at: string
  formatted_date: string
  user?: {
    id: number
    name: string
  }
}

export const useTemplateDataStore = defineStore('templateData', () => {
  const templateData = ref<TemplateData[]>([])
  const currentTemplateData = ref<TemplateData | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Fetch all data files with optional filters
  async function fetchTemplateDatas(filters?: {
    template_ref?: string
    category?: string
    search?: string
  }) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/template-data', { params: filters })
      templateData.value = response.data
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to fetch data files'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Fetch a single data file
  async function fetchTemplateData(id: number) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/template-data/${id}`)
      currentTemplateData.value = response.data
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to fetch data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Create a new data file
  async function createTemplateData(data: {
    name: string
    description?: string
    template_ref?: string
    data: Record<string, any>
    is_public?: boolean
    category?: string
  }) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.post('/api/template-data', data)
      const newTemplateData = response.data
      templateData.value.unshift(newTemplateData)
      currentTemplateData.value = newTemplateData
      return newTemplateData
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to create data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Update an existing data file
  async function updateTemplateData(id: number, data: Partial<{
    name: string
    description: string
    template_ref: string
    data: Record<string, any>
    is_public: boolean
    category: string
  }>) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.put(`/api/template-data/${id}`, data)
      const updatedTemplateData = response.data
      
      // Update in list
      const index = templateData.value.findIndex(df => df.id === id)
      if (index !== -1) {
        templateData.value[index] = updatedTemplateData
      }
      
      // Update current if it's the same
      if (currentTemplateData.value?.id === id) {
        currentTemplateData.value = updatedTemplateData
      }
      
      return updatedTemplateData
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to update data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Delete a data file
  async function deleteTemplateData(id: number) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/template-data/${id}`)
      
      // Remove from list
      templateData.value = templateData.value.filter(df => df.id !== id)
      
      // Clear current if it's the same
      if (currentTemplateData.value?.id === id) {
        currentTemplateData.value = null
      }
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to delete data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Clear current data file
  function clearCurrent() {
    currentTemplateData.value = null
  }

  // Set current data file (for editing)
  function setCurrent(dataFile: TemplateData) {
    currentTemplateData.value = dataFile
  }

  return {
    // State
    templateData,
    currentTemplateData,
    loading,
    error,
    
    // Actions
    fetchTemplateDatas,
    fetchTemplateData,
    createTemplateData,
    updateTemplateData,
    deleteTemplateData,
    clearCurrent,
    setCurrent,
  }
})
