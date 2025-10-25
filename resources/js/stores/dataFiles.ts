import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from '@/lib/axios'

export interface DataFile {
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

export const useDataFilesStore = defineStore('dataFiles', () => {
  const dataFiles = ref<DataFile[]>([])
  const currentDataFile = ref<DataFile | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Fetch all data files with optional filters
  async function fetchDataFiles(filters?: {
    template_ref?: string
    category?: string
    search?: string
  }) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get('/api/data-files', { params: filters })
      dataFiles.value = response.data
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to fetch data files'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Fetch a single data file
  async function fetchDataFile(id: number) {
    loading.value = true
    error.value = null
    try {
      const response = await axios.get(`/api/data-files/${id}`)
      currentDataFile.value = response.data
      return response.data
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to fetch data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Create a new data file
  async function createDataFile(data: {
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
      const response = await axios.post('/api/data-files', data)
      const newDataFile = response.data
      dataFiles.value.unshift(newDataFile)
      currentDataFile.value = newDataFile
      return newDataFile
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to create data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Update an existing data file
  async function updateDataFile(id: number, data: Partial<{
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
      const response = await axios.put(`/api/data-files/${id}`, data)
      const updatedDataFile = response.data
      
      // Update in list
      const index = dataFiles.value.findIndex(df => df.id === id)
      if (index !== -1) {
        dataFiles.value[index] = updatedDataFile
      }
      
      // Update current if it's the same
      if (currentDataFile.value?.id === id) {
        currentDataFile.value = updatedDataFile
      }
      
      return updatedDataFile
    } catch (e: any) {
      error.value = e.response?.data?.message || 'Failed to update data file'
      throw e
    } finally {
      loading.value = false
    }
  }

  // Delete a data file
  async function deleteDataFile(id: number) {
    loading.value = true
    error.value = null
    try {
      await axios.delete(`/api/data-files/${id}`)
      
      // Remove from list
      dataFiles.value = dataFiles.value.filter(df => df.id !== id)
      
      // Clear current if it's the same
      if (currentDataFile.value?.id === id) {
        currentDataFile.value = null
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
    currentDataFile.value = null
  }

  // Set current data file (for editing)
  function setCurrent(dataFile: DataFile) {
    currentDataFile.value = dataFile
  }

  return {
    // State
    dataFiles,
    currentDataFile,
    loading,
    error,
    
    // Actions
    fetchDataFiles,
    fetchDataFile,
    createDataFile,
    updateDataFile,
    deleteDataFile,
    clearCurrent,
    setCurrent,
  }
})
