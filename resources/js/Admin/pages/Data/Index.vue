<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import { useTemplateDataStore } from '../../stores/templateData'
import { useTemplatesStore } from '../../stores/templates'
import { useFamiliesStore } from '../../stores/families'
import type { TemplateData } from '../../stores/templateData'

const dataStore = useTemplateDataStore()
const templatesStore = useTemplatesStore()
const familiesStore = useFamiliesStore()

const searchQuery = ref('')
const selectedTemplate = ref<number | null>(null)
const selectedFamily = ref<number | null>(null)

const columns = [
  { key: 'document_id', label: 'Document ID', sortable: true },
  { key: 'name', label: 'Name', sortable: true },
  { key: 'template', label: 'Template', sortable: true },
  { key: 'portable_format', label: 'Format', sortable: true },
  { key: 'created_at', label: 'Created', sortable: true },
  { key: 'actions', label: 'Actions', width: '120px' },
]

const filteredDataFiles = computed(() => {
  let result = dataStore.dataFiles
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(data => 
      data.document_id.toLowerCase().includes(query) ||
      data.name?.toLowerCase().includes(query)
    )
  }
  
  if (selectedTemplate.value) {
    result = result.filter(data => data.template_id === selectedTemplate.value)
  }
  
  if (selectedFamily.value) {
    result = result.filter(data => data.template?.family?.id === selectedFamily.value)
  }
  
  return result
})

onMounted(() => {
  dataStore.fetchDataFiles()
  templatesStore.fetchTemplates()
  familiesStore.fetchFamilies()
})

function createData() {
  router.visit('/admin/data/create')
}

function editData(data: TemplateData) {
  router.visit(`/admin/data/${data.id}/edit`)
}

function viewData(data: TemplateData) {
  router.visit(`/admin/data/${data.id}/edit`)
}

async function deleteData(data: TemplateData) {
  const confirmed = confirm(`Are you sure you want to delete "${data.document_id}"?`)
  if (!confirmed) return

  try {
    await dataStore.deleteDataFile(data.id)
  } catch (error) {
    console.error('Failed to delete data:', error)
  }
}

function clearFilters() {
  searchQuery.value = ''
  selectedTemplate.value = null
  selectedFamily.value = null
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
  })
}
</script>

<template>
  <AdminLayout>
    <div class="data-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Template Data</h1>
          <p class="page-subtitle">Manage your template data files</p>
        </div>
        <va-button @click="createData" color="primary" icon="add">
          New Data File
        </va-button>
      </div>

      <!-- Filters & Search -->
      <va-card class="filters-card">
        <va-card-content>
          <div class="filters">
            <va-input
              v-model="searchQuery"
              placeholder="Search by document ID or name..."
              class="search-input"
            >
              <template #prependInner>
                <va-icon name="search" />
              </template>
            </va-input>

            <va-select
              v-model="selectedFamily"
              :options="familiesStore.families"
              placeholder="All Families"
              text-by="name"
              value-by="id"
              clearable
              class="filter-select"
            />

            <va-select
              v-model="selectedTemplate"
              :options="templatesStore.templates"
              placeholder="All Templates"
              text-by="name"
              value-by="id"
              clearable
              class="filter-select"
            />
            
            <va-button
              v-if="searchQuery || selectedTemplate || selectedFamily"
              preset="plain"
              icon="clear"
              @click="clearFilters"
            >
              Clear
            </va-button>
            
            <div class="spacer" />
            
            <va-button preset="plain" icon="download">
              Export
            </va-button>
          </div>
        </va-card-content>
      </va-card>

      <!-- Data Table -->
      <va-card>
        <va-card-content>
          <!-- Loading State -->
          <div v-if="dataStore.loading" class="loading-state">
            <va-progress-circle indeterminate />
            <p>Loading data files...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="filteredDataFiles.length === 0" class="empty-state">
            <va-icon name="data_object" size="large" color="secondary" />
            <p v-if="searchQuery || selectedTemplate || selectedFamily">
              No data files match your filters
            </p>
            <p v-else>
              No data files yet. Create your first one!
            </p>
            <va-button
              v-if="!searchQuery && !selectedTemplate && !selectedFamily"
              @click="createData"
              color="primary"
              class="mt-4"
            >
              Create Data File
            </va-button>
          </div>

          <!-- Data Table -->
          <va-data-table
            v-else
            :items="filteredDataFiles"
            :columns="columns"
            striped
            hoverable
            :per-page="15"
            @row:click="(event) => viewData(event.item)"
            class="data-table"
          >
            <!-- Name Column -->
            <template #cell(name)="{ value }">
              <span v-if="value">{{ value }}</span>
              <span v-else class="text-muted">-</span>
            </template>

            <!-- Template Column -->
            <template #cell(template)="{ rowData }">
              <div v-if="rowData.template">
                <div>{{ rowData.template.name }}</div>
                <div v-if="rowData.template.family" class="text-sm text-muted">
                  {{ rowData.template.family.name }}
                </div>
              </div>
              <span v-else-if="rowData.template_ref" class="text-muted">
                {{ rowData.template_ref }}
              </span>
              <span v-else class="text-muted">-</span>
            </template>

            <!-- Format Column -->
            <template #cell(portable_format)="{ value }">
              <va-chip
                :color="value ? 'info' : 'secondary'"
                size="small"
              >
                {{ value ? 'Portable' : 'Full Spec' }}
              </va-chip>
            </template>

            <!-- Created At Column -->
            <template #cell(created_at)="{ value }">
              <span class="text-secondary">{{ formatDate(value) }}</span>
            </template>

            <!-- Actions Column -->
            <template #cell(actions)="{ rowData }">
              <div class="actions-cell" @click.stop>
                <va-button
                  preset="plain"
                  icon="visibility"
                  size="small"
                  @click="viewData(rowData)"
                  title="View"
                />
                <va-button
                  preset="plain"
                  icon="edit"
                  size="small"
                  @click="editData(rowData)"
                  title="Edit"
                />
                <va-button
                  preset="plain"
                  icon="delete"
                  size="small"
                  color="danger"
                  @click="deleteData(rowData)"
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
.data-page {
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

.filters-card {
  margin-bottom: 1.5rem;
}

.filters {
  display: flex;
  gap: 1rem;
  align-items: center;
  flex-wrap: wrap;
}

.search-input {
  min-width: 300px;
  flex: 1;
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

.data-table {
  cursor: pointer;
}

.data-table :deep(tbody tr:hover) {
  background: var(--va-background-hover);
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

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
  }

  .filters {
    flex-direction: column;
    align-items: stretch;
  }

  .search-input,
  .filter-select {
    min-width: 100%;
  }
}
</style>
