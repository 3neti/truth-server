<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import { useTemplatesStore } from '../../stores/templates'
import { useFamiliesStore } from '../../stores/families'
import type { Template } from '../../stores/templates'

const templatesStore = useTemplatesStore()
const familiesStore = useFamiliesStore()

const searchQuery = ref('')
const selectedFamily = ref<number | null>(null)
const selectedCategory = ref<string | null>(null)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'family', label: 'Family', sortable: true },
  { key: 'category', label: 'Category', sortable: true },
  { key: 'layout_variant', label: 'Variant', sortable: true },
  { key: 'version', label: 'Version', sortable: true },
  { key: 'is_public', label: 'Visibility', sortable: true },
  { key: 'updated_at', label: 'Updated', sortable: true },
  { key: 'actions', label: 'Actions', width: '120px' },
]

const filteredTemplates = computed(() => {
  let result = templatesStore.templates
  
  // Filter by search query
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    result = result.filter(template => 
      template.name.toLowerCase().includes(query) ||
      template.description?.toLowerCase().includes(query) ||
      template.category.toLowerCase().includes(query)
    )
  }
  
  // Filter by family
  if (selectedFamily.value) {
    result = result.filter(template => template.family_id === selectedFamily.value)
  }
  
  // Filter by category
  if (selectedCategory.value) {
    result = result.filter(template => template.category === selectedCategory.value)
  }
  
  return result
})

const categories = computed(() => {
  const cats = new Set(templatesStore.templates.map(t => t.category))
  return Array.from(cats).sort()
})

onMounted(() => {
  templatesStore.fetchTemplates()
  familiesStore.fetchFamilies()
})

function createTemplate() {
  router.visit('/admin/templates/create')
}

function editTemplate(template: Template) {
  router.visit(`/admin/templates/${template.id}/edit`)
}

function viewTemplate(template: Template) {
  router.visit(`/admin/templates/${template.id}/edit`)
}

async function deleteTemplate(template: Template) {
  const confirmed = confirm(`Are you sure you want to delete "${template.name}"?`)
  if (!confirmed) return

  try {
    await templatesStore.deleteTemplate(template.id)
  } catch (error) {
    console.error('Failed to delete template:', error)
  }
}

function clearFilters() {
  searchQuery.value = ''
  selectedFamily.value = null
  selectedCategory.value = null
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
    <div class="templates-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Templates</h1>
          <p class="page-subtitle">Manage your document templates</p>
        </div>
        <va-button @click="createTemplate" color="primary" icon="add">
          New Template
        </va-button>
      </div>

      <!-- Filters & Search -->
      <va-card class="filters-card">
        <va-card-content>
          <div class="filters">
            <va-input
              v-model="searchQuery"
              placeholder="Search templates..."
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
              v-model="selectedCategory"
              :options="categories"
              placeholder="All Categories"
              clearable
              class="filter-select"
            />
            
            <va-button
              v-if="searchQuery || selectedFamily || selectedCategory"
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
          <div v-if="templatesStore.loading" class="loading-state">
            <va-progress-circle indeterminate />
            <p>Loading templates...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="filteredTemplates.length === 0" class="empty-state">
            <va-icon name="description" size="large" color="secondary" />
            <p v-if="searchQuery || selectedFamily || selectedCategory">
              No templates match your filters
            </p>
            <p v-else>
              No templates yet. Create your first one!
            </p>
            <va-button
              v-if="!searchQuery && !selectedFamily && !selectedCategory"
              @click="createTemplate"
              color="primary"
              class="mt-4"
            >
              Create Template
            </va-button>
          </div>

          <!-- Data Table -->
          <va-data-table
            v-else
            :items="filteredTemplates"
            :columns="columns"
            striped
            hoverable
            :per-page="15"
            @row:click="(event) => viewTemplate(event.item)"
            class="templates-table"
          >
            <!-- Family Column -->
            <template #cell(family)="{ rowData }">
              <span v-if="rowData.family">
                {{ rowData.family.name }}
              </span>
              <span v-else class="text-muted">-</span>
            </template>

            <!-- Variant Column -->
            <template #cell(layout_variant)="{ value }">
              <va-chip v-if="value" size="small" color="info">
                {{ value }}
              </va-chip>
              <span v-else class="text-muted">-</span>
            </template>

            <!-- Visibility Column -->
            <template #cell(is_public)="{ value }">
              <va-chip
                :color="value ? 'success' : 'secondary'"
                size="small"
              >
                {{ value ? 'Public' : 'Private' }}
              </va-chip>
            </template>

            <!-- Version Column -->
            <template #cell(version)="{ value }">
              <code class="version-badge">{{ value }}</code>
            </template>

            <!-- Updated At Column -->
            <template #cell(updated_at)="{ value }">
              <span class="text-secondary">{{ formatDate(value) }}</span>
            </template>

            <!-- Actions Column -->
            <template #cell(actions)="{ rowData }">
              <div class="actions-cell" @click.stop>
                <va-button
                  preset="plain"
                  icon="visibility"
                  size="small"
                  @click="viewTemplate(rowData)"
                  title="View"
                />
                <va-button
                  preset="plain"
                  icon="edit"
                  size="small"
                  @click="editTemplate(rowData)"
                  title="Edit"
                />
                <va-button
                  preset="plain"
                  icon="delete"
                  size="small"
                  color="danger"
                  @click="deleteTemplate(rowData)"
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
.templates-page {
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

.templates-table {
  cursor: pointer;
}

.templates-table :deep(tbody tr:hover) {
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
}

.version-badge {
  background: var(--va-background-element);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
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
