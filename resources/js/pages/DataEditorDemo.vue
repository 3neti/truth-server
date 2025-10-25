<script setup lang="ts">
import { ref } from 'vue'
import { DataEditor } from '@lbhurtado/vue-data-editor'

const sampleData = ref({
  title: '2025 General Election',
  id: 'BAL-2025-001',
  date: '2025-11-04',
  total_precincts: 150,
  is_active: true,
  items: [
    {
      code: 'ITEM1',
      name: 'President',
      position: 1,
    },
    {
      code: 'ITEM2',
      name: 'Vice President',
      position: 2,
    },
  ],
  metadata: {
    created_by: 'admin',
    region: 'NCR',
    verified: false,
  },
})
</script>

<template>
  <div class="min-h-screen bg-gray-50 p-8">
    <div class="max-w-7xl mx-auto">
      <h1 class="text-3xl font-bold text-gray-900 mb-6">Data Editor Demo</h1>
      
      <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <DataEditor v-model="sampleData" />
      </div>

      <!-- Debug Output -->
      <div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">Current Data (Debug)</h2>
        <pre class="bg-gray-100 p-4 rounded text-sm overflow-auto">{{ JSON.stringify(sampleData, null, 2) }}</pre>
      </div>
    </div>
  </div>
</template>
