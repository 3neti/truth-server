<script setup lang="ts">
import { ref, onMounted, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AdminLayout from '../../layouts/AdminLayout.vue'
import FamilyDrawer from '../../components/FamilyDrawer.vue'
import { useFamiliesStore } from '../../stores/families'

const props = defineProps<{
  familyId: string | number
}>()

const familiesStore = useFamiliesStore()
const activeTab = ref('overview')
const isEditDrawerOpen = ref(false)

const family = computed(() => familiesStore.currentFamily)

const tabs = [
  { value: 'overview', label: 'Overview', icon: 'info' },
  { value: 'templates', label: 'Templates', icon: 'description' },
  { value: 'variants', label: 'Variants', icon: 'library_books' },
  { value: 'activity', label: 'Activity', icon: 'timeline' },
  { value: 'settings', label: 'Settings', icon: 'settings' },
]

onMounted(() => {
  familiesStore.fetchFamily(props.familyId)
})

function goBack() {
  router.visit('/admin/families')
}

function openEditDrawer() {
  isEditDrawerOpen.value = true
}

function closeEditDrawer() {
  isEditDrawerOpen.value = false
}

function handleSaved() {
  closeEditDrawer()
  familiesStore.fetchFamily(props.familyId)
}

async function deleteFamily() {
  if (!family.value) return
  
  const confirmed = confirm(`Are you sure you want to delete "${family.value.name}"?`)
  if (!confirmed) return

  try {
    await familiesStore.deleteFamily(family.value.id)
    goBack()
  } catch (error) {
    console.error('Failed to delete family:', error)
  }
}

function formatDate(dateString: string) {
  return new Date(dateString).toLocaleString('en-US', {
    year: 'numeric',
    month: 'long',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
}
</script>

<template>
  <AdminLayout>
    <div class="family-detail">
      <!-- Loading State -->
      <div v-if="familiesStore.loading" class="loading-state">
        <va-progress-circle indeterminate />
        <p>Loading family...</p>
      </div>

      <!-- Error State -->
      <div v-else-if="familiesStore.error" class="error-state">
        <va-icon name="error" size="large" color="danger" />
        <p>{{ familiesStore.error }}</p>
        <va-button @click="goBack" preset="secondary">
          Go Back
        </va-button>
      </div>

      <!-- Content -->
      <template v-else-if="family">
        <!-- Page Header -->
        <div class="page-header">
          <div class="header-left">
            <va-button
              preset="plain"
              icon="arrow_back"
              @click="goBack"
              class="back-button"
            />
            <div>
              <div class="title-row">
                <h1 class="page-title">{{ family.name }}</h1>
                <va-chip
                  :color="family.is_active ? 'success' : 'secondary'"
                  size="small"
                >
                  {{ family.is_active ? 'Active' : 'Inactive' }}
                </va-chip>
              </div>
              <p class="page-subtitle">{{ family.slug }}</p>
            </div>
          </div>
          
          <div class="header-actions">
            <va-button
              @click="openEditDrawer"
              icon="edit"
              preset="secondary"
            >
              Edit
            </va-button>
            <va-button
              @click="deleteFamily"
              icon="delete"
              color="danger"
              preset="secondary"
            >
              Delete
            </va-button>
          </div>
        </div>

        <!-- Tabs -->
        <va-tabs v-model="activeTab" class="tabs-container">
          <template #tabs>
            <va-tab
              v-for="tab in tabs"
              :key="tab.value"
              :name="tab.value"
            >
              <va-icon :name="tab.icon" class="tab-icon" />
              {{ tab.label }}
            </va-tab>
          </template>
        </va-tabs>

        <!-- Tab Content -->
        <div class="tab-content">
          <!-- Overview Tab -->
          <div v-if="activeTab === 'overview'" class="overview-tab">
            <va-card class="info-card">
              <va-card-title>Information</va-card-title>
              <va-card-content>
                <div class="info-grid">
                  <div class="info-item">
                    <label>Name</label>
                    <p>{{ family.name }}</p>
                  </div>
                  <div class="info-item">
                    <label>Slug</label>
                    <p><code>{{ family.slug }}</code></p>
                  </div>
                  <div class="info-item">
                    <label>Description</label>
                    <p>{{ family.description || 'No description provided' }}</p>
                  </div>
                  <div class="info-item">
                    <label>Tags</label>
                    <div class="tags-list">
                      <va-chip
                        v-for="(tag, index) in family.tags"
                        :key="index"
                        size="small"
                        color="primary"
                      >
                        {{ tag }}
                      </va-chip>
                      <span v-if="!family.tags || family.tags.length === 0" class="text-muted">
                        No tags
                      </span>
                    </div>
                  </div>
                  <div class="info-item">
                    <label>Status</label>
                    <p>
                      <va-chip
                        :color="family.is_active ? 'success' : 'secondary'"
                        size="small"
                      >
                        {{ family.is_active ? 'Active' : 'Inactive' }}
                      </va-chip>
                    </p>
                  </div>
                  <div class="info-item">
                    <label>Templates</label>
                    <p>{{ family.templates_count || 0 }} templates</p>
                  </div>
                  <div class="info-item">
                    <label>Created</label>
                    <p class="text-muted">{{ formatDate(family.created_at) }}</p>
                  </div>
                  <div class="info-item">
                    <label>Last Updated</label>
                    <p class="text-muted">{{ formatDate(family.updated_at) }}</p>
                  </div>
                </div>
              </va-card-content>
            </va-card>
          </div>

          <!-- Templates Tab -->
          <div v-else-if="activeTab === 'templates'" class="templates-tab">
            <va-card>
              <va-card-content>
                <div class="empty-state">
                  <va-icon name="description" size="large" color="secondary" />
                  <p>Templates list coming soon</p>
                </div>
              </va-card-content>
            </va-card>
          </div>

          <!-- Variants Tab -->
          <div v-else-if="activeTab === 'variants'" class="variants-tab">
            <va-card>
              <va-card-content>
                <div class="empty-state">
                  <va-icon name="library_books" size="large" color="secondary" />
                  <p>Variants management coming soon</p>
                </div>
              </va-card-content>
            </va-card>
          </div>

          <!-- Activity Tab -->
          <div v-else-if="activeTab === 'activity'" class="activity-tab">
            <va-card>
              <va-card-content>
                <div class="empty-state">
                  <va-icon name="timeline" size="large" color="secondary" />
                  <p>Activity log coming soon</p>
                </div>
              </va-card-content>
            </va-card>
          </div>

          <!-- Settings Tab -->
          <div v-else-if="activeTab === 'settings'" class="settings-tab">
            <va-card>
              <va-card-content>
                <div class="empty-state">
                  <va-icon name="settings" size="large" color="secondary" />
                  <p>Settings panel coming soon</p>
                </div>
              </va-card-content>
            </va-card>
          </div>
        </div>
      </template>
    </div>

    <!-- Edit Drawer -->
    <FamilyDrawer
      v-model:visible="isEditDrawerOpen"
      :family="family"
      @close="closeEditDrawer"
      @saved="handleSaved"
    />
  </AdminLayout>
</template>

<style scoped>
.family-detail {
  max-width: 1400px;
  margin: 0 auto;
}

.loading-state,
.error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  color: var(--va-text-secondary);
}

.loading-state p,
.error-state p {
  margin-top: 1rem;
  font-size: 1rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: flex-start;
  margin-bottom: 2rem;
  gap: 1rem;
}

.header-left {
  display: flex;
  gap: 1rem;
  align-items: flex-start;
}

.back-button {
  margin-top: 0.25rem;
}

.title-row {
  display: flex;
  align-items: center;
  gap: 1rem;
}

.page-title {
  font-size: 2rem;
  font-weight: 700;
  color: var(--va-text-primary);
  margin: 0;
}

.page-subtitle {
  font-size: 0.875rem;
  color: var(--va-text-secondary);
  margin: 0.5rem 0 0 0;
  font-family: monospace;
}

.header-actions {
  display: flex;
  gap: 0.75rem;
}

.tabs-container {
  margin-bottom: 2rem;
}

.tab-icon {
  margin-right: 0.5rem;
}

.tab-content {
  min-height: 400px;
}

.info-card {
  margin-bottom: 2rem;
}

.info-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
  gap: 2rem;
}

.info-item {
  display: flex;
  flex-direction: column;
  gap: 0.5rem;
}

.info-item label {
  font-size: 0.875rem;
  font-weight: 600;
  color: var(--va-text-secondary);
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.info-item p {
  margin: 0;
  font-size: 1rem;
  color: var(--va-text-primary);
}

.info-item code {
  background: var(--va-background-element);
  padding: 0.25rem 0.5rem;
  border-radius: 0.25rem;
  font-family: monospace;
  font-size: 0.875rem;
}

.tags-list {
  display: flex;
  flex-wrap: wrap;
  gap: 0.5rem;
}

.text-muted {
  color: var(--va-text-secondary);
  font-size: 0.875rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 4rem 2rem;
  text-align: center;
  color: var(--va-text-secondary);
}

.empty-state p {
  margin-top: 1rem;
  font-size: 1rem;
}

@media (max-width: 768px) {
  .page-header {
    flex-direction: column;
  }

  .header-left {
    flex-direction: column;
  }

  .title-row {
    flex-direction: column;
    align-items: flex-start;
  }

  .header-actions {
    width: 100%;
  }

  .info-grid {
    grid-template-columns: 1fr;
  }
}
</style>
