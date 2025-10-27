<script setup lang="ts">
import { onMounted } from 'vue'
import AdminLayout from '../../layouts/AdminLayout.vue'
import { useDashboardStore } from '../../stores/dashboard'

const dashboardStore = useDashboardStore()

onMounted(() => {
  dashboardStore.fetchStats()
})
</script>

<template>
  <AdminLayout>
    <div class="dashboard">
      <!-- Page Header -->
      <div class="page-header">
        <h1 class="page-title">Dashboard</h1>
        <p class="page-subtitle">Welcome to Truth Admin Panel</p>
      </div>

      <!-- Stats Cards -->
      <div class="stats-grid">
        <va-card class="stat-card">
          <va-card-content>
            <div class="stat-icon" style="background: #e0f2fe">
              <va-icon name="folder" color="#0284c7" size="large" />
            </div>
            <div class="stat-content">
              <div class="stat-label">Template Families</div>
              <div class="stat-value">
                {{ dashboardStore.loading ? '-' : dashboardStore.stats.families_count }}
              </div>
            </div>
          </va-card-content>
        </va-card>

        <va-card class="stat-card">
          <va-card-content>
            <div class="stat-icon" style="background: #ddd6fe">
              <va-icon name="description" color="#7c3aed" size="large" />
            </div>
            <div class="stat-content">
              <div class="stat-label">Templates</div>
              <div class="stat-value">
                {{ dashboardStore.loading ? '-' : dashboardStore.stats.templates_count }}
              </div>
            </div>
          </va-card-content>
        </va-card>

        <va-card class="stat-card">
          <va-card-content>
            <div class="stat-icon" style="background: #d1fae5">
              <va-icon name="data_object" color="#059669" size="large" />
            </div>
            <div class="stat-content">
              <div class="stat-label">Data Files</div>
              <div class="stat-value">
                {{ dashboardStore.loading ? '-' : dashboardStore.stats.data_files_count }}
              </div>
            </div>
          </va-card-content>
        </va-card>

        <va-card class="stat-card">
          <va-card-content>
            <div class="stat-icon" style="background: #fef3c7">
              <va-icon name="work" color="#d97706" size="large" />
            </div>
            <div class="stat-content">
              <div class="stat-label">Recent Jobs</div>
              <div class="stat-value">
                {{ dashboardStore.loading ? '-' : dashboardStore.stats.recent_jobs_count }}
              </div>
            </div>
          </va-card-content>
        </va-card>
      </div>

      <!-- Quick Actions -->
      <div class="quick-actions">
        <va-card>
          <va-card-title>Quick Actions</va-card-title>
          <va-card-content>
            <div class="actions-grid">
              <va-button href="/admin/families" color="primary" icon="add">
                New Family
              </va-button>
              <va-button href="/admin/templates" color="primary" icon="add">
                New Template
              </va-button>
              <va-button href="/admin/data" color="primary" icon="add">
                New Data File
              </va-button>
              <va-button href="/admin/jobs" color="info" icon="visibility">
                View Jobs
              </va-button>
            </div>
          </va-card-content>
        </va-card>
      </div>

      <!-- Recent Activity (Placeholder) -->
      <div class="recent-activity">
        <va-card>
          <va-card-title>Recent Activity</va-card-title>
          <va-card-content>
            <div class="empty-state">
              <va-icon name="timeline" size="large" color="secondary" />
              <p>No recent activity to display</p>
            </div>
          </va-card-content>
        </va-card>
      </div>
    </div>
  </AdminLayout>
</template>

<style scoped>
.dashboard {
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
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

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  cursor: default;
}

.stat-card .va-card__content {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1.5rem;
}

.stat-icon {
  width: 64px;
  height: 64px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-content {
  flex: 1;
}

.stat-label {
  font-size: 0.875rem;
  color: var(--va-text-secondary);
  margin-bottom: 0.25rem;
}

.stat-value {
  font-size: 2rem;
  font-weight: 700;
  color: var(--va-text-primary);
}

.quick-actions {
  margin-bottom: 2rem;
}

.actions-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 1rem;
}

.recent-activity {
  margin-bottom: 2rem;
}

.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 3rem 1rem;
  text-align: center;
  color: var(--va-text-secondary);
}

.empty-state p {
  margin-top: 1rem;
  font-size: 0.875rem;
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }

  .actions-grid {
    grid-template-columns: 1fr;
  }
}
</style>
