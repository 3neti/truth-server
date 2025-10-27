import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export interface TemplateFamily {
  id: number
  name: string
  slug: string
  description: string | null
  tags: string[]
  is_active: boolean
  created_at: string
  updated_at: string
  templates_count?: number
}

export const useFamiliesStore = defineStore('admin-families', () => {
  // State
  const families = ref<TemplateFamily[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)
  const currentFamily = ref<TemplateFamily | null>(null)

  // Computed
  const activeFamilies = computed(() => 
    families.value.filter(f => f.is_active)
  )

  const familiesCount = computed(() => families.value.length)

  // Actions
  async function fetchFamilies() {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/truth-templates/families')
      families.value = Array.isArray(response.data) ? response.data : (response.data.families || [])
      return families.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch families'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchFamily(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/truth-templates/families/${id}`)
      currentFamily.value = response.data
      return currentFamily.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch family'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createFamily(data: Partial<TemplateFamily>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/families', data)
      const newFamily = response.data
      families.value.push(newFamily)
      return newFamily
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to create family'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function updateFamily(id: number | string, data: Partial<TemplateFamily>) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.put(`/api/truth-templates/families/${id}`, data)
      const updatedFamily = response.data
      
      // Optimistic update
      const index = families.value.findIndex(f => f.id === id)
      if (index !== -1) {
        families.value[index] = updatedFamily
      }
      
      if (currentFamily.value?.id === id) {
        currentFamily.value = updatedFamily
      }

      return updatedFamily
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to update family'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteFamily(id: number | string) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`/api/truth-templates/families/${id}`)
      
      // Optimistic update
      families.value = families.value.filter(f => f.id !== id)
      
      if (currentFamily.value?.id === id) {
        currentFamily.value = null
      }
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to delete family'
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
    families,
    loading,
    error,
    currentFamily,
    
    // Computed
    activeFamilies,
    familiesCount,
    
    // Actions
    fetchFamilies,
    fetchFamily,
    createFamily,
    updateFamily,
    deleteFamily,
    clearError,
  }
})
