import { defineStore } from 'pinia'
import { ref } from 'vue'
import axios from 'axios'

export interface DashboardStats {
  families_count: number
  templates_count: number
  data_files_count: number
  recent_jobs_count: number
  storage_used_mb: number
}

export interface ActivityItem {
  id: number
  type: 'family' | 'template' | 'data' | 'job'
  action: 'created' | 'updated' | 'deleted' | 'rendered'
  title: string
  description?: string
  user?: string
  created_at: string
}

export const useDashboardStore = defineStore('admin-dashboard', () => {
  // State
  const stats = ref<DashboardStats>({
    families_count: 0,
    templates_count: 0,
    data_files_count: 0,
    recent_jobs_count: 0,
    storage_used_mb: 0,
  })
  
  const recentActivity = ref<ActivityItem[]>([])
  const loading = ref(false)
  const error = ref<string | null>(null)

  // Actions
  async function fetchStats() {
    loading.value = true
    error.value = null

    try {
      // Fetch families count
      const familiesRes = await axios.get('/api/truth-templates/families')
      stats.value.families_count = Array.isArray(familiesRes.data) ? familiesRes.data.length : 0

      // Fetch templates count
      const templatesRes = await axios.get('/api/truth-templates/templates')
      const templatesData = templatesRes.data.templates || templatesRes.data
      stats.value.templates_count = Array.isArray(templatesData) ? templatesData.length : 0

      // Fetch data files count
      const dataRes = await axios.get('/api/truth-templates/data')
      const dataFiles = dataRes.data.data || dataRes.data
      stats.value.data_files_count = Array.isArray(dataFiles) ? dataFiles.length : 0

      return stats.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch stats'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchRecentActivity() {
    loading.value = true
    error.value = null

    try {
      // For now, we'll mock this - later can be replaced with actual API
      recentActivity.value = []
      return recentActivity.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch activity'
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
    stats,
    recentActivity,
    loading,
    error,
    
    // Actions
    fetchStats,
    fetchRecentActivity,
    clearError,
  }
})
