import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import axios from 'axios'

export interface RenderingJob {
  id: number
  template_data_id: number | null
  user_id: number | null
  status: 'pending' | 'processing' | 'completed' | 'failed' | 'cancelled'
  progress: number
  pdf_url: string | null
  error_message: string | null
  metadata: Record<string, any> | null
  started_at: string | null
  completed_at: string | null
  created_at: string
  updated_at: string
  template_data?: {
    id: number
    document_id: string
    name: string | null
  }
}

export const useRenderingJobsStore = defineStore('admin-rendering-jobs', () => {
  // State
  const jobs = ref<RenderingJob[]>([])
  const currentJob = ref<RenderingJob | null>(null)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const pollingInterval = ref<number | null>(null)

  // Computed
  const jobsCount = computed(() => jobs.value.length)
  
  const pendingJobs = computed(() => 
    jobs.value.filter(j => j.status === 'pending')
  )
  
  const processingJobs = computed(() => 
    jobs.value.filter(j => j.status === 'processing')
  )
  
  const completedJobs = computed(() => 
    jobs.value.filter(j => j.status === 'completed')
  )
  
  const failedJobs = computed(() => 
    jobs.value.filter(j => j.status === 'failed')
  )

  const activeJobsCount = computed(() => 
    pendingJobs.value.length + processingJobs.value.length
  )

  // Actions
  async function fetchJobs(params?: { status?: string; per_page?: number }) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get('/api/truth-templates/jobs', { params })
      const data = response.data.data || response.data
      jobs.value = Array.isArray(data) ? data : []
      return jobs.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch jobs'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function fetchJob(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.get(`/api/truth-templates/jobs/${id}`)
      currentJob.value = response.data
      return currentJob.value
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to fetch job'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function createJob(data: { template_data_id?: number; spec: Record<string, any> }) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post('/api/truth-templates/jobs', data)
      const newJob = response.data
      jobs.value.unshift(newJob)
      return newJob
    } catch (err: any) {
      error.value = err.response?.data?.message || err.message || 'Failed to create job'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function retryJob(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/truth-templates/jobs/${id}/retry`)
      const updatedJob = response.data
      
      // Update in list
      const index = jobs.value.findIndex(j => j.id === id)
      if (index !== -1) {
        jobs.value[index] = updatedJob
      }
      
      if (currentJob.value?.id === id) {
        currentJob.value = updatedJob
      }

      return updatedJob
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to retry job'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function cancelJob(id: number | string) {
    loading.value = true
    error.value = null

    try {
      const response = await axios.post(`/api/truth-templates/jobs/${id}/cancel`)
      const updatedJob = response.data
      
      // Update in list
      const index = jobs.value.findIndex(j => j.id === id)
      if (index !== -1) {
        jobs.value[index] = updatedJob
      }
      
      if (currentJob.value?.id === id) {
        currentJob.value = updatedJob
      }

      return updatedJob
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to cancel job'
      throw err
    } finally {
      loading.value = false
    }
  }

  async function deleteJob(id: number | string) {
    loading.value = true
    error.value = null

    try {
      await axios.delete(`/api/truth-templates/jobs/${id}`)
      
      // Remove from list
      jobs.value = jobs.value.filter(j => j.id !== id)
      
      if (currentJob.value?.id === id) {
        currentJob.value = null
      }
    } catch (err: any) {
      error.value = err.response?.data?.error || err.message || 'Failed to delete job'
      throw err
    } finally {
      loading.value = false
    }
  }

  // Polling for job updates
  function startPolling(intervalMs: number = 3000) {
    if (pollingInterval.value) {
      stopPolling()
    }

    pollingInterval.value = window.setInterval(async () => {
      // Only poll if there are active jobs
      if (activeJobsCount.value > 0) {
        try {
          await fetchJobs()
        } catch (err) {
          console.error('Polling error:', err)
        }
      }
    }, intervalMs)
  }

  function stopPolling() {
    if (pollingInterval.value) {
      clearInterval(pollingInterval.value)
      pollingInterval.value = null
    }
  }

  function clearError() {
    error.value = null
  }

  return {
    // State
    jobs,
    currentJob,
    loading,
    error,
    pollingInterval,
    
    // Computed
    jobsCount,
    pendingJobs,
    processingJobs,
    completedJobs,
    failedJobs,
    activeJobsCount,
    
    // Actions
    fetchJobs,
    fetchJob,
    createJob,
    retryJob,
    cancelJob,
    deleteJob,
    startPolling,
    stopPolling,
    clearError,
  }
})
