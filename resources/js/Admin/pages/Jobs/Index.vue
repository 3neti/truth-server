<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import { useRenderingJobsStore } from '../../stores/renderingJobs'
import type { RenderingJob } from '../../stores/renderingJobs'

const jobsStore = useRenderingJobsStore()

const selectedStatus = ref<string>('')

const columns = [
  { key: 'id', label: 'ID', sortable: true },
  { key: 'template_data', label: 'Document', sortable: false },
  { key: 'status', label: 'Status', sortable: true },
  { key: 'progress', label: 'Progress', sortable: false },
  { key: 'created_at', label: 'Created', sortable: true },
  { key: 'actions', label: 'Actions', width: '180px' },
]

const filteredJobs = computed(() => {
  if (!selectedStatus.value) return jobsStore.jobs
  return jobsStore.jobs.filter(job => job.status === selectedStatus.value)
})

onMounted(async () => {
  await jobsStore.fetchJobs()
  // Start polling for updates
  jobsStore.startPolling(3000)
})

onUnmounted(() => {
  // Stop polling when leaving page
  jobsStore.stopPolling()
})

function getStatusColor(status: string) {
  const colors: Record<string, string> = {
    pending: 'info',
    processing: 'primary',
    completed: 'success',
    failed: 'danger',
    cancelled: 'secondary',
  }
  return colors[status] || 'secondary'
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleString('en-US', {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}

async function handleRetry(job: RenderingJob) {
  try {
    await jobsStore.retryJob(job.id)
  } catch (error) {
    console.error('Failed to retry job:', error)
  }
}

async function handleCancel(job: RenderingJob) {
  const confirmed = confirm('Are you sure you want to cancel this job?')
  if (!confirmed) return

  try {
    await jobsStore.cancelJob(job.id)
  } catch (error) {
    console.error('Failed to cancel job:', error)
  }
}

async function handleDelete(job: RenderingJob) {
  const confirmed = confirm('Are you sure you want to delete this job?')
  if (!confirmed) return

  try {
    await jobsStore.deleteJob(job.id)
  } catch (error) {
    console.error('Failed to delete job:', error)
  }
}

function viewPDF(job: RenderingJob) {
  if (job.pdf_url) {
    window.open(job.pdf_url, '_blank')
  }
}

function clearFilter() {
  selectedStatus.value = ''
}
</script>

<template>
  <AdminLayout>
    <div class="jobs-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Rendering Jobs</h1>
          <p class="page-subtitle">Track and manage PDF rendering jobs</p>
        </div>
        <div class="header-stats">
          <va-chip color="info" size="small">
            {{ jobsStore.activeJobsCount }} Active
          </va-chip>
          <va-chip color="success" size="small">
            {{ jobsStore.completedJobs.length }} Completed
          </va-chip>
          <va-chip color="danger" size="small">
            {{ jobsStore.failedJobs.length }} Failed
          </va-chip>
        </div>
      </div>

      <!-- Filters -->
      <va-card class="filters-card">
        <va-card-content>
          <div class="filters">
            <va-select
              v-model="selectedStatus"
              :options="[
                { text: 'All Statuses', value: '' },
                { text: 'Pending', value: 'pending' },
                { text: 'Processing', value: 'processing' },
                { text: 'Completed', value: 'completed' },
                { text: 'Failed', value: 'failed' },
                { text: 'Cancelled', value: 'cancelled' },
              ]"
              placeholder="Filter by status"
              text-by="text"
              value-by="value"
              class="filter-select"
            />
            
            <va-button
              v-if="selectedStatus"
              preset="plain"
              icon="clear"
              @click="clearFilter"
            >
              Clear
            </va-button>
            
            <div class="spacer" />
            
            <va-button
              preset="plain"
              icon="refresh"
              @click="jobsStore.fetchJobs()"
              :loading="jobsStore.loading"
            >
              Refresh
            </va-button>
          </div>
        </va-card-content>
      </va-card>

      <!-- Jobs Table -->
      <va-card>
        <va-card-content>
          <!-- Loading State -->
          <div v-if="jobsStore.loading && jobsStore.jobs.length === 0" class="loading-state">
            <va-progress-circle indeterminate />
            <p>Loading jobs...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="filteredJobs.length === 0" class="empty-state">
            <va-icon name="work" size="large" color="secondary" />
            <p v-if="selectedStatus">
              No jobs with status "{{ selectedStatus }}"
            </p>
            <p v-else>
              No rendering jobs yet. Render a template to create one!
            </p>
          </div>

          <!-- Jobs Table -->
          <va-data-table
            v-else
            :items="filteredJobs"
            :columns="columns"
            striped
            hoverable
            :per-page="20"
            class="jobs-table"
          >
            <!-- Document Column -->
            <template #cell(template_data)="{ rowData }">
              <div v-if="rowData.template_data">
                <div class="font-semibold">{{ rowData.template_data.document_id }}</div>
                <div v-if="rowData.template_data.name" class="text-sm text-muted">
                  {{ rowData.template_data.name }}
                </div>
              </div>
              <span v-else class="text-muted">-</span>
            </template>

            <!-- Status Column -->
            <template #cell(status)="{ rowData }">
              <va-chip
                :color="getStatusColor(rowData.status)"
                size="small"
              >
                {{ rowData.status }}
              </va-chip>
            </template>

            <!-- Progress Column -->
            <template #cell(progress)="{ rowData }">
              <div class="progress-cell">
                <va-progress-bar
                  v-if="rowData.status === 'processing' || rowData.status === 'pending'"
                  :model-value="rowData.progress"
                  size="small"
                />
                <span v-else-if="rowData.status === 'completed'" class="progress-text">
                  ✓ Done
                </span>
                <span v-else-if="rowData.status === 'failed'" class="progress-text text-danger">
                  ✗ Failed
                </span>
                <span v-else class="progress-text text-muted">
                  -
                </span>
              </div>
            </template>

            <!-- Created At Column -->
            <template #cell(created_at)="{ value }">
              <span class="text-secondary">{{ formatDate(value) }}</span>
            </template>

            <!-- Actions Column -->
            <template #cell(actions)="{ rowData }">
              <div class="actions-cell" @click.stop>
                <va-button
                  v-if="rowData.status === 'completed' && rowData.pdf_url"
                  preset="plain"
                  icon="visibility"
                  size="small"
                  @click="viewPDF(rowData)"
                  title="View PDF"
                />
                <va-button
                  v-if="rowData.status === 'failed' || rowData.status === 'cancelled'"
                  preset="plain"
                  icon="refresh"
                  size="small"
                  @click="handleRetry(rowData)"
                  title="Retry"
                />
                <va-button
                  v-if="rowData.status === 'pending' || rowData.status === 'processing'"
                  preset="plain"
                  icon="cancel"
                  size="small"
                  color="warning"
                  @click="handleCancel(rowData)"
                  title="Cancel"
                />
                <va-button
                  preset="plain"
                  icon="delete"
                  size="small"
                  color="danger"
                  @click="handleDelete(rowData)"
                  title="Delete"
                />
              </div>
            </template>
          </va-data-table>
        </va-card-content>
      </va-card>
    </div>
  </AdminLayout>
</template>

<style scoped>
.jobs-page {
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--va-text-primary);
  margin: 0 0 0.5rem 0;
}

.page-subtitle {
  font-size: 1rem;
  color: var(--va-text-secondary);
  margin: 0;
}

.header-stats {
  display: flex;
  gap: 0.5rem;
  align-items: center;
}

.filters-card {
  margin-bottom: 1.5rem;
}

.filters {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.filter-select {
  min-width: 200px;
}

.spacer {
  flex: 1;
}

.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  color: var(--va-text-secondary);
}

.loading-state p,
.empty-state p {
  margin-top: 1rem;
  font-size: 1rem;
}

.jobs-table {
  cursor: default;
}

.progress-cell {
  min-width: 100px;
}

.progress-text {
  font-size: 0.875rem;
}

.actions-cell {
  display: flex;
  gap: 0.25rem;
}

.text-secondary {
  color: var(--va-text-secondary);
  font-size: 0.875rem;
}

.text-muted {
  color: var(--va-text-secondary);
  font-style: italic;
  font-size: 0.875rem;
}

.text-sm {
  font-size: 0.75rem;
}

.text-danger {
  color: var(--va-danger);
}

.font-semibold {
  font-weight: 600;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
  }

  .filters {
    flex-direction: column;
    align-items: stretch;
  }

  .filter-select {
    min-width: 100%;
  }
}
</style>
