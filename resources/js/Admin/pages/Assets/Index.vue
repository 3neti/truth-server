<script setup lang="ts">
import AdminLayout from '../../layouts/AdminLayout.vue'
</script>

<template>
  <AdminLayout>
    <div class="page-container">
      <div class="page-header">
        <h1 class="page-title">Assets</h1>
        <p class="page-subtitle">Manage your assets library</p>
      </div>
      
      <va-card>
        <va-card-content>
          <div class="empty-state">
            <va-icon name="collections" size="large" color="primary" />
            <p>Assets module coming soon...</p>
          </div>
        </va-card-content>
      </va-card>
    </div>
  </AdminLayout>
</template>

<style scoped>
.page-container { max-width: 1400px; margin: 0 auto; }
.page-header { margin-bottom: 2rem; }
.page-title { font-size: 2rem; font-weight: 700; margin: 0 0 0.5rem 0; }
.page-subtitle { font-size: 1rem; color: var(--va-text-secondary); margin: 0; }
.empty-state { display: flex; flex-direction: column; align-items: center; padding: 4rem 2rem; text-align: center; color: var(--va-text-secondary); }
.empty-state p { margin-top: 1rem; }
</style>
