<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const features = [
  {
    title: 'Simple Editor',
    description: 'Create templates with an intuitive visual editor',
    icon: '📝',
    route: '/truth-templates/editor',
  },
  {
    title: 'Advanced Editor',
    description: 'Build complex templates with Handlebars syntax',
    icon: '⚙️',
    route: '/truth-templates/advanced',
  },
  {
    title: 'Data Editor',
    description: 'Manage template data and preview results',
    icon: '📊',
    route: '/truth-templates/data/editor',
  },
]
</script>

<template>
  <AppLayout>
    <Head title="Truth Templates" />

    <template #header>
      <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-200">
        Truth Templates
      </h2>
    </template>

    <div class="py-12">
      <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <!-- Welcome Section -->
        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-8 mb-6">
          <h1 class="text-3xl font-bold text-gray-900 dark:text-gray-100 mb-4">
            Welcome to Truth Templates
          </h1>
          <p class="text-lg text-gray-600 dark:text-gray-400">
            Create, manage, and render document templates for elections, surveys, and more.
          </p>
        </div>

        <!-- Features Grid -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <Link
            v-for="feature in features"
            :key="feature.route"
            :href="feature.route"
            class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-6 hover:shadow-md transition-shadow cursor-pointer group"
          >
            <div class="text-4xl mb-4">{{ feature.icon }}</div>
            <h3 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-2 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
              {{ feature.title }}
            </h3>
            <p class="text-gray-600 dark:text-gray-400">
              {{ feature.description }}
            </p>
          </Link>
        </div>

        <!-- Quick Stats (Optional) -->
        <div class="mt-8 bg-gradient-to-r from-blue-500 to-purple-600 overflow-hidden shadow-sm sm:rounded-lg p-8 text-white">
          <h3 class="text-2xl font-bold mb-4">Template System Features</h3>
          <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
              <div class="text-3xl font-bold">∞</div>
              <div class="text-sm opacity-90">Unlimited Templates</div>
            </div>
            <div>
              <div class="text-3xl font-bold">3</div>
              <div class="text-sm opacity-90">Editor Types</div>
            </div>
            <div>
              <div class="text-3xl font-bold">PDF</div>
              <div class="text-sm opacity-90">Export Format</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>
