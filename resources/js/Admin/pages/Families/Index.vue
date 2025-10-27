<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import FamilyDrawer from '../../components/FamilyDrawer.vue'
import { useFamiliesStore } from '../../stores/families'
import type { TemplateFamily } from '../../stores/families'

const familiesStore = useFamiliesStore()

const searchQuery = ref('')
const selectedFamilies = ref<TemplateFamily[]>([])
const isDrawerOpen = ref(false)
const editingFamily = ref<TemplateFamily | null>(null)
const isDeleting = ref(false)

const columns = [
  { key: 'name', label: 'Name', sortable: true },
  { key: 'slug', label: 'Slug', sortable: true },
  { key: 'templates_count', label: 'Templates', sortable: true },
  { key: 'is_active', label: 'Status', sortable: true },
  { key: 'updated_at', label: 'Updated', sortable: true },
  { key: 'actions', label: 'Actions', width: '120px' },
]

const filteredFamilies = computed(() => {
  if (!searchQuery.value) return familiesStore.families
  
  const query = searchQuery.value.toLowerCase()
  return familiesStore.families.filter(family => 
    family.name.toLowerCase().includes(query) ||
    family.slug.toLowerCase().includes(query) ||
    family.description?.toLowerCase().includes(query)
  )
})

onMounted(() => {
  familiesStore.fetchFamilies()
})

function openCreateDrawer() {
  editingFamily.value = null
  isDrawerOpen.value = true
}

function openEditDrawer(family: TemplateFamily) {
  editingFamily.value = family
  isDrawerOpen.value = true
}

function closeDrawer() {
  isDrawerOpen.value = false
  editingFamily.value = null
}

function viewFamily(family: TemplateFamily) {
  router.visit(`/admin/families/${family.id}`)
}

async function deleteFamily(family: TemplateFamily) {
  const confirmed = confirm(`Are you sure you want to delete "${family.name}"?`)
  if (!confirmed) return

  isDeleting.value = true
  try {
    await familiesStore.deleteFamily(family.id)
  } catch (error) {
    console.error('Failed to delete family:', error)
  } finally {
    isDeleting.value = false
  }
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
    <div class="families-page">
      <!-- Page Header -->
      <div class="page-header">
        <div>
          <h1 class="page-title">Template Families</h1>
          <p class="page-subtitle">Manage your template families and variants</p>
        </div>
        <va-button @click="openCreateDrawer" color="primary" icon="add">
          New Family
        </va-button>
      </div>

      <!-- Filters & Search -->
      <va-card class="filters-card">
        <va-card-content>
          <div class="filters">
            <va-input
              v-model="searchQuery"
              placeholder="Search families..."
              class="search-input"
            >
              <template #prependInner>
                <va-icon name="search" />
              </template>
            </va-input>
            
            <div class="spacer" />
            
            <va-button preset="plain" icon="filter_list">
              Filters
            </va-button>
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
          <div v-if="familiesStore.loading" class="loading-state">
            <va-progress-circle indeterminate />
            <p>Loading families...</p>
          </div>

          <!-- Empty State -->
          <div v-else-if="filteredFamilies.length === 0" class="empty-state">
            <va-icon name="folder_open" size="large" color="secondary" />
            <p v-if="searchQuery">
              No families match your search "{{ searchQuery }}"
            </p>
            <p v-else>
              No template families yet. Create your first one!
            </p>
            <va-button
              v-if="!searchQuery"
              @click="openCreateDrawer"
              color="primary"
              class="mt-4"
            >
              Create Family
            </va-button>
          </div>

          <!-- Data Table -->
          <va-data-table
            v-else
            :items="filteredFamilies"
            :columns="columns"
            striped
            hoverable
            :per-page="10"
            :current-page="1"
            @row:click="(event) => viewFamily(event.item)"
            class="families-table"
          >
            <!-- Status Column -->
            <template #cell(is_active)="{ value }">
              <va-chip
                :color="value ? 'success' : 'secondary'"
                size="small"
              >
                {{ value ? 'Active' : 'Inactive' }}
              </va-chip>
            </template>

            <!-- Templates Count Column -->
            <template #cell(templates_count)="{ value }">
              <va-badge :text="String(value || 0)" color="info" />
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
                  @click="viewFamily(rowData)"
                  title="View"
                />
                <va-button
                  preset="plain"
                  icon="edit"
                  size="small"
                  @click="openEditDrawer(rowData)"
                  title="Edit"
                />
                <va-button
                  preset="plain"
                  icon="delete"
                  size="small"
                  color="danger"
                  @click="deleteFamily(rowData)"
                  :disabled="isDeleting"
                  title="Delete"
                />
              </div>
            </template>
          </va-data-table>
        </va-card-content>
      </va-card>
    </div>

    <!-- Create/Edit Drawer -->
    <FamilyDrawer
      v-model:visible="isDrawerOpen"
      :family="editingFamily"
      @close="closeDrawer"
      @saved="closeDrawer"
    />
  </AdminLayout>
</template>

<style scoped>
.families-page {
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
}

.search-input {
  max-width: 400px;
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

.families-table {
  cursor: pointer;
}

.families-table :deep(tbody tr:hover) {
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

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
    gap: 1rem;
  }

  .filters {
    flex-direction: column;
    align-items: stretch;
  }

  .search-input {
    max-width: 100%;
  }
}
</style>
